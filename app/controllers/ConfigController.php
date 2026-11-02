<?php
/**
 * CyberPOS - Controlador Configuracion.
 * Cero pantallas blancas: cada endpoint responde JSON controlado ante fallos.
 */

class ConfigController extends Controller
{
    public function show(): void
    {
        try {
            $this->ok((new Configuracion())->get());
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo obtener la configuración.');
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
        } catch (Throwable $e) {
            $this->serverError($e, 'No se pudo guardar la configuración.');
        }
    }
}
