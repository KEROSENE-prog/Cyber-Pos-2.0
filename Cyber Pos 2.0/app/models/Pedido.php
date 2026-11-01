<?php
/**
 * CyberPOS - Modelo Pedido de snacks.
 * Inmunidad ante SQLi y sanitización XSS (Etapa 2).
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

        $st = $this->db->prepare(
            'INSERT INTO pedidos_snacks (equipo_id, equipo_nombre, cliente_nombre, items, total, estado, fecha)
             VALUES (?, ?, ?, ?, ?, "pendiente", NOW())'
        );
        $st->execute([$equipoId, $equipoNombre, $clienteNombre, json_encode($itemsSanitizados, JSON_UNESCAPED_UNICODE), round($total, 2)]);
        return ['success' => true, 'data' => $this->getById((int) $this->db->lastInsertId()), 'message' => 'Pedido enviado.'];
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
