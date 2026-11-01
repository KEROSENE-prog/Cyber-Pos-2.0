<?php
/**
 * CyberPOS - Modelo Solicitud de tiempo.
 * Inmunidad ante SQLi y sanitización XSS (Etapa 2).
 * Bloqueo de solicitudes pendientes duplicadas por equipo.
 * Transacción atómica en confirmación admin: UPDATE solicitud + agregarTiempo (Etapa 3).
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

    /**
     * Verifica si ya hay una solicitud pendiente para el equipo dado.
     * Previene acumulación de solicitudes pendientes duplicadas por la misma estación.
     */
    private function tieneSolicitudPendiente(?int $equipoId): bool
    {
        if ($equipoId === null) {
            return false;
        }
        $st = $this->db->prepare(
            'SELECT COUNT(*) FROM solicitudes_tiempo
             WHERE equipo_id = ? AND estado IN ("pendiente", "esperando_admin")'
        );
        $st->execute([$equipoId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function crear(?int $equipoId, ?int $clienteId, string $clienteNombre, int $minutos, float $monto): array
    {
        if ($minutos <= 0) {
            return ['success' => false, 'error' => 'Minutos de alquiler inválidos: debe ser mayor a 0.'];
        }
        if ($minutos > 1440) {
            return ['success' => false, 'error' => 'Minutos de alquiler excesivos: el tiempo máximo permitido es 1440 minutos (24 horas).'];
        }

        $clienteNombre = trim(strip_tags($clienteNombre));
        if ($clienteNombre === '') {
            $clienteNombre = 'Cliente';
        }
        if (mb_strlen($clienteNombre) > 120) {
            return ['success' => false, 'error' => 'El nombre del cliente no puede exceder los 120 caracteres.'];
        }

        if ($monto < 0 || is_nan($monto) || is_infinite($monto)) {
            return ['success' => false, 'error' => 'El monto no puede ser negativo ni inválido.'];
        }

        // ── UNICIDAD OPERATIVA: bloquear solicitud duplicada por equipo ──
        if ($this->tieneSolicitudPendiente($equipoId)) {
            return ['success' => false, 'error' => 'Esta estación ya tiene una solicitud de tiempo pendiente. Espera a que sea aprobada o cancélala antes de enviar otra.'];
        }

        $st = $this->db->prepare(
            'INSERT INTO solicitudes_tiempo (equipo_id, cliente_id, cliente_nombre, minutos, monto, estado, confirmado_admin, confirmado_cliente, fecha)
             VALUES (?, ?, ?, ?, ?, "pendiente", 0, 0, NOW())'
        );
        $st->execute([$equipoId, $clienteId, $clienteNombre, $minutos, round($monto, 2)]);
        return ['success' => true, 'data' => $this->getById((int) $this->db->lastInsertId()), 'message' => 'Solicitud enviada.'];
    }

    /**
     * Confirma la solicitud.
     * Para el admin: TRANSACCIÓN ATÓMICA — marca la solicitud como completada
     * y agrega el tiempo al equipo. Si cualquiera falla, hace ROLLBACK completo.
     *
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

        if ($quien === 'admin') {
            // ── TRANSACCIÓN ATÓMICA: marcar solicitud + agregar tiempo ───
            $this->db->beginTransaction();
            try {
                $this->db->prepare(
                    'UPDATE solicitudes_tiempo SET confirmado_admin = 1, estado = "completada" WHERE id = ?'
                )->execute([$id]);

                if ($sol['equipo_id']) {
                    $equipoModel = new Equipo();
                    $resEquipo   = $equipoModel->agregarTiempo(
                        (int) $sol['equipo_id'],
                        (int) $sol['minutos'],
                        (string) $sol['cliente_nombre']
                    );
                    if (!$resEquipo['success']) {
                        throw new \RuntimeException($resEquipo['error']);
                    }
                }

                $this->db->commit();
            } catch (\Throwable $ex) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'error' => 'Error al aprobar la solicitud: ' . $ex->getMessage()];
            }

            return ['success' => true, 'data' => $this->getById($id), 'message' => 'Tiempo otorgado al cliente.'];
        }

        // Confirmación del cliente (sin transacción compuesta)
        $this->db->prepare(
            'UPDATE solicitudes_tiempo SET confirmado_cliente = 1 WHERE id = ?'
        )->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Confirmación registrada.'];
    }

    public function cancelar(int $id): array
    {
        if (!$this->getById($id)) {
            return ['success' => false, 'error' => 'Solicitud no encontrada.'];
        }
        $this->db->prepare(
            'UPDATE solicitudes_tiempo SET estado = "cancelada" WHERE id = ?'
        )->execute([$id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Solicitud cancelada.'];
    }
}
