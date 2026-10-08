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
        $st = $this->db->prepare(
            'INSERT INTO pedidos_snacks (equipo_id, equipo_nombre, cliente_nombre, items, total, estado, fecha)
             VALUES (?, ?, ?, ?, ?, "pendiente", NOW())'
        );
        $st->execute([$equipoId, $equipoNombre, $clienteNombre, json_encode($items, JSON_UNESCAPED_UNICODE), $total]);
        return ['success' => true, 'data' => $this->getById((int) $this->db->lastInsertId()), 'message' => 'Pedido enviado.'];
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