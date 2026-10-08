<?php
/**
 * CyberPOS - Modelo Producto.
 * Une el catalogo estandar ("snacks") y el personalizado mediante la columna origen.
 */

class Producto extends Model
{
    private const IMAGEN_DEFAULT = 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?w=500&q=80';

    private function cast(array $p): array
    {
        $p['id']     = (int) $p['id'];
        $p['precio'] = (float) $p['precio'];
        $p['stock']  = (int) $p['stock'];
        return $p;
    }

    public function getAll(string $categoria = 'all', string $busqueda = '', string $origen = 'snacks'): array
    {
        $sql    = 'SELECT * FROM productos WHERE origen = ?';
        $params = [$origen];
        if ($categoria !== '' && strtolower($categoria) !== 'all') {
            $sql .= ' AND LOWER(categoria) = ?';
            $params[] = strtolower($categoria);
        }
        if ($busqueda !== '') {
            $sql .= ' AND LOWER(nombre) LIKE ?';
            $params[] = '%' . strtolower($busqueda) . '%';
        }
        $sql .= ' ORDER BY id';
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'cast'], $st->fetchAll());
    }

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM productos WHERE id = ?');
        $st->execute([$id]);
        $p = $st->fetch();
        return $p ? $this->cast($p) : null;
    }

    /** Todos los productos (snacks + personalizados) con su origen, para el POS. */
    public function getAllUnificados(string $busqueda = ''): array
    {
        $sql    = 'SELECT * FROM productos';
        $params = [];
        if ($busqueda !== '') {
            $sql .= ' WHERE LOWER(nombre) LIKE ?';
            $params[] = '%' . strtolower($busqueda) . '%';
        }
        $sql .= ' ORDER BY origen, id';
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map(function ($p) {
            $p = $this->cast($p);
            $p['_origen'] = $p['origen'];
            return $p;
        }, $st->fetchAll());
    }

    public function create(array $data, string $origen): array
    {
        $nombre = trim($data['nombre'] ?? '');
        $precio = isset($data['precio']) ? (float) $data['precio'] : 0;
        $stock  = isset($data['stock']) ? (int) $data['stock'] : 0;
        if ($nombre === '') {
            return ['success' => false, 'error' => 'El nombre es obligatorio.'];
        }
        if ($precio <= 0) {
            return ['success' => false, 'error' => 'El precio debe ser mayor a 0.'];
        }
        if ($stock < 0) {
            return ['success' => false, 'error' => 'El stock no puede ser negativo.'];
        }
        $categoria = trim($data['categoria'] ?? '');
        if ($categoria === '') {
            $categoria = $origen === 'snacks' ? 'bebidas' : 'Otro';
        }
        $imagen = trim($data['url_imagen'] ?? '') !== '' ? trim($data['url_imagen']) : self::IMAGEN_DEFAULT;

        $st = $this->db->prepare(
            'INSERT INTO productos (nombre, precio, stock, url_imagen, categoria, origen, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([$nombre, $precio, $stock, $imagen, $categoria, $origen]);
        return ['success' => true, 'data' => $this->getById((int) $this->db->lastInsertId()), 'message' => 'Producto agregado.'];
    }

    public function update(int $id, array $data): array
    {
        $actual = $this->getById($id);
        if (!$actual) {
            return ['success' => false, 'error' => 'Producto no encontrado.'];
        }
        $nombre = array_key_exists('nombre', $data) ? trim((string) $data['nombre']) : $actual['nombre'];
        if ($nombre === '') {
            return ['success' => false, 'error' => 'El nombre es obligatorio.'];
        }
        $precio = array_key_exists('precio', $data) ? (float) $data['precio'] : $actual['precio'];
        if ($precio <= 0) {
            return ['success' => false, 'error' => 'El precio debe ser mayor a 0.'];
        }
        $stock = array_key_exists('stock', $data) ? (int) $data['stock'] : $actual['stock'];
        if ($stock < 0) {
            return ['success' => false, 'error' => 'El stock no puede ser negativo.'];
        }
        $categoria = array_key_exists('categoria', $data) && trim((string) $data['categoria']) !== ''
            ? trim((string) $data['categoria'])
            : $actual['categoria'];
        $imagen = array_key_exists('url_imagen', $data) && trim((string) $data['url_imagen']) !== ''
            ? trim((string) $data['url_imagen'])
            : $actual['url_imagen'];

        $st = $this->db->prepare(
            'UPDATE productos SET nombre = ?, precio = ?, stock = ?, url_imagen = ?, categoria = ? WHERE id = ?'
        );
        $st->execute([$nombre, $precio, $stock, $imagen, $categoria, $id]);
        return ['success' => true, 'data' => $this->getById($id), 'message' => 'Producto actualizado.'];
    }

    public function delete(int $id): array
    {
        if (!$this->getById($id)) {
            return ['success' => false, 'error' => 'Producto no encontrado.'];
        }
        $this->db->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
        return ['success' => true, 'message' => 'Producto eliminado.'];
    }

    /** Reduce stock. Devuelve false si no hay suficiente. */
    public function reducirStock(int $id, int $cantidad): bool
    {
        $p = $this->getById($id);
        if (!$p || $p['stock'] < $cantidad) {
            return false;
        }
        $this->db->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')->execute([$cantidad, $id]);
        return true;
    }
}