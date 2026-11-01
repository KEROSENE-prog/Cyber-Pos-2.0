<?php
/**
 * CyberPOS - Modelo Equipo (PC / Consola).
 * El tiempo transcurrido se calcula en el servidor a partir de fecha_inicio.
 * Inmunidad ante SQLi y sanitización XSS (Etapa 2).
 * Unicidad de nombre_equipo, transacciones atómicas inicio/recarga + sesión (Etapa 3).
 */

class Equipo extends Model
{
    private function cast(array $e): array
    {
        $e['id']                  = (int) $e['id'];
        $e['tiempo_transcurrido'] = (int) $e['tiempo_transcurrido'];
        $e['tiempo_limite']       = (int) $e['tiempo_limite'];
        $e['tarifa_por_hora']     = (float) $e['tarifa_por_hora'];
        $e['notificado']          = (bool) $e['notificado'];
        return $e;
    }

    /** Segundos transcurridos reales (acumulado + tiempo de la corrida activa). */
    private function transcurridoActual(array $e): int
    {
        $base = (int) $e['tiempo_transcurrido'];
        if ($e['estado'] === 'activa' && !empty($e['fecha_inicio'])) {
            $base += max(0, time() - strtotime($e['fecha_inicio']));
        }
        return $base;
    }

    /** Congela un equipo activo cuyo limite ya se alcanzo. */
    private function normalizar(array $e): array
    {
        $e['tiempo_transcurrido'] = $this->transcurridoActual($e);
        if ($e['estado'] === 'activa'
            && (int) $e['tiempo_limite'] > 0
            && $e['tiempo_transcurrido'] >= (int) $e['tiempo_limite']) {
            $st = $this->db->prepare(
                'UPDATE equipos SET estado = "finalizada", tiempo_transcurrido = ?, fecha_inicio = NULL, notificado = 1 WHERE id = ?'
            );
            $st->execute([$e['tiempo_transcurrido'], $e['id']]);
            $e['estado']       = 'finalizada';
            $e['fecha_inicio'] = null;
            $e['notificado']   = 1;
        }
        return $this->cast($e);
    }

    private function raw(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM equipos WHERE id = ?');
        $st->execute([$id]);
        $e = $st->fetch();
        return $e ?: null;
    }

    /** Verifica si ya existe un equipo con el mismo nombre (excluyendo el id dado). */
    private function nombreExiste(string $nombre, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $st = $this->db->prepare(
                'SELECT COUNT(*) FROM equipos WHERE nombre_equipo = ? AND id != ?'
            );
            $st->execute([$nombre, $excludeId]);
        } else {
            $st = $this->db->prepare(
                'SELECT COUNT(*) FROM equipos WHERE nombre_equipo = ?'
            );
            $st->execute([$nombre]);
        }
        return (int) $st->fetchColumn() > 0;
    }

    public function getAll(): array
    {
        $rows = $this->db->query('SELECT * FROM equipos ORDER BY id')->fetchAll();
        return array_map(function ($e) {
            return $this->normalizar($e);
        }, $rows);
    }

    public function getById(int $id): ?array
    {
        $e = $this->raw($id);
        return $e ? $this->normalizar($e) : null;
    }

    public function create(array $data): array
    {
        $nombreRaw = isset($data['nombre_equipo']) ? (string) $data['nombre_equipo'] : '';
        $nombre    = trim(strip_tags($nombreRaw));

        if ($nombre === '') {
            return ['success' => false, 'error' => 'El nombre del equipo es obligatorio y no puede consistir únicamente en etiquetas HTML o espacios.'];
        }
        if (mb_strlen($nombre) > 100) {
            return ['success' => false, 'error' => 'El nombre del equipo no puede exceder los 100 caracteres.'];
        }

        // ── UNICIDAD: bloquear nombre duplicado ──────────────────────────
        if ($this->nombreExiste($nombre)) {
            return ['success' => false, 'error' => "Ya existe un equipo registrado con el nombre \"{$nombre}\". Usa un nombre diferente."];
        }

        $tipo = ($data['tipo'] ?? 'pc') === 'consola' ? 'consola' : 'pc';

        if (isset($data['tarifa_por_hora'])) {
            if (!is_numeric($data['tarifa_por_hora'])) {
                return ['success' => false, 'error' => 'Tarifa por hora inválida: debe ser un valor numérico.'];
            }
            $tarifa = (float) $data['tarifa_por_hora'];
            if (is_nan($tarifa) || is_infinite($tarifa) || $tarifa <= 0) {
                return ['success' => false, 'error' => 'Tarifa por hora inválida: debe ser mayor a 0.'];
            }
        } else {
            $tarifa = ($tipo === 'consola') ? 2.00 : 1.50;
        }

        try {
            $st = $this->db->prepare(
                'INSERT INTO equipos (nombre_equipo, tipo, tarifa_por_hora, estado, tiempo_transcurrido, tiempo_limite, cliente, notificado, fecha_creacion)
                 VALUES (?, ?, ?, "inactiva", 0, 0, "", 0, NOW())'
            );
            $st->execute([$nombre, $tipo, $tarifa]);
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare('UPDATE equipos SET numero = LPAD(?, 2, "0") WHERE id = ?')->execute([$id, $id]);
        } catch (\PDOException $e) {
            // Captura violación de clave única a nivel de base de datos
            if ($e->getCode() === '23000') {
                return ['success' => false, 'error' => "Ya existe un equipo con el nombre \"{$nombre}\" (conflicto en base de datos)."];
            }
            throw $e;
        }

        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Equipo agregado.'];
    }

    public function update(int $id, array $data): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $sets   = [];
        $params = [];

        if (isset($data['nombre_equipo'])) {
            $nombre = trim(strip_tags((string) $data['nombre_equipo']));
            if ($nombre === '') {
                return ['success' => false, 'error' => 'El nombre del equipo no puede quedar vacío tras sanitizar etiquetas HTML.'];
            }
            if (mb_strlen($nombre) > 100) {
                return ['success' => false, 'error' => 'El nombre del equipo no puede exceder los 100 caracteres.'];
            }
            // ── UNICIDAD: bloquear renombrar a un nombre ya usado ────────
            if ($this->nombreExiste($nombre, $id)) {
                return ['success' => false, 'error' => "Ya existe otro equipo registrado con el nombre \"{$nombre}\". Usa un nombre diferente."];
            }
            $sets[]   = 'nombre_equipo = ?';
            $params[] = $nombre;
        }

        if (isset($data['tipo'])) {
            $sets[]   = 'tipo = ?';
            $params[] = $data['tipo'] === 'consola' ? 'consola' : 'pc';
        }

        if (isset($data['tarifa_por_hora'])) {
            if (!is_numeric($data['tarifa_por_hora'])) {
                return ['success' => false, 'error' => 'Tarifa por hora inválida: debe ser un valor numérico.'];
            }
            $tarifa = (float) $data['tarifa_por_hora'];
            if (is_nan($tarifa) || is_infinite($tarifa) || $tarifa <= 0) {
                return ['success' => false, 'error' => 'Tarifa por hora inválida: debe ser mayor a 0.'];
            }
            $sets[]   = 'tarifa_por_hora = ?';
            $params[] = $tarifa;
        }

        if (empty($sets)) {
            return ['success' => false, 'error' => 'No hay cambios para guardar.'];
        }

        try {
            $params[] = $id;
            $this->db->prepare('UPDATE equipos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['success' => false, 'error' => 'Nombre de equipo duplicado (conflicto en base de datos).'];
            }
            throw $e;
        }

        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Equipo actualizado.'];
    }

    public function delete(int $id): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $this->db->prepare('DELETE FROM equipos WHERE id = ?')->execute([$id]);
        return ['success' => true, 'message' => 'Equipo eliminado.'];
    }

    /**
     * Registra la sesión de uso en sesiones_uso.
     * Debe llamarse siempre dentro de una transacción activa.
     */
    private function registrarSesion(int $equipoId, string $equipoNombre, string $cliente, int $segundos, float $monto): void
    {
        $cliente = trim(strip_tags($cliente));
        if ($cliente === '') {
            $cliente = 'Cliente';
        }
        if (mb_strlen($cliente) > 120) {
            $cliente = mb_substr($cliente, 0, 120);
        }

        $equipoNombre = trim(strip_tags($equipoNombre));
        if (mb_strlen($equipoNombre) > 100) {
            $equipoNombre = mb_substr($equipoNombre, 0, 100);
        }

        $st = $this->db->prepare(
            'INSERT INTO sesiones_uso (equipo_id, equipo_nombre, cliente, segundos_alquilados, monto_cobrado, tipo_registro, fecha_registro)
             VALUES (?, ?, ?, ?, ?, "alquiler_tiempo", NOW())'
        );
        $st->execute([$equipoId, $equipoNombre, $cliente, $segundos, max(0.0, round($monto, 2))]);
    }

    /**
     * Inicia el temporizador del equipo y registra la sesión en sesiones_uso.
     * Transacción atómica: si falla el INSERT de sesiones_uso, revierte el UPDATE del equipo.
     */
    public function iniciarConLimite(int $id, int $minutos, string $cliente = ''): array
    {
        $e = $this->raw($id);
        if (!$e) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        if ($minutos <= 0) {
            return ['success' => false, 'error' => 'Minutos de alquiler inválidos: debe ser mayor a 0.'];
        }
        if ($minutos > 1440) {
            return ['success' => false, 'error' => 'Minutos de alquiler excesivos: el tiempo máximo permitido es 1440 minutos (24 horas).'];
        }

        $cliente = trim(strip_tags($cliente));
        $cliente = ($cliente !== '') ? $cliente : 'Cliente';
        if (mb_strlen($cliente) > 120) {
            return ['success' => false, 'error' => 'El nombre del cliente no puede exceder los 120 caracteres.'];
        }

        $segundos = $minutos * 60;
        $tarifa   = (float) $e['tarifa_por_hora'];
        $monto    = round(($minutos / 60) * $tarifa, 2);

        // ── TRANSACCIÓN ATÓMICA: UPDATE equipos + INSERT sesiones_uso ────
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare(
                'UPDATE equipos SET estado = "activa", tiempo_transcurrido = 0, tiempo_limite = ?, cliente = ?, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
            );
            $st->execute([$segundos, $cliente, $id]);

            $this->registrarSesion($id, $e['nombre_equipo'], $cliente, $segundos, $monto);

            $this->db->commit();
        } catch (\Throwable $ex) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'error' => 'Error al iniciar tiempo: ' . $ex->getMessage()];
        }

        return ['success' => true, 'data' => $this->getById($id), 'monto' => $monto, 'message' => 'Tiempo iniciado.'];
    }

    /**
     * Agrega tiempo a un equipo ya activo o finalizado y registra la sesión.
     * Transacción atómica: si falla el INSERT de sesiones_uso, revierte el UPDATE del equipo.
     */
    public function agregarTiempo(int $id, int $minutos, ?string $cliente = null): array
    {
        $e = $this->raw($id);
        if (!$e) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        if ($minutos <= 0) {
            return ['success' => false, 'error' => 'Minutos de alquiler inválidos: debe ser mayor a 0.'];
        }
        if ($minutos > 1440) {
            return ['success' => false, 'error' => 'Minutos de alquiler excesivos: el tiempo máximo permitido es 1440 minutos (24 horas).'];
        }

        $cliente = ($cliente !== null && trim($cliente) !== '')
            ? trim(strip_tags($cliente))
            : ($e['cliente'] !== '' && $e['cliente'] !== null ? $e['cliente'] : 'Cliente');
        if (mb_strlen($cliente) > 120) {
            return ['success' => false, 'error' => 'El nombre del cliente no puede exceder los 120 caracteres.'];
        }

        $segundos     = $minutos * 60;
        $tarifa       = (float) $e['tarifa_por_hora'];
        $monto        = round(($minutos / 60) * $tarifa, 2);
        $transcurrido = $this->transcurridoActual($e);
        $limite       = (int) $e['tiempo_limite'] + $segundos;

        // ── TRANSACCIÓN ATÓMICA: UPDATE equipos + INSERT sesiones_uso ────
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE equipos SET estado = "activa", tiempo_transcurrido = ?, tiempo_limite = ?, cliente = ?, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
            )->execute([$transcurrido, $limite, $cliente, $id]);

            $this->registrarSesion($id, $e['nombre_equipo'], $cliente, $segundos, $monto);

            $this->db->commit();
        } catch (\Throwable $ex) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'error' => 'Error al agregar tiempo: ' . $ex->getMessage()];
        }

        return ['success' => true, 'data' => $this->getById($id), 'monto' => $monto, 'message' => 'Tiempo agregado.'];
    }

    public function detener(int $id): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $this->db->prepare(
            'UPDATE equipos SET estado = "inactiva", tiempo_transcurrido = 0, tiempo_limite = 0, cliente = "", fecha_inicio = NULL, notificado = 0 WHERE id = ?'
        )->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Equipo detenido.'];
    }

    public function reiniciarA0(int $id): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $this->db->prepare(
            'UPDATE equipos SET tiempo_transcurrido = 0, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
        )->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Tiempo reiniciado.'];
    }
}
