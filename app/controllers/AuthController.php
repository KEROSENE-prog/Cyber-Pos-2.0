<?php
/**
 * CyberPOS - Controlador de autenticacion.
 */

class AuthController extends Controller
{
    public function login(): void
    {
        $body = $this->body();
        $res  = (new Usuario())->login(
            (string) ($body['email'] ?? ''),
            (string) ($body['password'] ?? ''),
            $body['rol'] ?? null
        );
        if (!$res['success']) {
            $this->fail($res['error'], 401);
        }
        $this->json($res, 200);
    }

    public function register(): void
    {
        $res = (new Usuario())->register($this->body());
        if (!$res['success']) {
            $this->fail($res['error'], 400);
        }
        $this->json($res, 200);
    }

    public function logout(): void
    {
        $token = $_SERVER['HTTP_X_AUTH_TOKEN']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $this->input('token');
        (new Usuario())->logout($token ? preg_replace('/^Bearer\s+/i', '', $token) : null);
        $this->ok(null, 'Sesión finalizada.');
    }

    public function me(): void
    {
        $user = $this->requireAuth();
        $this->ok($user);
    }
}