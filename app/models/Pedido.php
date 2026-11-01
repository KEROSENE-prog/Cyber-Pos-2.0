<?php
/**
 * CyberPOS - Modelo Pedido de snacks.
 * Inmunidad ante SQLi y sanitización XSS (Etapa 2).
 * Transacción atómica con verificación de stock real antes de descontar (Etapa 3).
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

    /**
     * Crea el pedido registrándolo en pedidos_snacks y desconta stock de productos.
     *
     * Transacción atómica:
     *  1. Verifica el stock real disponible de cada ítem DENTRO de la transacción.
     *  2. Si algún ítem no tiene stock suficiente → ROLLBACK inmediato sin descontar nada.
     *  3. Si todo está bien → descuenta stock de cada ítem e inserta el pedido → COMMIT.
     */
    public function crear(?int $equipoId, string $equipoNombre, string $clienteNombre, array $items, float $total): array
    {
        if (empty($items)) {
            return ['success' => false, 'error' => 'El pedido debe contener al menos un producto.'];
        }

        $equipoNombre = trim(strip_tags($equipoNombre));
        if ($equipoNombre === '') {
            $equipoNombre = 'Mostrador';
        }
        if (mb_strlen($equipoNombre) > 100) {
            $equipoNombre = mb_substr($equipoNombre, 0, 100);
        }

        $clienteNombre = trim(strip_tags($clienteNombre));
        if ($clienteNombre === '') {
            $clienteNombre = 'Cliente';
        }
        if (mb_strlen($clienteNombre) > 120) {
            $clienteNombre = mb_substr($clienteNombre, 0, 120);
        }

        if ($total < 0 || is_nan($total) || is_infinite($total)) {
            return ['success' => false, 'error' => 'El total del pedido no puede ser negativo ni inválido.'];
        }

        $itemsSanitizados = [];
        foreach ($items as $item) {
            $nombreItem = isset($item['nombre']) ? trim(strip_tags((string) $item['nombre'])) : 'Producto';
            $cantItem   = isset($item['cantidad']) ? max(1, (int) $item['cantidad']) : 1;
            $precioItem = isset($item['precio']) && is_numeric($item['precio']) ? max(0.0, (float) $item['precio']) : 0.0;
            $itemsSanitizados[] = [
                'id'       => isset($item['id']) ? (int) $item['id'] : 0,
                'nombre'   => mb_substr($nombreItem, 0, 150),
                'precio'   => round($precioItem, 2),
                'cantidad' => $cantItem,
            ];
        }

        // ── TRANSACCIÓN ATÓMICA ──────────────────────────────────────────
        $this->db->beginTransaction();
        try {
            // 1. Verificar stock real de cada producto dentro de la transacción
            $stStock = $this->db->prepare('SELECT id, nombre, stock FROM productos WHERE id = ?');
            foreach ($itemsSanitizados as $item) {
                if ($item['id'] <= 0) {
                    continue; // ítems sin ID registrado (e.g. snacks externos) no descontan stock
                }
                $stStock->execute([$item['id']]);
                $prod = $stStock->fetch();
                if ($prod && (int) $prod['stock'] < $item['cantidad']) {
                    throw new \RuntimeException(
                        "Stock insuficiente para \"{$prod['nombre']}\": disponible {$prod['stock']}, solicitado {$item['cantidad']}."
                    );
                }
            }

            // 2. Descontar stock
            $stDesc = $this->db->prepare(
                'UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );
            foreach ($itemsSanitizados as $item) {
                if ($item['id'] <= 0) {
                    continue;
                }
                $stDesc->execute([$item['cantidad'], $item['id'], $item['cantidad']]);
                if ($stDesc->rowCount() === 0) {
                    // La fila no se actualizó: stock insuficiente en el momento exacto (race condition)
                    throw new \RuntimeException(
                        "Stock insuficiente para el producto #{$item['id']} al momento de descontar (posible concurrencia)."
                    );
                }
            }

            // 3. Insertar el pedido
            $st = $this->db->prepare(
                'INSERT INTO pedidos_snacks (equipo_id, equipo_nombre, cliente_nombre, items, total, estado, fecha)
                 VALUES (?, ?, ?, ?, ?, "pendiente", NOW())'
            );
            $st->execute([
                $equipoId,
                $equipoNombre,
                $clienteNombre,
                json_encode($itemsSanitizados, JSON_UNESCAPED_UNICODE),
                round($total, 2),
            ]);
            $pedidoId = (int) $this->db->lastInsertId();

            $this->db->commit();
        } catch (\Throwable $ex) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'error' => $ex->getMessage()];
        }

        return ['success' => true, 'data' => $this->getById($pedidoId), 'message' => 'Pedido enviado.'];
    }

    public function cambiarEstado(int $id, string $estado): array
    {
        if (!$this->getById($id)) {
            return ['success' => false, 'error' => 'Pedido no encontrado.'];
        }
        $estado = in_array($estado, ['pendiente', 'completado', 'cancelado'], true) ? $estado : 'pendiente';
        $st = $this->db->prepare('UPDATE pedidos_snacks SET estado = ? WHERE id = ?');
        $st->execute([$estado, $id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Pedido actualizado.'];
    }
}
