<?php
/**
 * CyberPOS - Controlador Equipo.
 * Cero pantallas blancas: cada endpoint responde JSON controlado ante fallos.
 */

class EquipoController extends Controller
{
    public function index(): void
    {
        try {
            $this->requireAuth();
            $this->ok((new Equipo())->getAll());
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudieron obtener los equipos.');
        }
    }

    public function show($id): void
    {
        try {
            $this->requireAuth();
            $eq = (new Equipo())->getById((int) $id);
            if (!$eq) {
                $this->fail('Equipo no encontrado.', 404);
            }
            $this->ok($eq);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo obtener el equipo.');
        }
    }

    public function store(): void
    {
        try {
            $this->requireAdmin();
            $res = (new Equipo())->create($this->body());
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo crear el equipo.');
        }
    }

    public function update($id): void
    {
        try {
            $this->requireAdmin();
            $id  = $this->idValido($id);
            $res = (new Equipo())->update($id, $this->body());
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo actualizar el equipo.');
        }
    }

    public function destroy($id): void
    {
        try {
            $this->requireAdmin();
            $id  = $this->idValido($id);
            $res = (new Equipo())->delete($id);
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo eliminar el equipo.');
        }
    }

    public function tiempo($id): void
    {
        try {
            $this->requireAuth();
            $id     = $this->idValido($id);
            $body   = $this->body();
            $accion = (string) ($body['accion'] ?? 'iniciar');
            if (!in_array($accion, ['iniciar', 'agregar', 'apagar', 'reiniciar', 'reiniciar0'], true)) {
                $this->fail('Acción de tiempo no válida.', 400);
            }

            $min = 0;
            if ($accion === 'iniciar' || $accion === 'agregar') {
                if (array_key_exists('minutos', $body)) {
                    $error = Validador::errorEnteroPositivo($body['minutos'], 'minutos de alquiler', 1440);
                    if ($error !== null) {
                        $this->fail($error, 400);
                    }
                    $min = (int) $body['minutos'];
                } elseif (array_key_exists('segundos', $body)) {
                    $error = Validador::errorEnteroPositivo($body['segundos'], 'segundos de alquiler', 86400);
                    if ($error !== null) {
                        $this->fail($error, 400);
                    }
                    $min = max(1, (int) round(((int) $body['segundos']) / 60));
                } else {
                    $this->fail('Debes indicar los minutos de alquiler.', 400);
                }
            }

            $cliente = Validador::texto($body['cliente'] ?? '');
            $error   = Validador::errorTexto($cliente, 120, 'nombre del cliente', false);
            if ($error !== null) {
                $this->fail($error, 400);
            }

            $model = new Equipo();

            if ($accion === 'apagar' || $accion === 'reiniciar') {
                $res = $model->detener($id);
            } elseif ($accion === 'reiniciar0') {
                $res = $model->reiniciarA0($id);
            } elseif ($accion === 'agregar') {
                $eq = $model->getById($id);
                if ($eq && in_array($eq['estado'], ['activa', 'finalizada'], true)) {
                    $res = $model->agregarTiempo($id, $min, $cliente !== '' ? $cliente : null);
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
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo completar la operación de tiempo.');
        }
    }

    private function idValido($id): int
    {
        $error = Validador::errorEnteroPositivo($id, 'identificador del equipo');
        if ($error !== null) {
            $this->fail($error, 400);
        }
        return (int) $id;
    }
}
