<?php
/**
 * CyberPOS - Front Controller de la API.
 * Todas las peticiones del frontend entran por aqui: api/index.php?r=<ruta>
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // los errores se devuelven como JSON

header('Content-Type: application/json; charset=utf-8');

// Zona horaria unica para toda la app (evita desfases PHP vs MySQL).
date_default_timezone_set('America/Caracas');

// -----------------------------------------------------------------------------
// Autoload minimo (MVC)
// -----------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $base = dirname(__DIR__) . '/app/';
    $rutas = [
        $base . 'core/' . $class . '.php',
        $base . 'models/' . $class . '.php',
        $base . 'controllers/' . $class . '.php',
    ];
    foreach ($rutas as $archivo) {
        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});

// -----------------------------------------------------------------------------
// Despacho
// -----------------------------------------------------------------------------
try {
    $routes = require dirname(__DIR__) . '/app/routes.php';
    $router = new Router($routes);

    $path   = $_GET['r'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $router->dispatch($method, (string) $path);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno del servidor: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}