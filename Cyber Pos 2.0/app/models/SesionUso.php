<?php
// Modelo SesionUso — historial de alquileres y ventas directas.

class SesionUso extends Model
{
    private function cast(array $s): array
    {
        $s['id']                  = (int) $s['id'];
        $s['equipo_id']           = $s['equipo_id'] !== null ? (int) $s['equipo_id'] : null;
        $s['segundos_alquilados'] = (int) $s['segundos_alquilados'];
        $s['monto_cobrado']       = (float) $s['monto_cobrado'];
        if (array_key_exists('detalle', $s)) {
            $items = $s['detalle'] ? json_decode($s['detalle'], true) : [];
            $s['items'] = is_array($items) ? $items : [];
            unset($s['detalle']);
        }
        return $s;
    }

    public function getAll(): array
    {
        $rows = $this->db->query('SELECT * FROM sesiones_uso ORDER BY id DESC')->fetchAll();
        return array_map([$this, 'cast'], $rows);
    }

    public function contar(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM sesiones_uso')->fetchColumn();
    }

    public function ingresosHoy(): float
    {
        $st = $this->db->prepare('SELECT COALESCE(SUM(monto_cobrado), 0) FROM sesiones_uso WHERE DATE(fecha_registro) = CURDATE()');
        $st->execute();
        return (float) $st->fetchColumn();
    }

    public function registrarVenta(string $cliente, float $monto, array $items = []): int
    {
        $cliente = trim(strip_tags($cliente));
        if ($cliente === '') {
            $cliente = 'Mostrador';
        }
        if (mb_strlen($cliente) > 120) {
            $cliente = mb_substr($cliente, 0, 120);
        }
        $monto = max(0.0, round($monto, 2));

        $detalle = $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null;
        $st = $this->db->prepare(
            'INSERT INTO sesiones_uso (equipo_id, equipo_nombre, cliente, segundos_alquilados, monto_cobrado, tipo_registro, detalle, fecha_registro)
             VALUES (NULL, "Venta Directa", ?, 0, ?, "venta_snack", ?, NOW())'
        );
        $st->execute([$cliente, $monto, $detalle]);
        return (int) $this->db->lastInsertId();
    }

    public function historialVentas(int $limit = 200, ?string $desde = null, ?string $hasta = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT s.*, e.tipo AS equipo_tipo
                FROM sesiones_uso s
                LEFT JOIN equipos e ON e.id = s.equipo_id';
        $where = [];
        $params = [];
        if ($desde) {
            $where[] = 'DATE(s.fecha_registro) >= ?';
            $params[] = $desde;
        }
        if ($hasta) {
            $where[] = 'DATE(s.fecha_registro) <= ?';
            $params[] = $hasta;
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.id DESC LIMIT ' . (int) $limit;
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'cast'], $st->fetchAll());
    }
}
