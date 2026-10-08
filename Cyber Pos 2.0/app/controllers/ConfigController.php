<?php
/**
 * CyberPOS - Controlador Configuracion.
 */

class ConfigController extends Controller
{
    public function show(): void
    {
        $this->ok((new Configuracion())->get());
    }

    public function update(): void
    {
        $this->requireAdmin();
        $res = (new Configuracion())->update($this->body());
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }
}