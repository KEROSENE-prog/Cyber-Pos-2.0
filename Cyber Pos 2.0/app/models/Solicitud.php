<?php
/**
 * CyberPOS - Modelo Solicitud de tiempo.
 */

class Solicitud extends Model
{
    private function cast(array $s): array
    {
        $s['id']                 = (int) $s['id'];
        $s['equipo_id']          = $s['equipo_id'] !== null ? (int) $s['equipo_id'] : null;
        $s['cliente_id']         = $s['cliente_id'] !== null ? (int) $s['cliente_id'] : null;
        $s['minutos']            = (int) $s['minutos'];
        $s['monto']              = (float) $s['monto'];
        $s['confirmado_admin']   = (bool) $s['confirmado_admin'];
        $s['confirmado_cliente'] = (bool) $s['confirmado_cliente'];
        return $s;
    }

    public function getAll(): array
    {
        $rows = $this->db->query('SELECT * FROM solicitudes_tiempo ORDER BY id DESC')->fetchAll();
        return array_map([$this, 'cast'], $rows);
    }

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM solicitudes_tiempo WHERE id = ?');
        $st->execute([$id]);
        $s = $st->fetch();
        return $s ? $this->cast($s) : null;
    }

    public function crear(?int $equipoId, ?int $clienteId, string $clienteNombre, int $minutos, float $monto): array
    {
        $st = $this->db->prepare(
            'INSERT INTO solicitudes_tiempo (equipo_id, cliente_id, cliente_nombre, minutos, monto, estado, confirmado_admin, confirmado_cliente, fecha)
             VALUES (?, ?, ?, ?, ?, "pendiente", 0, 0, NOW())'
        );
        $st->execute([$equipoId, $clienteId, $clienteNombre, $minutos, $monto]);
        return ['success' => true, 'data' => $this->getById((int) $this->db->lastInsertId()), 'message' => 'Solicitud enviada.'];
    }

    /**
     * @param string $quien 'admin' o 'cliente'
     */
    public function confirmar(int $id, string $quien): array
    {
        $sol = $this->getById($id);
        if (!$sol) {
            return ['success' => false, 'error' => 'Solicitud no encontrada.'];
        }
        if (in_array($sol['estado'], ['completada', 'cancelada'], true)) {
            return ['success' => false, 'error' => 'La solicitud ya fue cerrada.'];
        }

        // La aprobación del administrador (caja) es definitiva: se suma el
        // tiempo al equipo y la solicitud queda completada de inmediato.
        if ($quien === 'admin') {
            $this->db->prepare('UPDATE solicitudes_tiempo SET confirmado_admin = 1, estado = "completada" WHERE id = ?')->execute([$id]);
            if ($sol['equipo_id']) {
                (new Equipo())->agregarTiempo((int) $sol['equipo_id'], (int) $sol['minutos'], (string) $sol['cliente_nombre']);
            }
            return ['success' => true, 'data' => $this->getById($id), 'message' => 'Tiempo otorgado al cliente.'];
        }

        // Confirmación del cliente: informativa (el admin ya cerró la solicitud).
        $this->db->prepare('UPDATE solicitudes_tiempo SET confirmado_cliente = 1 WHERE id = ?')->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Confirmación registrada.'];
    }

    public function cancelar(int $id): array
    {
        if (!$this->getById($id)) {
            return ['success' => false, 'error' => 'Solicitud no encontrada.'];
        }
        $this->db->prepare('UPDATE solicitudes_tiempo SET estado = "cancelada" WHERE id = ?')->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Solicitud cancelada.'];
    }
}