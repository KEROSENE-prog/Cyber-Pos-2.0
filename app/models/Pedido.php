<?php
/**
 * CyberPOS - Modelo Pedido de snacks.
 */

class Pedido extends Model
{
    private function cast(array $p): array
    {
        $p['id']         = (int) $p['id'];
        $p['equipo_id']  = $p['equipo_id'] !== null ? (int) $p['equipo_id'] : null;
        $p['total']      = (float) $p['total'];
        $p['items']      = $p['items'] ? json_decode($p['items'], true) : [];
        if (!is_array($p['items'])) {
            $p['items'] = [];
        }
        return $p;
    }

    public function getAll(): array
    {
        $rows = $this->db->query('SELECT * FROM pedidos_snacks ORDER BY id DESC')->fetchAll();
        return array_map([$this, 'cast'], $rows);
    }

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM pedidos_snacks WHERE id = ?');
        $st->execute([$id]);
        $p = $st->fetch();
        return $p ? $this->cast($p) : null;
    }

    public function crear(?int $equipoId, string $equipoNombre, string $clienteNombre, array $items, float $total): array
    {
        if ($equipoId !== null && $equipoId <= 0) {
            return ['success' => false, 'error' => 'El equipo indicado no es válido.'];
        }

        $equipoNombre = Validador::texto($equipoNombre);
        if ($equipoNombre === '') {
            $equipoNombre = 'Mostrador';
        }
        $error = Validador::errorTexto($equipoNombre, 100, 'nombre del equipo');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        $clienteNombre = Validador::texto($clienteNombre);
        if ($clienteNombre === '') {
            $clienteNombre = 'Cliente';
        }
        $error = Validador::errorTexto($clienteNombre, 120, 'nombre del cliente');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        if (count($items) === 0) {
            return ['success' => false, 'error' => 'El pedido debe incluir al menos un producto.'];
        }

        $itemsLimpios = [];
        foreach ($items as $indice => $item) {
            if (!is_array($item)) {
                return ['success' => false, 'error' => 'Uno de los productos del pedido no es válido.'];
            }
            $numero = $indice + 1;

            $error = Validador::errorEnteroPositivo($item['id'] ?? null, "identificador del producto #{$numero}");
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }

            $error = Validador::errorEnteroPositivo($item['cantidad'] ?? null, "cantidad del producto #{$numero}", 10000);
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }

            $error = Validador::errorDecimalNoNegativo($item['precio'] ?? null, "precio del producto #{$numero}");
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }

            $nombreItem = Validador::texto($item['nombre'] ?? '');
            if (mb_strlen($nombreItem, 'UTF-8') > 150) {
                return ['success' => false, 'error' => "El nombre del producto #{$numero} no puede exceder los 150 caracteres."];
            }

            $origenItem = Validador::texto($item['origen'] ?? '');
            if (!in_array($origenItem, ['snacks', 'personalizados'], true)) {
                $origenItem = 'snacks';
            }

            $itemsLimpios[] = [
                'id'       => (int) $item['id'],
                'origen'   => $origenItem,
                'nombre'   => $nombreItem,
                'precio'   => round((float) $item['precio'], 2),
                'cantidad' => (int) $item['cantidad'],
            ];
        }

        $error = Validador::errorDecimalNoNegativo($total, 'total del pedido');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        $requerido = [];
        foreach ($itemsLimpios as $item) {
            $pid = (int) $item['id'];
            if ($pid <= 0) {
                continue;
            }
            $requerido[$pid] = ($requerido[$pid] ?? 0) + (int) $item['cantidad'];
        }

        // ── TRANSACCIÓN ATÓMICA: stock real + descuento + registro del pedido ──
        $this->db->beginTransaction();
        try {
            $stStock = $this->db->prepare(
                'SELECT id, nombre, stock FROM productos WHERE id = ? FOR UPDATE'
            );
            foreach ($requerido as $pid => $cantidad) {
                $stStock->execute([$pid]);
                $prod = $stStock->fetch();
                if (!$prod) {
                    $this->db->rollBack();
                    return ['success' => false, 'error' => "El producto #{$pid} ya no está disponible."];
                }
                if ((int) $prod['stock'] < $cantidad) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'error'   => "Stock insuficiente para \"{$prod['nombre']}\": disponible {$prod['stock']}, " .
                                     "solicitado {$cantidad}. Ningún producto fue descontado.",
                    ];
                }
            }

            $stDesc = $this->db->prepare(
                'UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );
            foreach ($requerido as $pid => $cantidad) {
                $stDesc->execute([$cantidad, $pid, $cantidad]);
                if ($stDesc->rowCount() !== 1) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'error'   => "Stock insuficiente para el producto #{$pid} al momento de descontar. " .
                                     'Ningún producto fue descontado.',
                    ];
                }
            }

            $st = $this->db->prepare(
                'INSERT INTO pedidos_snacks (equipo_id, equipo_nombre, cliente_nombre, items, total, estado, fecha)
                 VALUES (?, ?, ?, ?, ?, "pendiente", NOW())'
            );
            $st->execute([
                $equipoId,
                $equipoNombre,
                $clienteNombre,
                json_encode($itemsLimpios, JSON_UNESCAPED_UNICODE),
                round($total, 2),
            ]);
            $pedidoId = (int) $this->db->lastInsertId();
            $pedido   = $this->getById($pedidoId);

            $this->db->commit();
        } catch (Throwable $ex) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log(sprintf(
                '[CyberPOS] Error al crear pedido: %s en %s:%d',
                $ex->getMessage(),
                $ex->getFile(),
                $ex->getLine()
            ));
            return ['success' => false, 'error' => 'No se pudo procesar el pedido. Intenta de nuevo.'];
        }

        return ['success' => true, 'data' => $pedido, 'message' => 'Pedido enviado.'];
    }

    public function cambiarEstado(int $id, string $estado): array
    {
        if (!$this->getById($id)) {
            return ['success' => false, 'error' => 'Pedido no encontrado.'];
        }
        $st = $this->db->prepare('UPDATE pedidos_snacks SET estado = ? WHERE id = ?');
        $st->execute([$estado, $id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Pedido actualizado.'];
    }
}