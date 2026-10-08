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
        $this->ok((new SesionUso())->historialVentas());
    }

    public function store(): void
    {
        $user  = $this->requireAdmin();
        $body  = $this->body();
        $items = $body['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $this->fail('El carrito está vacío.', 400);
        }

        $producto = new Producto();
        $subtotal = 0.0;
        $detalle  = [];
        foreach ($items as $item) {
            $id       = (int) ($item['id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);
            if ($id <= 0 || $cantidad <= 0) {
                continue;
            }
            $prod = $producto->getById($id);
            if ($prod) {
                $subtotal += $prod['precio'] * $cantidad;
                $detalle[] = [
                    'id'       => $id,
                    'nombre'   => $prod['nombre'],
                    'cantidad' => $cantidad,
                    'precio'   => (float) $prod['precio'],
                ];
            }
        }

        $this->dbBegin();
        try {
            foreach ($items as $item) {
                $producto->reducirStock((int) $item['id'], (int) $item['cantidad']);
            }
            $impuesto = round($subtotal * self::IVA, 2);
            $total    = round($subtotal + $impuesto, 2);

            $cliente = $body['cliente'] ?? ($user['nombre_completo'] ?? 'Mostrador');
            $ventaId = (new SesionUso())->registrarVenta($cliente, $total, $detalle);
            $this->dbCommit();

            $this->json([
                'success'  => true,
                'message'  => 'Venta registrada.',
                'venta_id' => $ventaId,
                'subtotal' => round($subtotal, 2),
                'impuesto' => $impuesto,
                'total'    => $total,
            ], 200);
        } catch (Throwable $e) {
            $this->dbRollback();
            $this->fail('Error al registrar la venta: ' . $e->getMessage(), 500);
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