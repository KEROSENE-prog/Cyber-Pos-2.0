<?php
/**
 * CyberPOS - Modelo Solicitud de tiempo.
 * Unicidad operativa por estación y transacciones atómicas al aprobar.
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
        if ($equipoId !== null && $equipoId <= 0) {
            return ['success' => false, 'error' => 'El equipo indicado no es válido.'];
        }
        if ($clienteId !== null && $clienteId <= 0) {
            return ['success' => false, 'error' => 'El cliente indicado no es válido.'];
        }

        $clienteNombre = Validador::texto($clienteNombre);
        if ($clienteNombre === '') {
            $clienteNombre = 'Cliente';
        }
        $error = Validador::errorTexto($clienteNombre, 120, 'nombre del cliente');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        $error = Validador::errorEnteroPositivo($minutos, 'minutos solicitados', 1440);
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        $error = Validador::errorDecimalNoNegativo($monto, 'monto de la solicitud');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        // ── TRANSACCIÓN: bloquear estación, verificar duplicados e insertar ──
        $this->db->beginTransaction();
        try {
            if ($equipoId !== null) {
                // Serializa las solicitudes de una misma estación.
                $stEq = $this->db->prepare('SELECT id FROM equipos WHERE id = ? FOR UPDATE');
                $stEq->execute([$equipoId]);
                if (!$stEq->fetch()) {
                    $this->db->rollBack();
                    return ['success' => false, 'error' => 'El equipo indicado no existe.'];
                }

                $stPend = $this->db->prepare(
                    'SELECT COUNT(*) FROM solicitudes_tiempo
                     WHERE equipo_id = ? AND estado IN ("pendiente", "esperando_admin", "esperando_cliente")'
                );
                $stPend->execute([$equipoId]);
                if ((int) $stPend->fetchColumn() > 0) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'error'   => 'Esta estación ya tiene una solicitud de tiempo pendiente. ' .
                                     'Espera a que sea aprobada o cancélala antes de enviar otra.',
                    ];
                }
            }

            $st = $this->db->prepare(
                'INSERT INTO solicitudes_tiempo (equipo_id, cliente_id, cliente_nombre, minutos, monto, estado, confirmado_admin, confirmado_cliente, fecha)
                 VALUES (?, ?, ?, ?, ?, "pendiente", 0, 0, NOW())'
            );
            $st->execute([$equipoId, $clienteId, $clienteNombre, $minutos, $monto]);
            $solicitudId = (int) $this->db->lastInsertId();
            $solicitud   = $this->getById($solicitudId);

            $this->db->commit();
        } catch (Throwable $ex) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log(sprintf(
                '[CyberPOS] Error al crear solicitud: %s en %s:%d',
                $ex->getMessage(),
                $ex->getFile(),
                $ex->getLine()
            ));
            return ['success' => false, 'error' => 'No se pudo enviar la solicitud. Intenta de nuevo.'];
        }

        return ['success' => true, 'data' => $solicitud, 'message' => 'Solicitud enviada.'];
    }

    /**
     * Confirma la solicitud.
     * Para el admin: transacción atómica que marca la solicitud como completada
     * y agrega el tiempo al equipo; si algo falla, revierte todo.
     *
     * @param string $quien 'admin' o 'cliente'
     */
    public function confirmar(int $id, string $quien): array
    {
        if ($quien === 'admin') {
            $this->db->beginTransaction();
            try {
                $st = $this->db->prepare('SELECT * FROM solicitudes_tiempo WHERE id = ? FOR UPDATE');
                $st->execute([$id]);
                $row = $st->fetch();
                if (!$row) {
                    $this->db->rollBack();
                    return ['success' => false, 'error' => 'Solicitud no encontrada.'];
                }

                $sol = $this->cast($row);
                if (in_array($sol['estado'], ['completada', 'cancelada'], true)) {
                    $this->db->rollBack();
                    return ['success' => false, 'error' => 'La solicitud ya fue cerrada.'];
                }

                if ($sol['equipo_id']) {
                    $resEquipo = (new Equipo())->agregarTiempo(
                        (int) $sol['equipo_id'],
                        (int) $sol['minutos'],
                        (string) $sol['cliente_nombre'],
                        true
                    );
                    if (!$resEquipo['success']) {
                        $this->db->rollBack();
                        return ['success' => false, 'error' => $resEquipo['error']];
                    }
                }

                $this->db->prepare(
                    'UPDATE solicitudes_tiempo SET confirmado_admin = 1, estado = "completada" WHERE id = ?'
                )->execute([$id]);

                $solicitud = $this->getById($id);
                $this->db->commit();
            } catch (Throwable $ex) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                error_log(sprintf(
                    '[CyberPOS] Error al aprobar solicitud: %s en %s:%d',
                    $ex->getMessage(),
                    $ex->getFile(),
                    $ex->getLine()
                ));
                return ['success' => false, 'error' => 'No se pudo aprobar la solicitud. Intenta de nuevo.'];
            }

            return ['success' => true, 'data' => $solicitud, 'message' => 'Tiempo otorgado al cliente.'];
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
