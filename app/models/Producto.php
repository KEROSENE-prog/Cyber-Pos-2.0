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
        $nombre = isset($data['nombre']) ? trim((string) $data['nombre']) : '';
        if ($nombre === '') {
            return ['success' => false, 'error' => 'El nombre del producto es obligatorio y no puede componerse solo de espacios.'];
        }
        if (mb_strlen($nombre) > 150) {
            return ['success' => false, 'error' => 'El nombre del producto no puede exceder los 150 caracteres.'];
        }

        if (!isset($data['precio']) || !is_numeric($data['precio'])) {
            return ['success' => false, 'error' => 'Precio inválido: debe ser un valor numérico.'];
        }
        $precio = (float) $data['precio'];
        if (is_nan($precio) || is_infinite($precio) || $precio <= 0) {
            return ['success' => false, 'error' => 'Precio inválido: debe ser mayor a 0.'];
        }

        if (!isset($data['stock']) || !is_numeric($data['stock'])) {
            return ['success' => false, 'error' => 'Stock inválido: debe ser un número entero.'];
        }
        $stockFloat = (float) $data['stock'];
        if (floor($stockFloat) != $stockFloat) {
            return ['success' => false, 'error' => 'El stock debe ser un número entero sin decimales.'];
        }
        $stock = (int) $stockFloat;
        if ($stock < 0) {
            return ['success' => false, 'error' => 'El stock no puede ser negativo.'];
        }

        $categoria = isset($data['categoria']) ? trim((string) $data['categoria']) : '';
        if ($categoria === '') {
            $categoria = ($origen === 'snacks') ? 'bebidas' : 'Otro';
        }
        if (mb_strlen($categoria) > 60) {
            return ['success' => false, 'error' => 'La categoría no puede exceder los 60 caracteres.'];
        }

        $origen = ($origen === 'personalizados') ? 'personalizados' : 'snacks';
        $imagen = isset($data['url_imagen']) && trim((string) $data['url_imagen']) !== ''
            ? trim((string) $data['url_imagen'])
            : self::IMAGEN_DEFAULT;

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

        $nombre = $actual['nombre'];
        if (array_key_exists('nombre', $data)) {
            $nombre = trim((string) $data['nombre']);
            if ($nombre === '') {
                return ['success' => false, 'error' => 'El nombre del producto es obligatorio y no puede componerse solo de espacios.'];
            }
            if (mb_strlen($nombre) > 150) {
                return ['success' => false, 'error' => 'El nombre del producto no puede exceder los 150 caracteres.'];
            }
        }

        $precio = $actual['precio'];
        if (array_key_exists('precio', $data)) {
            if (!is_numeric($data['precio'])) {
                return ['success' => false, 'error' => 'Precio inválido: debe ser un valor numérico.'];
            }
            $precio = (float) $data['precio'];
            if (is_nan($precio) || is_infinite($precio) || $precio <= 0) {
                return ['success' => false, 'error' => 'Precio inválido: debe ser mayor a 0.'];
            }
        }

        $stock = $actual['stock'];
        if (array_key_exists('stock', $data)) {
            if (!is_numeric($data['stock'])) {
                return ['success' => false, 'error' => 'Stock inválido: debe ser un número entero.'];
            }
            $stockFloat = (float) $data['stock'];
            if (floor($stockFloat) != $stockFloat) {
                return ['success' => false, 'error' => 'El stock debe ser un número entero sin decimales.'];
            }
            $stock = (int) $stockFloat;
            if ($stock < 0) {
                return ['success' => false, 'error' => 'El stock no puede ser negativo.'];
            }
        }

        $categoria = $actual['categoria'];
        if (array_key_exists('categoria', $data)) {
            $categoria = trim((string) $data['categoria']);
            if ($categoria === '') {
                $categoria = $actual['categoria'];
            }
            if (mb_strlen($categoria) > 60) {
                return ['success' => false, 'error' => 'La categoría no puede exceder los 60 caracteres.'];
            }
        }

        $imagen = $actual['url_imagen'];
        if (array_key_exists('url_imagen', $data)) {
            $imagen = trim((string) $data['url_imagen']);
            if ($imagen === '') {
                $imagen = self::IMAGEN_DEFAULT;
            }
        }

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

    /** Reduce stock. Devuelve false si no hay suficiente o si la cantidad es invalida. */
    public function reducirStock(int $id, int $cantidad): bool
    {
        if ($cantidad <= 0) {
            return false;
        }
        $p = $this->getById($id);
        if (!$p || $p['stock'] < $cantidad) {
            return false;
        }
        $this->db->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')->execute([$cantidad, $id]);
        return true;
    }
}
