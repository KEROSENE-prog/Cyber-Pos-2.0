<?php
/**
 * CyberPOS - Controlador Configuracion.
 * Cero pantallas blancas / cero Error 500 crudo: try/catch global en todos los endpoints (Etapa 3).
 */

class ConfigController extends Controller
{
    public function show(): void
    {
        try {
            $this->ok((new Configuracion())->get());
        } catch (\Throwable $e) {
            $this->fail('Error al obtener configuración: ' . $e->getMessage(), 500);
        }
    }

    public function update(): void
    {
        try {
            $this->requireAdmin();
            $res = (new Configuracion())->update($this->body());
            if (!$res['success']) {
                $this->fail($res['error'], 400);
            }
            $this->json($res, 200);
        } catch (\Throwable $e) {
            $this->fail('Error al actualizar configuración: ' . $e->getMessage(), 500);
        }
    }
}
