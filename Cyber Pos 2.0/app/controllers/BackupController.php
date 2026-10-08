<?php
/**
 * CyberPOS - Controlador de gestion de la base de datos:
 * estadisticas, respaldo (export), restauracion (import) y reset.
 */

class BackupController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // ------------------------------------------------------------------
    // Estadisticas
    // ------------------------------------------------------------------
    public function stats(): void
    {
        $this->requireAuth();
        $count = fn (string $t) => (int) $this->db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        $this->ok([
            'usuarios'  => $count('usuarios'),
            'equipos'   => $count('equipos'),
            'productos' => $count('productos'),
            'sesiones'  => $count('sesiones_uso'),
        ]);
    }

    // ------------------------------------------------------------------
    // Exportar / respaldo
    // ------------------------------------------------------------------
    public function export(): void
    {
        $this->requireAdmin();
        $this->json([
            'version'   => '4.0',
            'exportado' => date('c'),
            'usuarios'  => $this->db->query('SELECT id, nombre_completo, email, password, rol, avatar_url, fecha_creacion FROM usuarios')->fetchAll(),
            'equipos'   => $this->db->query('SELECT * FROM equipos')->fetchAll(),
            'productos' => $this->db->query('SELECT * FROM productos')->fetchAll(),
            'sesiones_uso' => $this->db->query('SELECT * FROM sesiones_uso')->fetchAll(),
            'solicitudes_tiempo' => $this->db->query('SELECT * FROM solicitudes_tiempo')->fetchAll(),
            'pedidos_snacks' => $this->db->query('SELECT * FROM pedidos_snacks')->fetchAll(),
            'config'    => $this->db->query('SELECT * FROM configuracion WHERE id = 1')->fetch() ?: null,
        ], 200);
    }

    // ------------------------------------------------------------------
    // Importar / restaurar
    // ------------------------------------------------------------------
    public function import(): void
    {
        $this->requireAdmin();
        $body = $this->body();
        $data = $body;
        if (isset($body['contenido']) && is_string($body['contenido'])) {
            $data = json_decode($body['contenido'], true) ?: [];
        } elseif (isset($body['data']) && is_array($body['data'])) {
            $data = $body['data'];
        }

        if (!is_array($data) || !isset($data['usuarios'], $data['equipos'])) {
            $this->fail('El archivo de respaldo no es válido.', 400);
        }

        try {
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
            $this->db->beginTransaction();
            foreach (['sesiones_uso', 'solicitudes_tiempo', 'pedidos_snacks', 'productos', 'equipos', 'usuarios'] as $t) {
                $this->db->exec("TRUNCATE TABLE {$t}");
            }

            $this->insertUsuarios($data['usuarios'] ?? []);
            $this->insertEquipos($data['equipos'] ?? []);
            $this->insertProductos($this->normalizarProductos($data));
            $this->insertSesiones($data['sesiones_uso'] ?? []);
            $this->insertSolicitudes($data['solicitudes_tiempo'] ?? []);
            $this->insertPedidos($data['pedidos_snacks'] ?? []);
            if (!empty($data['config']) && is_array($data['config'])) {
                $this->guardarConfig($data['config']);
            }

            $this->db->commit();
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
            $this->fail('Error al importar: ' . $e->getMessage(), 500);
        }

        $this->ok(null, 'Copia de seguridad restaurada correctamente.');
    }

    /** Acepta formato nuevo (productos) o antiguo (snacks + productos_personalizados). */
    private function normalizarProductos(array $data): array
    {
        if (!empty($data['productos']) && is_array($data['productos'])) {
            $out = $data['productos'];
            foreach ($out as &$p) {
                if (empty($p['origen'])) {
                    $p['origen'] = ($p['_origen'] ?? 'snacks');
                }
            }
            unset($p);
            return $out;
        }
        $out = [];
        foreach (($data['snacks'] ?? []) as $p) {
            $p['origen'] = 'snacks';
            $out[] = $p;
        }
        foreach (($data['productos_personalizados'] ?? []) as $p) {
            $p['origen'] = 'personalizados';
            $out[] = $p;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Reset (datos de fabrica)
    // ------------------------------------------------------------------
    public function reset(): void
    {
        $this->requireAdmin();
        try {
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['sesiones_uso', 'solicitudes_tiempo', 'pedidos_snacks', 'productos', 'equipos', 'usuarios', 'configuracion'] as $t) {
                $this->db->exec("TRUNCATE TABLE {$t}");
            }
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

            $this->insertUsuarios([
                ['id'=>1,'nombre_completo'=>'Administrador Laneros','email'=>'admin@laneros.com','password'=>'admin123','rol'=>'administrador','avatar_url'=>'','fecha_creacion'=>'2026-10-01 10:00:00'],
                ['id'=>2,'nombre_completo'=>'Kelvin Nieto','email'=>'kelvin@laneros.com','password'=>'admin123','rol'=>'administrador','avatar_url'=>'','fecha_creacion'=>'2026-10-01 10:00:00'],
                ['id'=>3,'nombre_completo'=>'Cliente Gamer Demo','email'=>'cliente@laneros.com','password'=>'cliente123','rol'=>'cliente','avatar_url'=>'','fecha_creacion'=>'2026-10-01 10:00:00'],
            ]);
            $this->insertEquipos($this->equiposSeed());
            $this->insertProductos($this->productosSeed());
            $this->db->exec(
                'INSERT INTO configuracion (id, tasa_bcv, tasa_manual, nombre_cyber, precio_hora_pc, precio_hora_consola, ultima_actualizacion_tasa, theme_color, avatar_url)
                 VALUES (1, 36.85, 0, "CyberPOS Laneros Gamer", 1.50, 2.00, NOW(), "#35c029", "")'
            );
            $this->ok(null, 'Base de datos restablecida.');
        } catch (Throwable $e) {
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
            $this->fail('Error al restablecer: ' . $e->getMessage(), 500);
        }
    }

    // ------------------------------------------------------------------
    // Inserción de filas
    // ------------------------------------------------------------------

    private function insertUsuarios(array $rows): void
    {
        $st = $this->db->prepare(
            'INSERT INTO usuarios (id, nombre_completo, email, password, rol, avatar_url, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $u) {
            $pass = (string) ($u['password'] ?? 'admin123');
            if (strpos($pass, '$2') !== 0) {
                $pass = password_hash($pass, PASSWORD_BCRYPT);
            }
            $st->execute([
                !empty($u['id']) ? (int) $u['id'] : null,
                (string) ($u['nombre_completo'] ?? 'Usuario'),
                strtolower((string) ($u['email'] ?? '')),
                $pass,
                ($u['rol'] ?? 'cliente') === 'administrador' ? 'administrador' : 'cliente',
                (string) ($u['avatar_url'] ?? ''),
                !empty($u['fecha_creacion']) ? $u['fecha_creacion'] : date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insertEquipos(array $rows): void
    {
        $st = $this->db->prepare(
            'INSERT INTO equipos (id, nombre_equipo, numero, tipo, estado, tiempo_transcurrido, tiempo_limite, tarifa_por_hora, cliente, fecha_inicio, notificado, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $e) {
            $tipo   = ($e['tipo'] ?? 'pc') === 'consola' ? 'consola' : 'pc';
            $estado = $e['estado'] ?? 'inactiva';
            if (!in_array($estado, ['inactiva', 'activa', 'finalizada'], true)) {
                $estado = 'inactiva';
            }
            $st->execute([
                !empty($e['id']) ? (int) $e['id'] : null,
                (string) ($e['nombre_equipo'] ?? 'Equipo'),
                !empty($e['numero']) ? (string) $e['numero'] : null,
                $tipo,
                $estado,
                (int) ($e['tiempo_transcurrido'] ?? 0),
                (int) ($e['tiempo_limite'] ?? 0),
                (float) ($e['tarifa_por_hora'] ?? ($tipo === 'consola' ? 2.00 : 1.50)),
                (string) ($e['cliente'] ?? ''),
                !empty($e['fecha_inicio']) ? $e['fecha_inicio'] : null,
                !empty($e['notificado']) ? 1 : 0,
                !empty($e['fecha_creacion']) ? $e['fecha_creacion'] : date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insertProductos(array $rows): void
    {
        $st = $this->db->prepare(
            'INSERT INTO productos (id, nombre, precio, stock, url_imagen, categoria, origen, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $p) {
            $st->execute([
                !empty($p['id']) ? (int) $p['id'] : null,
                (string) ($p['nombre'] ?? 'Producto'),
                (float) ($p['precio'] ?? 0),
                (int) ($p['stock'] ?? 0),
                (string) ($p['url_imagen'] ?? ''),
                (string) ($p['categoria'] ?? 'otro'),
                ($p['origen'] ?? $p['_origen'] ?? 'snacks') === 'personalizados' ? 'personalizados' : 'snacks',
                !empty($p['fecha_creacion']) ? $p['fecha_creacion'] : date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insertSesiones(array $rows): void
    {
        $st = $this->db->prepare(
            'INSERT INTO sesiones_uso (id, equipo_id, equipo_nombre, cliente, segundos_alquilados, monto_cobrado, tipo_registro, fecha_registro)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $s) {
            $st->execute([
                !empty($s['id']) ? (int) $s['id'] : null,
                !empty($s['equipo_id']) ? (int) $s['equipo_id'] : null,
                (string) ($s['equipo_nombre'] ?? 'Venta Directa'),
                (string) ($s['cliente'] ?? 'Público'),
                (int) ($s['segundos_alquilados'] ?? 0),
                (float) ($s['monto_cobrado'] ?? 0),
                ($s['tipo_registro'] ?? 'venta_snack') === 'alquiler_tiempo' ? 'alquiler_tiempo' : 'venta_snack',
                !empty($s['fecha_registro']) ? $s['fecha_registro'] : date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insertSolicitudes(array $rows): void
    {
        $st = $this->db->prepare(
            'INSERT INTO solicitudes_tiempo (id, equipo_id, cliente_id, cliente_nombre, minutos, monto, estado, confirmado_admin, confirmado_cliente, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $s) {
            $estado = in_array($s['estado'] ?? '', ['pendiente','esperando_cliente','esperando_admin','completada','cancelada'], true)
                ? $s['estado'] : 'pendiente';
            $st->execute([
                !empty($s['id']) ? (int) $s['id'] : null,
                !empty($s['equipo_id']) ? (int) $s['equipo_id'] : null,
                !empty($s['cliente_id']) ? (int) $s['cliente_id'] : null,
                (string) ($s['cliente_nombre'] ?? 'Cliente'),
                (int) ($s['minutos'] ?? 0),
                (float) ($s['monto'] ?? 0),
                $estado,
                !empty($s['confirmado_admin']) ? 1 : 0,
                !empty($s['confirmado_cliente']) ? 1 : 0,
                !empty($s['fecha']) ? $s['fecha'] : date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insertPedidos(array $rows): void
    {
        $st = $this->db->prepare(
            'INSERT INTO pedidos_snacks (id, equipo_id, equipo_nombre, cliente_nombre, items, total, estado, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($rows as $p) {
            $items = $p['items'] ?? [];
            if (is_string($items)) {
                $decoded = json_decode($items, true);
                $items = is_array($decoded) ? $decoded : [];
            }
            $estado = in_array($p['estado'] ?? '', ['pendiente','completado','cancelado'], true) ? $p['estado'] : 'pendiente';
            $st->execute([
                !empty($p['id']) ? (int) $p['id'] : null,
                !empty($p['equipo_id']) ? (int) $p['equipo_id'] : null,
                (string) ($p['equipo_nombre'] ?? 'Mostrador'),
                (string) ($p['cliente_nombre'] ?? 'Cliente'),
                json_encode($items, JSON_UNESCAPED_UNICODE),
                (float) ($p['total'] ?? 0),
                $estado,
                !empty($p['fecha']) ? $p['fecha'] : date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function guardarConfig(array $c): void
    {
        $st = $this->db->prepare(
            'INSERT INTO configuracion (id, tasa_bcv, tasa_manual, nombre_cyber, precio_hora_pc, precio_hora_consola, ultima_actualizacion_tasa, theme_color, avatar_url)
             VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE tasa_bcv=VALUES(tasa_bcv), tasa_manual=VALUES(tasa_manual), nombre_cyber=VALUES(nombre_cyber),
               precio_hora_pc=VALUES(precio_hora_pc), precio_hora_consola=VALUES(precio_hora_consola),
               ultima_actualizacion_tasa=VALUES(ultima_actualizacion_tasa), theme_color=VALUES(theme_color), avatar_url=VALUES(avatar_url)'
        );
        $st->execute([
            (float) ($c['tasa_bcv'] ?? 36.85),
            !empty($c['tasa_manual']) ? 1 : 0,
            (string) ($c['nombre_cyber'] ?? 'CyberPOS Laneros Gamer'),
            (float) ($c['precio_hora_pc'] ?? 1.50),
            (float) ($c['precio_hora_consola'] ?? 2.00),
            !empty($c['ultima_actualizacion_tasa']) ? $c['ultima_actualizacion_tasa'] : date('Y-m-d H:i:s'),
            (string) ($c['theme_color'] ?? '#35c029'),
            (string) ($c['avatar_url'] ?? ''),
        ]);
    }

    // ------------------------------------------------------------------
    // Semillas
    // ------------------------------------------------------------------

    private function equiposSeed(): array
    {
        $out = [];
        for ($i = 1; $i <= 10; $i++) {
            $esConsola = $i > 6;
            $out[] = [
                'nombre_equipo'   => $esConsola ? 'Consola ' . str_pad((string) ($i - 6), 2, '0', STR_PAD_LEFT)
                                                : 'PC ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'numero'          => str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'tipo'            => $esConsola ? 'consola' : 'pc',
                'tarifa_por_hora' => $esConsola ? 2.00 : 1.50,
            ];
        }
        return $out;
    }

    private function productosSeed(): array
    {
        $base = [
            ['Monster Energy', 2.50, 24, 'bebidas', 'https://images.unsplash.com/photo-1622543925917-763c34d1a86e?w=500&q=80'],
            ['Lays Papas', 1.50, 15, 'comida', 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=500&q=80'],
            ['Snickers', 1.00, 30, 'comida', 'https://images.unsplash.com/photo-1570145820259-b5b80c5c8bd6?w=500&q=80'],
            ['Coca Cola 500ml', 1.75, 20, 'bebidas', 'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=500&q=80'],
            ['Doritos Mega Queso', 1.80, 18, 'comida', 'https://images.unsplash.com/photo-1600952841320-db92ec4047ca?w=500&q=80'],
            ['Agua Mineral 600ml', 1.00, 45, 'bebidas', 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=500&q=80'],
            ['Oreo', 1.25, 35, 'comida', 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=500&q=80'],
            ['Hamburguesa Doble Gamer', 4.50, 12, 'comida', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500&q=80'],
        ];
        $out = [];
        foreach ($base as $p) {
            $out[] = ['nombre' => $p[0], 'precio' => $p[1], 'stock' => $p[2], 'categoria' => $p[3], 'url_imagen' => $p[4], 'origen' => 'snacks'];
        }
        return $out;
    }
}