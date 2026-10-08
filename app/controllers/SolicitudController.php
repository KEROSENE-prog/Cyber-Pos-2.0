<?php
/**
 * CyberPOS - Controlador Solicitud de tiempo.
 */

class SolicitudController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ok((new Solicitud())->getAll());
    }

    public function store(): void
    {
        $this->requireAuth();
        $b = $this->body();
        $res = (new Solicitud())->crear(
            isset($b['equipo_id']) ? (int) $b['equipo_id'] : null,
            isset($b['cliente_id']) ? (int) $b['cliente_id'] : null,
            trim((string) ($b['cliente_nombre'] ?? 'Cliente')),
            (int) ($b['minutos'] ?? 0),
            (float) ($b['monto'] ?? 0)
        );
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function admin($id): void
    {
        $this->requireAdmin();
        $res = (new Solicitud())->confirmar((int) $id, 'admin');
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function cliente($id): void
    {
        $this->requireAuth();
        $res = (new Solicitud())->confirmar((int) $id, 'cliente');
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function cancelar($id): void
    {
        $this->requireAuth();
        $res = (new Solicitud())->cancelar((int) $id);
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }
}