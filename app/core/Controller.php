<?php
/**
 * CyberPOS - Controlador base (MVC).
 * Maneja respuestas JSON, lectura del cuerpo y autenticacion por token.
 */

abstract class Controller
{
    protected ?array $authUser = null;
    protected bool $authResolved = false;

    // ------------------------------------------------------------------
    // Entrada / salida
    // ------------------------------------------------------------------

    /** Cuerpo de la peticion como array (JSON). */
    protected function body(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            $cached = [];
            return $cached;
        }
        $decoded = json_decode($raw, true);
        $cached = is_array($decoded) ? $decoded : [];
        return $cached;
    }

    protected function input(string $key, $default = null)
    {
        $body = $this->body();
        return $body[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function ok($data = null, ?string $message = null): void
    {
        $payload = ['success' => true];
        if ($data !== null)    $payload['data'] = $data;
        if ($message !== null) $payload['message'] = $message;
        $this->json($payload, 200);
    }

    protected function fail(string $message, int $code = 400): void
    {
        $this->json(['success' => false, 'error' => $message], $code);
    }

    // ------------------------------------------------------------------
    // Autenticacion
    // ------------------------------------------------------------------

    private function tokenFromRequest(): ?string
    {
        if (!empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
            return $_SERVER['HTTP_X_AUTH_TOKEN'];
        }
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return preg_replace('/^Bearer\s+/i', '', $_SERVER['HTTP_AUTHORIZATION']);
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return preg_replace('/^Bearer\s+/i', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        $token = $this->input('token');
        return $token ? (string) $token : null;
    }

    /** Usuario autenticado o null. */
    protected function user(): ?array
    {
        if ($this->authResolved) {
            return $this->authUser;
        }
        $this->authResolved = true;
        $token = $this->tokenFromRequest();
        if (!$token) {
            return null;
        }
        $model = new Usuario();
        $this->authUser = $model->findByToken($token);
        return $this->authUser;
    }

    protected function requireAuth(): array
    {
        $user = $this->user();
        if (!$user) {
            $this->fail('No autenticado. Inicia sesion nuevamente.', 401);
        }
        return $user;
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireAuth();
        if (($user['rol'] ?? '') !== 'administrador') {
            $this->fail('Acceso restringido a administradores.', 403);
        }
        return $user;
    }
}