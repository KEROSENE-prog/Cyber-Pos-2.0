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
        if (!is_numeric($id) || (int) $id <= 0) {
            $this->fail('Identificador de equipo inválido.', 400);
        }
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
        if (!is_numeric($id) || (int) $id <= 0) {
            $this->fail('Identificador de equipo inválido.', 400);
        }
        $res = (new Equipo())->update((int) $id, $this->body());
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function destroy($id): void
    {
        $this->requireAdmin();
        if (!is_numeric($id) || (int) $id <= 0) {
            $this->fail('Identificador de equipo inválido.', 400);
        }
        $res = (new Equipo())->delete((int) $id);
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function tiempo($id): void
    {
        $this->requireAuth();
        if (!is_numeric($id) || (int) $id <= 0) {
            $this->fail('Identificador de equipo inválido.', 400);
        }
        $id     = (int) $id;
        $body   = $this->body();
        $accion = isset($body['accion']) ? trim((string) $body['accion']) : 'iniciar';

        if (in_array($accion, ['iniciar', 'agregar'], true)) {
            $minRaw = $body['minutos'] ?? null;
            if ($minRaw === null && isset($body['segundos']) && is_numeric($body['segundos'])) {
                $minRaw = round(((float) $body['segundos']) / 60);
            }

            if ($minRaw === null || !is_numeric($minRaw)) {
                $this->fail('Minutos de alquiler inválidos: debe ser un número entero.', 400);
            }
            $minFloat = (float) $minRaw;
            if (floor($minFloat) != $minFloat) {
                $this->fail('Los minutos de alquiler deben ser un número entero sin decimales.', 400);
            }
            $min = (int) $minFloat;
            if ($min <= 0) {
                $this->fail('Minutos de alquiler inválidos: debe ser mayor a 0.', 400);
            }
            if ($min > 1440) {
                $this->fail('Minutos de alquiler excesivos: el tiempo máximo permitido es 1440 minutos (24 horas).', 400);
            }
        } else {
            $min = 0;
        }

        $cliente = isset($body['cliente']) ? trim((string) $body['cliente']) : '';
        if (mb_strlen($cliente) > 120) {
            $this->fail('El nombre del cliente no puede exceder los 120 caracteres.', 400);
        }

        $model = new Equipo();

        if ($accion === 'apagar' || $accion === 'reiniciar') {
            $res = $model->detener($id);
        } elseif ($accion === 'reiniciar0') {
            $res = $model->reiniciarA0($id);
        } elseif ($accion === 'agregar') {
            $eq = $model->getById($id);
            if ($eq && in_array($eq['estado'], ['activa', 'finalizada'], true)) {
                $res = $model->agregarTiempo($id, $min, $cliente);
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
