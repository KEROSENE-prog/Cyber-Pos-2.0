<?php
/**
 * CyberPOS - Controlador Venta (POS). Registra una venta y descuenta stock.
 */

class VentaController extends Controller
{
    private const IVA = 0.16;

    public function index(): void
    {
        $this->requireAdmin();
        $desde = $this->input('desde');
        $hasta = $this->input('hasta');
        if ($desde !== null && $desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $desde)) {
            $this->fail('Formato de fecha "desde" inválido (debe ser YYYY-MM-DD).', 400);
        }
        if ($hasta !== null && $hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $hasta)) {
            $this->fail('Formato de fecha "hasta" inválido (debe ser YYYY-MM-DD).', 400);
        }
        $this->ok((new SesionUso())->historialVentas(200, $desde ? (string) $desde : null, $hasta ? (string) $hasta : null));
    }

    public function store(): void
    {
        $user  = $this->requireAdmin();
        $body  = $this->body();
        $items = $body['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $this->fail('El carrito está vacío.', 400);
        }

        $clienteRaw = isset($body['cliente']) ? trim((string) $body['cliente']) : '';
        $cliente = $clienteRaw !== '' ? $clienteRaw : ($user['nombre_completo'] ?? 'Mostrador');
        if (mb_strlen($cliente) > 120) {
            $this->fail('El nombre del cliente no puede exceder los 120 caracteres.', 400);
        }

        $producto = new Producto();
        $subtotal = 0.0;
        $detalle  = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                $this->fail('Formato de artículo inválido en el carrito.', 400);
            }
            if (!isset($item['id']) || !is_numeric($item['id']) || (int) $item['id'] <= 0) {
                $this->fail('Identificador de producto inválido en el carrito.', 400);
            }
            $id = (int) $item['id'];

            if (!isset($item['cantidad']) || !is_numeric($item['cantidad'])) {
                $this->fail('Cantidad inválida en el carrito: debe ser un número entero.', 400);
            }
            $cantFloat = (float) $item['cantidad'];
            if (floor($cantFloat) != $cantFloat || (int) $cantFloat <= 0) {
                $this->fail('Cantidad de compra inválida: debe ser un número entero mayor a 0 sin decimales.', 400);
            }
            $cantidad = (int) $cantFloat;

            $prod = $producto->getById($id);
            if (!$prod) {
                $this->fail("El producto con ID #{$id} no existe.", 400);
            }

            if ($prod['stock'] < $cantidad) {
                $this->fail("Stock insuficiente para '{$prod['nombre']}': disponible {$prod['stock']}, solicitado {$cantidad}.", 400);
            }

            $precioUnitario = (float) $prod['precio'];
            $subtotal += $precioUnitario * $cantidad;
            $detalle[] = [
                'id'       => $id,
                'nombre'   => $prod['nombre'],
                'cantidad' => $cantidad,
                'precio'   => $precioUnitario,
            ];
        }

        $this->dbBegin();
        try {
            foreach ($detalle as $item) {
                $reducido = $producto->reducirStock($item['id'], $item['cantidad']);
                if (!$reducido) {
                    throw new Exception("Stock insuficiente para el producto '{$item['nombre']}'.");
                }
            }
            $impuesto = round($subtotal * self::IVA, 2);
            $total    = round($subtotal + $impuesto, 2);

            $ventaId = (new SesionUso())->registrarVenta($cliente, $total, $detalle);
            $this->dbCommit();

            $this->json([
                'success'  => true,
                'message'  => 'Venta registrada con éxito.',
                'venta_id' => $ventaId,
                'subtotal' => round($subtotal, 2),
                'impuesto' => $impuesto,
                'total'    => $total,
            ], 200);
        } catch (Throwable $e) {
            $this->dbRollback();
            $this->fail('Error al registrar la venta: ' . $e->getMessage(), 400);
        }
    }

    private function dbBegin(): void
    {
        Database::get()->beginTransaction();
    }

    private function dbCommit(): void
    {
        Database::get()->commit();
    }

    private function dbRollback(): void
    {
        if (Database::get()->inTransaction()) {
            Database::get()->rollBack();
        }
    }
}
