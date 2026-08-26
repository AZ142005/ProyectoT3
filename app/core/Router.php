<?php
namespace App\Core;

/**
 * Enrutador declarativo orientado a objetos con soporte para
 * parámetros dinámicos ({id}) y middleware de autorización.
 */
class Router {
    private array $routes = [];

    /**
     * Registra una ruta GET.
     */
    public function get(string $pattern, array $handler, array $middlewares = []): self {
        return $this->add('GET', $pattern, $handler, $middlewares);
    }

    /**
     * Registra una ruta POST.
     */
    public function post(string $pattern, array $handler, array $middlewares = []): self {
        return $this->add('POST', $pattern, $handler, $middlewares);
    }

    /**
     * Registra una ruta que responde a cualquier método HTTP (GET/POST).
     */
    public function any(string $pattern, array $handler, array $middlewares = []): self {
        $this->add('GET', $pattern, $handler, $middlewares);
        $this->add('POST', $pattern, $handler, $middlewares);
        return $this;
    }

    /**
     * Añade una ruta al registro interno.
     */
    private function add(string $method, string $pattern, array $handler, array $middlewares = []): self {
        $this->routes[$method][$pattern] = [
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
        return $this;
    }

    /**
     * Despacha la petición HTTP entrante comparando la URI contra el registro de rutas.
     *
     * @param string $method Método HTTP ('GET', 'POST')
     * @param string $uri Ruta solicitada (ej. '/admin/dashboard', '/pagos/detalle/5')
     */
    public function dispatch(string $method, string $uri): void {
        $routesForMethod = $this->routes[$method] ?? [];

        foreach ($routesForMethod as $pattern => $routeInfo) {
            // Convertir placeholders tipo {id} o {slug} a expresiones regulares nombradas (?P<id>[^/]+)
            $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#i';

            if (preg_match($regex, $uri, $matches)) {
                // Ejecutar middlewares de autorización asignados a la ruta
                foreach ($routeInfo['middlewares'] as $mw) {
                    if ($mw === 'auth') {
                        Auth::requireLogin();
                    } elseif ($mw === UserRole::ADMIN || $mw === 'admin') {
                        Auth::requireRole(UserRole::ADMIN);
                    } elseif ($mw === UserRole::RESIDENTE || $mw === 'residente') {
                        Auth::requireRole(UserRole::RESIDENTE);
                    }
                }

                // Filtrar parámetros nombrados extraídos de la URL
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                [$controllerClass, $actionMethod] = $routeInfo['handler'];
                $controller = new $controllerClass();
                $controller->{$actionMethod}(...$params);
                return;
            }
        }

        // Si ninguna ruta coincide, responder con error 404
        http_response_code(404);
        $notFoundView = VIEWS_PATH . '/errors/404.php';
        if (file_exists($notFoundView)) {
            require_once $notFoundView;
        } else {
            echo "<h2>Error 404 - Página no encontrada</h2>";
        }
        exit;
    }
}
