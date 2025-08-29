<?php

declare(strict_types=1);

namespace Utils;

/**
 * Clase para manejar las rutas de la API
 */
class Router
{
    private $routes = [];
    private $basePrefix = '';

    /**
     * Agrega una ruta al router
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $path Ruta URI
     * @param string $controller Nombre del controlador
     * @param string $action Nombre del método en el controlador
     * @return void
     */
    public function add($method, $path, $controller, $action)
    {
        $path = $this->basePrefix . $path;
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    /**
     * Agrega un grupo de rutas con un prefijo común
     *
     * @param string $prefix Prefijo de ruta
     * @param callable $callback Función que define las rutas del grupo
     * @param array|null $middleware Middleware para todo el grupo
     * @return void
     */
    public function addGroup($prefix, callable $callback, $middleware = null)
    {
        $previousPrefix = $this->basePrefix;
        $this->basePrefix .= $prefix;

        $callback($this);

        if ($middleware) {
            // Aplicar middleware a todas las rutas del grupo
            $this->applyMiddlewareToGroup($prefix, $middleware);
        }

        $this->basePrefix = $previousPrefix;
    }

    /**
     * Aplica middleware a un grupo de rutas
     *
     * @param string $prefix Prefijo del grupo
     * @param array $middleware Middleware a aplicar
     * @return void
     */
    private function applyMiddlewareToGroup($prefix, $middleware)
    {
        foreach ($this->routes as &$route) {
            if (strpos($route['path'], $prefix) === 0) {
                $route['middleware'] = $middleware;
            }
        }
    }

    /**
     * Despacha la solicitud actual a la ruta correspondiente
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function dispatch($request)
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        $matchedRoute = null;
        $params = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertRouteToRegex($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                $matchedRoute = $route;

                // Extraer parí¡metros de la URL
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                break;
            }
        }

        if (!$matchedRoute) {
            // No se encontró una ruta que coincida
            ResponseHelper::error('Ruta no encontrada', null, 404);
            return;
        }

        // Aplicar middleware si existe
        if (isset($matchedRoute['middleware'])) {
            list($middlewareClass, $middlewareMethod) = $matchedRoute['middleware'];

            // Comprobar si el middleware impide continuar
            if (!call_user_func([$middlewareClass, $middlewareMethod], $request)) {
                return;
            }
        }

        // Preparar el controlador
        $controllerClass = 'Controllers\\' . $matchedRoute['controller'];

        if (!class_exists($controllerClass)) {
            ResponseHelper::error('Controlador no encontrado', null, 500);
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $matchedRoute['action'])) {
            ResponseHelper::error('Acción no encontrada', null, 500);
            return;
        }

        // Ejecutar la acción del controlador
        try {
            call_user_func_array([$controller, $matchedRoute['action']], [$request, $params]);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), null, 500);
        }
    }

    /**
     * Convierte una ruta con parí¡metros a expresión regular
     *
     * @param string $route Ruta con parí¡metros (ej: /api/users/:id)
     * @return string Expresión regular para coincidir con la ruta
     */
    private function convertRouteToRegex($route)
    {
        // Escapar caracteres especiales
        $route = preg_quote($route, '/');

        // Convertir parí¡metros :name a grupos de captura con nombre (?<name>[^/]+)
        $route = preg_replace('/\\\:([a-zA-Z0-9_]+)/', '(?<$1>[^/]+)', $route);

        // Aí±adir delimitadores y asegurar que coincide exactamente
        return '/^' . $route . '$/';
    }
}
