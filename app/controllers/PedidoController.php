<?php
/**
 * CyberPOS - Controlador Pedido de snacks.
 * Cero pantallas blancas: cada endpoint responde JSON controlado ante fallos.
 */

class PedidoController extends Controller
{
    public function index(): void
    {
        try {
            $this->requireAuth();
            $this->ok((new Pedido())->getAll());
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudieron obtener los pedidos.');
        }
    }

    public function store(): void
    {
        try {
            $this->requireAuth();
            $b = $this->body();

            $equipoId = null;
            if (array_key_exists('equipo_id', $b) && $b['equipo_id'] !== null && $b['equipo_id'] !== '') {
                $error = Validador::errorEnteroPositivo($b['equipo_id'], 'equipo');
                if ($error !== null) {
                    $this->fail($error, 400);
                }
                $equipoId = (int) $b['equipo_id'];
            }

            $error = Validador::errorTexto($b['equipo_nombre'] ?? 'Mostrador', 100, 'nombre del equipo', false);
            if ($error !== null) {
                $this->fail($error, 400);
            }

            $error = Validador::errorTexto($b['cliente_nombre'] ?? 'Cliente', 120, 'nombre del cliente', false);
            if ($error !== null) {
                $this->fail($error, 400);
            }

            $items = $b['items'] ?? null;
            if (!is_array($items) || count($items) === 0) {
                $this->fail('El pedido debe incluir al menos un producto.', 400);
            }

            $error = Validador::errorDecimalNoNegativo($b['total'] ?? null, 'total del pedido');
            if ($error !== null) {
                $this->fail($error, 400);
            }

            $res = (new Pedido())->crear(
                $equipoId,
                Validador::texto($b['equipo_nombre'] ?? 'Mostrador'),
                Validador::texto($b['cliente_nombre'] ?? 'Cliente'),
                $items,
                round((float) $b['total'], 2)
            );
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo procesar el pedido.');
        }
    }

    public function completar($id): void
    {
        try {
            $this->requireAdmin();
            $res = (new Pedido())->cambiarEstado($this->idValido($id), 'completado');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo completar el pedido.');
        }
    }

    public function cancelar($id): void
    {
        try {
            $this->requireAdmin();
            $res = (new Pedido())->cambiarEstado($this->idValido($id), 'cancelado');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo cancelar el pedido.');
        }
    }

    private function idValido($id): int
    {
        $error = Validador::errorEnteroPositivo($id, 'identificador del pedido');
        if ($error !== null) {
            $this->fail($error, 400);
        }
        return (int) $id;
    }
}
