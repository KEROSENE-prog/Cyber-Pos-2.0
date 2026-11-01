<?php
/**
 * CyberPOS - Controlador Solicitud de tiempo.
 * Cero pantallas blancas / cero Error 500 crudo: try/catch global en todos los endpoints (Etapa 3).
 */

class SolicitudController extends Controller
{
    public function index(): void
    {
        try {
            $this->requireAuth();
            $this->ok((new Solicitud())->getAll());
        } catch (\Throwable $e) {
            $this->fail('Error al obtener solicitudes: ' . $e->getMessage(), 500);
        }
    }

    public function store(): void
    {
        try {
            $this->requireAuth();
            $b = $this->body();

            // Validar minutos
            if (!isset($b['minutos']) || !is_numeric($b['minutos'])) {
                $this->fail('Minutos de alquiler inválidos: debe ser un número entero.', 400);
            }
            $minFloat = (float) $b['minutos'];
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

            // Validar cliente_nombre
            $clienteNombre = trim((string) ($b['cliente_nombre'] ?? 'Cliente'));
            if ($clienteNombre === '') {
                $clienteNombre = 'Cliente';
            }
            if (mb_strlen($clienteNombre) > 120) {
                $this->fail('El nombre del cliente no puede exceder los 120 caracteres.', 400);
            }

            // Validar monto
            $montoRaw = $b['monto'] ?? 0;
            if (!is_numeric($montoRaw)) {
                $this->fail('Monto inválido: debe ser un valor numérico.', 400);
            }
            $monto = (float) $montoRaw;
            if ($monto < 0 || is_nan($monto) || is_infinite($monto)) {
                $this->fail('El monto no puede ser negativo ni inválido.', 400);
            }

            $equipoId  = isset($b['equipo_id'])  && is_numeric($b['equipo_id'])  ? (int) $b['equipo_id']  : null;
            $clienteId = isset($b['cliente_id']) && is_numeric($b['cliente_id']) ? (int) $b['cliente_id'] : null;

            $res = (new Solicitud())->crear(
                $equipoId,
                $clienteId,
                $clienteNombre,
                $min,
                $monto
            );
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al crear solicitud: ' . $e->getMessage(), 500);
        }
    }

    public function admin($id): void
    {
        try {
            $this->requireAdmin();
            if (!is_numeric($id) || (int) $id <= 0) {
                $this->fail('Identificador de solicitud inválido.', 400);
            }
            $res = (new Solicitud())->confirmar((int) $id, 'admin');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al aprobar solicitud: ' . $e->getMessage(), 500);
        }
    }

    public function cliente($id): void
    {
        try {
            $this->requireAuth();
            if (!is_numeric($id) || (int) $id <= 0) {
                $this->fail('Identificador de solicitud inválido.', 400);
            }
            $res = (new Solicitud())->confirmar((int) $id, 'cliente');
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al confirmar solicitud: ' . $e->getMessage(), 500);
        }
    }

    public function cancelar($id): void
    {
        try {
            $this->requireAuth();
            if (!is_numeric($id) || (int) $id <= 0) {
                $this->fail('Identificador de solicitud inválido.', 400);
            }
            $res = (new Solicitud())->cancelar((int) $id);
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al cancelar solicitud: ' . $e->getMessage(), 500);
        }
    }
}
