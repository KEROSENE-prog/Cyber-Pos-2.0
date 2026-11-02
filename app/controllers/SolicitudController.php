<?php
/**
 * CyberPOS - Controlador Solicitud de tiempo.
 * Cero pantallas blancas: cada endpoint responde JSON controlado ante fallos.
 */

class SolicitudController extends Controller
{
    public function index(): void
    {
        try {
            $this->requireAuth();
            $this->ok((new Solicitud())->getAll());
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudieron obtener las solicitudes.');
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

            $clienteId = null;
            if (array_key_exists('cliente_id', $b) && $b['cliente_id'] !== null && $b['cliente_id'] !== '') {
                $error = Validador::errorEnteroPositivo($b['cliente_id'], 'cliente');
                if ($error !== null) {
                    $this->fail($error, 400);
                }
                $clienteId = (int) $b['cliente_id'];
            }

            $error = Validador::errorEnteroPositivo($b['minutos'] ?? null, 'minutos solicitados', 1440);
            if ($error !== null) {
                $this->fail($error, 400);
            }

            $error = Validador::errorDecimalNoNegativo($b['monto'] ?? null, 'monto de la solicitud');
            if ($error !== null) {
                $this->fail($error, 400);
            }

            $res = (new Solicitud())->crear(
                $equipoId,
                $clienteId,
                Validador::texto($b['cliente_nombre'] ?? 'Cliente'),
                (int) $b['minutos'],
                round((float) $b['monto'], 2)
            );
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo enviar la solicitud.');
        }
    }

    public function admin($id): void
    {
        try {
            $this->requireAdmin();
            $res = (new Solicitud())->confirmar($this->idValido($id), 'admin');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo aprobar la solicitud.');
        }
    }

    public function cliente($id): void
    {
        try {
            $this->requireAuth();
            $res = (new Solicitud())->confirmar($this->idValido($id), 'cliente');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo confirmar la solicitud.');
        }
    }

    public function cancelar($id): void
    {
        try {
            $this->requireAuth();
            $res = (new Solicitud())->cancelar($this->idValido($id));
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo cancelar la solicitud.');
        }
    }

    private function idValido($id): int
    {
        $error = Validador::errorEnteroPositivo($id, 'identificador de la solicitud');
        if ($error !== null) {
            $this->fail($error, 400);
        }
        return (int) $id;
    }
}
