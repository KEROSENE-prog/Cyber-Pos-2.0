<?php
/**
 * CyberPOS - Controlador Venta (POS). Registra una venta y descuenta stock.
 */

class VentaController extends Controller
{
    private const IVA = 0.16;

    public function index(): void
    {
        try {
            $this->requireAdmin();
            $this->ok((new SesionUso())->historialVentas());
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo obtener el historial de ventas.');
        }
    }

    public function store(): void
    {
        try {
            $user  = $this->requireAdmin();
            $body  = $this->body();
            $items = $body['items'] ?? null;
            if (!is_array($items) || count($items) === 0) {
                $this->fail('El carrito está vacío.', 400);
            }

            $producto = new Producto();
            $subtotal = 0.0;
            $detalle  = [];

            foreach ($items as $indice => $item) {
                $numero = $indice + 1;
                if (!is_array($item)) {
                    $this->fail("El producto #{$numero} del carrito no es válido.", 400);
                }

                $error = Validador::errorEnteroPositivo($item['id'] ?? null, "identificador del producto #{$numero}");
                if ($error !== null) {
                    $this->fail($error, 400);
                }
                $id = (int) $item['id'];

                $error = Validador::errorEnteroPositivo($item['cantidad'] ?? null, "cantidad del producto #{$numero}", 10000);
                if ($error !== null) {
                    $this->fail($error, 400);
                }
                $cantidad = (int) $item['cantidad'];

                $prod = $producto->getById($id);
                if (!$prod) {
                    $this->fail("El producto #{$id} no existe.", 400);
                }

                $subtotal += $prod['precio'] * $cantidad;
                $detalle[] = [
                    'id'       => $id,
                    'nombre'   => Validador::texto($prod['nombre']),
                    'cantidad' => $cantidad,
                    'precio'   => (float) $prod['precio'],
                ];
            }

            $cliente = Validador::texto($body['cliente'] ?? ($user['nombre_completo'] ?? 'Mostrador'));
            if ($cliente === '') {
                $cliente = 'Mostrador';
            }
            $error = Validador::errorTexto($cliente, 120, 'nombre del cliente');
            if ($error !== null) {
                $this->fail($error, 400);
            }

            $this->dbBegin();
            try {
                $requerido = [];
                foreach ($detalle as $linea) {
                    $pid = $linea['id'];
                    $requerido[$pid] = ($requerido[$pid] ?? 0) + $linea['cantidad'];
                }

                $stStock = Database::get()->prepare(
                    'SELECT id, nombre, stock FROM productos WHERE id = ? FOR UPDATE'
                );
                foreach ($requerido as $pid => $cantidadRequerida) {
                    $stStock->execute([$pid]);
                    $prodActual = $stStock->fetch();
                    if (!$prodActual) {
                        $this->dbRollback();
                        $this->fail("El producto #{$pid} ya no está disponible.", 400);
                    }
                    if ((int) $prodActual['stock'] < $cantidadRequerida) {
                        $this->dbRollback();
                        $this->fail(
                            "Stock insuficiente para \"{$prodActual['nombre']}\": disponible {$prodActual['stock']}, " .
                            "solicitado {$cantidadRequerida}. Ningún producto fue descontado.",
                            400
                        );
                    }
                }

                foreach ($detalle as $linea) {
                    if (!$producto->reducirStock($linea['id'], $linea['cantidad'])) {
                        $this->dbRollback();
                        $this->fail(
                            "Stock insuficiente para \"{$linea['nombre']}\" al momento de descontar. Ningún producto fue descontado.",
                            400
                        );
                    }
                }

                $impuesto = round($subtotal * self::IVA, 2);
                $total    = round($subtotal + $impuesto, 2);

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
                $this->serverError($e, 'No se pudo registrar la venta.');
            }
        } catch (Throwable $e) {
            $this->dbRollback();
            $this->serverError($e, 'No se pudo registrar la venta.');
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
        try {
            $pdo = Database::get();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $e) {
            // Si la conexión ya no está disponible, el error original se reporta arriba.
        }
    }
}