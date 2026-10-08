<?php
/**
 * CyberPOS - Controlador Pedido de snacks.
 */

class PedidoController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ok((new Pedido())->getAll());
    }

    public function store(): void
    {
        $this->requireAuth();
        $b = $this->body();
        $items = $b['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }
        $res = (new Pedido())->crear(
            isset($b['equipo_id']) ? (int) $b['equipo_id'] : null,
            trim((string) ($b['equipo_nombre'] ?? 'Mostrador')),
            trim((string) ($b['cliente_nombre'] ?? 'Cliente')),
            $items,
            (float) ($b['total'] ?? 0)
        );
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function completar($id): void
    {
        $this->requireAdmin();
        $res = (new Pedido())->cambiarEstado((int) $id, 'completado');
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function cancelar($id): void
    {
        $this->requireAdmin();
        $res = (new Pedido())->cambiarEstado((int) $id, 'cancelado');
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }
}