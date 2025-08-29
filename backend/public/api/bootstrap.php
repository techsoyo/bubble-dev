<?php

declare(strict_types=1);

if (!defined('API_BOOTSTRAPPED')) {
    define('API_BOOTSTRAPPED', true);

    // Cargar el bootstrap principal
    $mainBootstrap = __DIR__ . '/../../config/bootstrap.php';
    if (!is_file($mainBootstrap)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'config/bootstrap.php no encontrado']);
        exit;
    }

    require_once $mainBootstrap;

    // Ejecutar CORS lo antes posible (antes de CSRF/auth).
    // Primero intentamos con autoload (class_exists con autoload habilitado).
    if (class_exists('\Middleware\CorsMiddleware')) {
        \Middleware\CorsMiddleware::handle();
    } else {
        // Si no existe mediante autoload, intentamos cargar el archivo directamente
        $possibleCorsClass = BASE_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'CorsMiddleware.php';
        if (is_file($possibleCorsClass)) {
            require_once $possibleCorsClass;
            if (class_exists('\Middleware\CorsMiddleware')) {
                \Middleware\CorsMiddleware::handle();
            }
        }
    }

    // CSRF centralizado - SIN ALIASES
    if (PHP_SAPI !== 'cli') {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $appEnv = $_ENV['APP_ENV'] ?? 'production';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (class_exists('\Middleware\CsrfMiddleware', false)) {
                \Middleware\CsrfMiddleware::protect();
            }
        } elseif ($appEnv !== 'production') {
            // Solo en desarrollo asegurar token
            if (
                class_exists('\Middleware\CsrfMiddleware', false) &&
                method_exists('\Middleware\CsrfMiddleware', 'ensureToken')
            ) {
                \Middleware\CsrfMiddleware::ensureToken();
            }
        }
    }
}
