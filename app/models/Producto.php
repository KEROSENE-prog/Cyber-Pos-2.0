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

    private function sanitizarUrlImagen($url): string
    {
        $url = Validador::urlImagen($url);
        return $url !== '' ? $url : self::IMAGEN_DEFAULT;
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

    /** Todos los productos (snacks + personalizados) con su origen, para el POS. */
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
        $origen = ($origen === 'personalizados') ? 'personalizados' : 'snacks';
        $error = Validador::errorTexto($data['nombre'] ?? '', 150, 'nombre del producto');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }
        $nombre = Validador::texto($data['nombre'] ?? '');

        $error = Validador::errorDecimalPositivo($data['precio'] ?? null, 'precio del producto');
        if ($error !== null) {
            return ['success' => false, 'error' => 'Precio inválido. ' . $error];
        }
        $precio = (float) $data['precio'];

        $error = Validador::errorEnteroNoNegativo($data['stock'] ?? null, 'stock', 1000000);
        if ($error !== null) {
            return ['success' => false, 'error' => 'Stock inválido. ' . $error];
        }
        $stock = (int) $data['stock'];

        $categoria = Validador::texto($data['categoria'] ?? '');
        if ($categoria === '') {
            $categoria = $origen === 'snacks' ? 'bebidas' : 'Otro';
        }
        $error = Validador::errorTexto($categoria, 60, 'categoria');
        if ($error !== null) {
            return ['success' => false, 'error' => $error];
        }

        $imagen = $this->sanitizarUrlImagen($data['url_imagen'] ?? '');

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
            $error = Validador::errorTexto($data['nombre'], 150, 'nombre del producto');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $nombre = Validador::texto($data['nombre']);
        }

        $precio = $actual['precio'];
        if (array_key_exists('precio', $data)) {
            $error = Validador::errorDecimalPositivo($data['precio'], 'precio del producto');
            if ($error !== null) {
                return ['success' => false, 'error' => 'Precio inválido. ' . $error];
            }
            $precio = (float) $data['precio'];
        }

        $stock = $actual['stock'];
        if (array_key_exists('stock', $data)) {
            $error = Validador::errorEnteroNoNegativo($data['stock'], 'stock', 1000000);
            if ($error !== null) {
                return ['success' => false, 'error' => 'Stock inválido. ' . $error];
            }
            $stock = (int) $data['stock'];
        }

        $categoria = $actual['categoria'];
        if (array_key_exists('categoria', $data) && Validador::texto($data['categoria']) !== '') {
            $error = Validador::errorTexto($data['categoria'], 60, 'categoria');
            if ($error !== null) {
                return ['success' => false, 'error' => $error];
            }
            $categoria = Validador::texto($data['categoria']);
        }

        $imagen = $actual['url_imagen'];
        if (array_key_exists('url_imagen', $data)) {
            $imagen = $this->sanitizarUrlImagen($data['url_imagen']);
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

    /**
     * Reduce stock de forma atómica y condicional.
     * Devuelve false si la cantidad es inválida o si el stock real ya no alcanza
     * (por ejemplo, por una venta concurrente). No descuenta nada en ese caso.
     */
    public function reducirStock(int $id, int $cantidad): bool
    {
        if ($cantidad <= 0) {
            return false;
        }
        $st = $this->db->prepare(
            'UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?'
        );
        $st->execute([$cantidad, $id, $cantidad]);
        return $st->rowCount() === 1;
    }
}