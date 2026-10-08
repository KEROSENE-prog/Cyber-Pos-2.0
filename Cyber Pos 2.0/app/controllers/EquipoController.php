<?php
/**
 * CyberPOS - Controlador Equipo.
 */

class EquipoController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ok((new Equipo())->getAll());
    }

    public function show($id): void
    {
        $this->requireAuth();
        $eq = (new Equipo())->getById((int) $id);
        if (!$eq) {
            $this->fail('Equipo no encontrado.', 404);
        }
        $this->ok($eq);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $res = (new Equipo())->create($this->body());
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function update($id): void
    {
        $this->requireAdmin();
        $res = (new Equipo())->update((int) $id, $this->body());
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function destroy($id): void
    {
        $this->requireAdmin();
        $res = (new Equipo())->delete((int) $id);
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function tiempo($id): void
    {
        $this->requireAuth();
        $id     = (int) $id;
        $body   = $this->body();
        $accion = $body['accion'] ?? 'iniciar';
        $min    = (int) ($body['minutos'] ?? 0);
        if ($min <= 0 && isset($body['segundos'])) {
            $min = (int) round(((int) $body['segundos']) / 60);
        }
        $cliente = trim((string) ($body['cliente'] ?? ''));

        $model = new Equipo();

        if ($accion === 'apagar' || $accion === 'reiniciar') {
            $res = $model->detener($id);
        } elseif ($accion === 'reiniciar0') {
            $res = $model->reiniciarA0($id);
        } elseif ($accion === 'agregar') {
            $eq = $model->getById($id);
            if ($eq && in_array($eq['estado'], ['activa', 'finalizada'], true)) {
                $res = $model->agregarTiempo($id, $min);
            } else {
                $res = $model->iniciarConLimite($id, $min, $cliente);
            }
        } else {
            $res = $model->iniciarConLimite($id, $min, $cliente);
        }

        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }
}