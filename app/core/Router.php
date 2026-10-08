<?php
/**
 * CyberPOS - Enrutador simple basado en expresiones regulares.
 * Cada ruta: [metodo, patron, "Controlador@accion"].
 */

class Router
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        foreach ($this->routes as $route) {
            [$routeMethod, $pattern, $handler] = $route;

            if (strtoupper($routeMethod) !== strtoupper($method)) {
                continue;
            }
            $regex = '#^' . $pattern . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                [$controllerName, $action] = explode('@', $handler);
                $controllerClass = $controllerName;

                if (!class_exists($controllerClass)) {
                    throw new RuntimeException("Controlador no encontrado: {$controllerClass}");
                }
                $controller = new $controllerClass();
                if (!method_exists($controller, $action)) {
                    throw new RuntimeException("Accion no encontrada: {$controllerClass}@{$action}");
                }
                $controller->$action(...$matches);
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => "Ruta no implementada: {$method} {$path}",
        ]);
    }
}