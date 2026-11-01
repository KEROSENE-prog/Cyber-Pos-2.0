<?php
// Modelo Producto — catálogo snacks y personalizados (columna 'origen').

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

    private function sanitizarUrlImagen(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return self::IMAGEN_DEFAULT;
        }
        // bloquea javascript:, data:text/html y similares
        if (!preg_match('/^(https?:\/\/|\/|\.\/|[a-zA-Z0-9_\-\.\/]+\.(jpg|jpeg|png|gif|webp|svg))/i', $url)) {
            return self::IMAGEN_DEFAULT;
        }
        return filter_var($url, FILTER_SANITIZE_URL) ?: self::IMAGEN_DEFAULT;
    }

    public function getAll(string $categoria = 'all', string $busqueda = '', string $origen = 'snacks'): array
    {
        $origen = ($origen === 'personalizados') ? 'personalizados' : 'snacks';
        $sql    = 'SELECT * FROM productos WHERE origen = ?';
        $params = [$origen];

        if ($categoria !== '' && strtolower($categoria) !== 'all') {
            $sql .= ' AND LOWER(categoria) = ?';
            $params[] = strtolower(trim($categoria));
        }

        if ($busqueda !== '') {
            $sql .= ' AND LOWER(nombre) LIKE ?';
            $params[] = '%' . strtolower(trim($busqueda)) . '%';
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

    // Todos los productos (snacks + personalizados) con su origen, para el POS.
    public function getAllUnificados(string $busqueda = ''): array
    {
        $sql    = 'SELECT * FROM productos';
        $params = [];

        if ($busqueda !== '') {
            $sql .= ' WHERE LOWER(nombre) LIKE ?';
            $params[] = '%' . strtolower(trim($busqueda)) . '%';
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
        $nombreRaw = isset($data['nombre']) ? (string) $data['nombre'] : '';
        $nombre    = trim(strip_tags($nombreRaw));

        if ($nombre === '') {
            return ['success' => false, 'error' => 'El nombre del producto es obligatorio y no puede consistir únicamente en etiquetas HTML o espacios.'];
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

        $categoriaRaw = isset($data['categoria']) ? (string) $data['categoria'] : '';
        $categoria    = trim(strip_tags($categoriaRaw));
        if ($categoria === '') {
            $categoria = ($origen === 'snacks') ? 'bebidas' : 'Otro';
        }
        if (mb_strlen($categoria) > 60) {
            return ['success' => false, 'error' => 'La categoría no puede exceder los 60 caracteres.'];
        }

        $origen = ($origen === 'personalizados') ? 'personalizados' : 'snacks';
        $imagen = $this->sanitizarUrlImagen((string) ($data['url_imagen'] ?? ''));

        $st = $this->db->prepare(
            'INSERT INTO productos (nombre, precio, stock, url_imagen, categoria, origen, fecha_creacion)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([$nombre, $precio, $stock, $imagen, $categoria, $origen]);

        return [
            'success' => true,
            'data'    => $this->getById((int) $this->db->lastInsertId()),
            'message' => 'Producto agregado.',
        ];
    }

    public function update(int $id, array $data): array
    {
        $actual = $this->getById($id);
        if (!$actual) {
            return ['success' => false, 'error' => 'Producto no encontrado.'];
        }

        $nombre = $actual['nombre'];
        if (array_key_exists('nombre', $data)) {
            $nombre = trim(strip_tags((string) $data['nombre']));
            if ($nombre === '') {
                return ['success' => false, 'error' => 'El nombre del producto no puede quedar vacío tras sanitizar etiquetas HTML.'];
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
            $categoria = trim(strip_tags((string) $data['categoria']));
            if ($categoria === '') {
                $categoria = $actual['categoria'];
            }
            if (mb_strlen($categoria) > 60) {
                return ['success' => false, 'error' => 'La categoría no puede exceder los 60 caracteres.'];
            }
        }

        $imagen = $actual['url_imagen'];
        if (array_key_exists('url_imagen', $data)) {
            $imagen = $this->sanitizarUrlImagen((string) $data['url_imagen']);
        }

        $st = $this->db->prepare(
            'UPDATE productos SET nombre = ?, precio = ?, stock = ?, url_imagen = ?, categoria = ? WHERE id = ?'
        );
        $st->execute([$nombre, $precio, $stock, $imagen, $categoria, $id]);

        return [
            'success' => true,
            'data'    => $this->getById($id),
            'message' => 'Producto actualizado.',
        ];
    }

    public function delete(int $id): array
    {
        if (!$this->getById($id)) {
            return ['success' => false, 'error' => 'Producto no encontrado.'];
        }
        $this->db->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
        return ['success' => true, 'message' => 'Producto eliminado.'];
    }

    // Reduce stock. Devuelve false si no hay suficiente o si la cantidad es inválida.
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
