<?php
/**
 * CyberPOS - Controlador Usuario (perfil).
 */

class UsuarioController extends Controller
{
    public function update($id): void
    {
        $user = $this->requireAuth();
        $id   = (int) $id;
        if ((int) $user['id'] !== $id && $user['rol'] !== 'administrador') {
            $this->fail('No puedes actualizar este perfil.', 403);
        }
        $res = (new Usuario())->updateProfile($id, $this->body());
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }
}