<?php
/**
 * CyberPOS - Modelo Equipo (PC / Consola).
 * El tiempo transcurrido se calcula en el servidor a partir de fecha_inicio.
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
        $nombre = trim($data['nombre_equipo'] ?? '');
        if ($nombre === '') {
            return ['success' => false, 'error' => 'El nombre del equipo es obligatorio.'];
        }
        $tipo = ($data['tipo'] ?? 'pc') === 'consola' ? 'consola' : 'pc';
        $tarifa = isset($data['tarifa_por_hora'])
            ? (float) $data['tarifa_por_hora']
            : ($tipo === 'consola' ? 2.00 : 1.50);
        if ($tarifa <= 0) {
            $tarifa = $tipo === 'consola' ? 2.00 : 1.50;
        }

        $st = $this->db->prepare(
            'INSERT INTO equipos (nombre_equipo, tipo, tarifa_por_hora, estado, tiempo_transcurrido, tiempo_limite, cliente, notificado, fecha_creacion)
             VALUES (?, ?, ?, "inactiva", 0, 0, "", 0, NOW())'
        );
        $st->execute([$nombre, $tipo, $tarifa]);
        $id = (int) $this->db->lastInsertId();
        $this->db->prepare('UPDATE equipos SET numero = LPAD(?, 2, "0") WHERE id = ?')->execute([$id, $id]);

        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Equipo agregado.'];
    }

    public function update(int $id, array $data): array
    {
        if (!$this->raw($id)) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        $sets = [];
        $params = [];
        if (isset($data['nombre_equipo']) && trim((string) $data['nombre_equipo']) !== '') {
            $sets[] = 'nombre_equipo = ?';
            $params[] = trim((string) $data['nombre_equipo']);
        }
        if (isset($data['tipo'])) {
            $sets[] = 'tipo = ?';
            $params[] = $data['tipo'] === 'consola' ? 'consola' : 'pc';
        }
        if (isset($data['tarifa_por_hora'])) {
            $sets[] = 'tarifa_por_hora = ?';
            $params[] = (float) $data['tarifa_por_hora'];
        }
        if (empty($sets)) {
            return ['success' => false, 'error' => 'No hay cambios para guardar.'];
        }
        $params[] = $id;
        $this->db->prepare('UPDATE equipos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
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

    private function registrarSesion(int $equipoId, string $equipoNombre, string $cliente, int $segundos, float $monto): void
    {
        $st = $this->db->prepare(
            'INSERT INTO sesiones_uso (equipo_id, equipo_nombre, cliente, segundos_alquilados, monto_cobrado, tipo_registro, fecha_registro)
             VALUES (?, ?, ?, ?, ?, "alquiler_tiempo", NOW())'
        );
        $st->execute([$equipoId, $equipoNombre, $cliente, $segundos, $monto]);
    }

    public function iniciarConLimite(int $id, int $minutos, string $cliente = ''): array
    {
        $e = $this->raw($id);
        if (!$e) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        if ($minutos <= 0) {
            return ['success' => false, 'error' => 'Ingresa un tiempo válido.'];
        }
        $segundos = $minutos * 60;
        $tarifa   = (float) $e['tarifa_por_hora'];
        $monto    = round(($minutos / 60) * $tarifa, 2);
        $cliente  = $cliente !== '' ? $cliente : 'Cliente';

        $st = $this->db->prepare(
            'UPDATE equipos SET estado = "activa", tiempo_transcurrido = 0, tiempo_limite = ?, cliente = ?, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
        );
        $st->execute([$segundos, $cliente, $id]);
        $this->registrarSesion($id, $e['nombre_equipo'], $cliente, $segundos, $monto);

        return ['success' => true, 'data' => $this->getById($id), 'monto' => $monto, 'message' => 'Tiempo iniciado.'];
    }

    public function agregarTiempo(int $id, int $minutos, ?string $cliente = null): array
    {
        $e = $this->raw($id);
        if (!$e) {
            return ['success' => false, 'error' => 'Equipo no encontrado.'];
        }
        if ($minutos <= 0) {
            return ['success' => false, 'error' => 'Ingresa un tiempo válido.'];
        }
        $segundos = $minutos * 60;
        $tarifa   = (float) $e['tarifa_por_hora'];
        $monto    = round(($minutos / 60) * $tarifa, 2);
        $cliente  = ($cliente !== null && trim($cliente) !== '')
            ? trim($cliente)
            : ($e['cliente'] !== '' && $e['cliente'] !== null ? $e['cliente'] : 'Cliente');

        $transcurrido = $this->transcurridoActual($e);
        $limite       = (int) $e['tiempo_limite'] + $segundos;

        $this->db->prepare(
            'UPDATE equipos SET estado = "activa", tiempo_transcurrido = ?, tiempo_limite = ?, cliente = ?, fecha_inicio = NOW(), notificado = 0 WHERE id = ?'
        )->execute([$transcurrido, $limite, $cliente, $id]);
        $this->registrarSesion($id, $e['nombre_equipo'], $cliente, $segundos, $monto);

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