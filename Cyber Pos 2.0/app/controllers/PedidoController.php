<?php
/**
 * CyberPOS - Controlador Pedido de snacks.
 * Cero pantallas blancas / cero Error 500 crudo: try/catch global en todos los endpoints (Etapa 3).
 */

class PedidoController extends Controller
{
    public function index(): void
    {
        try {
            $this->requireAuth();
            $this->ok((new Pedido())->getAll());
        } catch (\Throwable $e) {
            $this->fail('Error al obtener pedidos: ' . $e->getMessage(), 500);
        }
    }

    public function store(): void
    {
        try {
            $this->requireAuth();
            $b     = $this->body();
            $items = $b['items'] ?? [];
            if (!is_array($items) || count($items) === 0) {
                $this->fail('El pedido debe incluir al menos un producto.', 400);
            }

            $itemsValidados = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    $this->fail('Formato de producto inválido en el pedido.', 400);
                }
                if (!isset($item['id']) || !is_numeric($item['id']) || (int) $item['id'] <= 0) {
                    $this->fail('Identificador de producto inválido en el pedido.', 400);
                }

                if (!isset($item['cantidad']) || !is_numeric($item['cantidad'])) {
                    $this->fail('Cantidad inválida en el pedido: debe ser un número entero.', 400);
                }
                $cantFloat = (float) $item['cantidad'];
                if (floor($cantFloat) != $cantFloat || (int) $cantFloat <= 0) {
                    $this->fail('Cantidad inválida en el pedido: debe ser un número entero mayor a 0 sin decimales.', 400);
                }

                $nombre = isset($item['nombre']) ? trim((string) $item['nombre']) : 'Producto';
                if (mb_strlen($nombre) > 150) {
                    $nombre = mb_substr($nombre, 0, 150);
                }

                $precio = isset($item['precio']) && is_numeric($item['precio']) ? max(0.0, (float) $item['precio']) : 0.0;

                $itemsValidados[] = [
                    'id'       => (int) $item['id'],
                    'nombre'   => $nombre,
                    'precio'   => round($precio, 2),
                    'cantidad' => (int) $cantFloat,
                ];
            }

            $equipoNombre = trim((string) ($b['equipo_nombre'] ?? 'Mostrador'));
            if (mb_strlen($equipoNombre) > 100) {
                $this->fail('El nombre del equipo no puede exceder los 100 caracteres.', 400);
            }

            $clienteNombre = trim((string) ($b['cliente_nombre'] ?? 'Cliente'));
            if (mb_strlen($clienteNombre) > 120) {
                $this->fail('El nombre del cliente no puede exceder los 120 caracteres.', 400);
            }

            $totalRaw = $b['total'] ?? 0;
            if (!is_numeric($totalRaw)) {
                $this->fail('Total de pedido inválido: debe ser un valor numérico.', 400);
            }
            $total = (float) $totalRaw;
            if ($total < 0 || is_nan($total) || is_infinite($total)) {
                $this->fail('El total no puede ser negativo ni inválido.', 400);
            }

            $equipoId = isset($b['equipo_id']) && is_numeric($b['equipo_id']) ? (int) $b['equipo_id'] : null;

            $res = (new Pedido())->crear(
                $equipoId,
                $equipoNombre,
                $clienteNombre,
                $itemsValidados,
                $total
            );
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al registrar pedido: ' . $e->getMessage(), 500);
        }
    }

    public function completar($id): void
    {
        try {
            $this->requireAdmin();
            if (!is_numeric($id) || (int) $id <= 0) {
                $this->fail('Identificador de pedido inválido.', 400);
            }
            $res = (new Pedido())->cambiarEstado((int) $id, 'completado');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al completar pedido: ' . $e->getMessage(), 500);
        }
    }

    public function cancelar($id): void
    {
        try {
            $this->requireAdmin();
            if (!is_numeric($id) || (int) $id <= 0) {
                $this->fail('Identificador de pedido inválido.', 400);
            }
            $res = (new Pedido())->cambiarEstado((int) $id, 'cancelado');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al cancelar pedido: ' . $e->getMessage(), 500);
        }
    }
}
