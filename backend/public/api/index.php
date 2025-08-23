<?php

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';

use Utils\ResponseHelper as Res;

// Cargar configuración
require_once __DIR__ . '/../../config/config.php';

spl_autoload_register(function ($class) {
    if (strpos($class, 'AIModule\\') === 0) {
        $file = __DIR__ . '/../src/' . str_replace(['AIModule\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request_uri, PHP_URL_PATH);

try {
    switch ($path) {
        case '/api/notification':
            require_once __DIR__ . '/notification.php';
            break;
        case '/api/statistics':
            require_once __DIR__ . '/statistics.php';
            break;
        case '/api/chatbot':
            require_once __DIR__ . '/chatbot.php';
            break;
        case '/ai/analyze-cv':
            if ($request_method === 'POST') {
                require_once __DIR__ . '/analyze_cv.php';
            } else {
                http_response_code(405);
                Res::error('Método no permitido', 405);
            }
            break;
        case '/ai/extract-skills':
            if ($request_method === 'POST') {
                require_once __DIR__ . '/endpoints/extract_skills.php';
            } else {
                http_response_code(405);
                Res::error('Método no permitido', 405);
            }
            break;
        case '/ai/calculate-matching':
            if ($request_method === 'POST') {
                require_once __DIR__ . '/endpoints/calculate_matching.php';
            } else {
                http_response_code(405);
                Res::error('Método no permitido', 405);
            }
            break;
        case '/ai/health':
            Res::success('OK', [
                'status' => 'ok',
                'module' => 'ai_module',
                'version' => '1.0.0',
                'timestamp' => date('c')
            ]);
            break;
        default:
            http_response_code(404);
            Res::error('Endpoint no encontrado', 404, [
                'path' => $path,
                'available_endpoints' => [
                    '/ai/analyze-cv',
                    '/ai/extract-skills',
                    '/ai/calculate-matching',
                    '/ai/health'
                ]
            ]);
    }
} catch (Throwable $e) {
    http_response_code(500);

    Res::error('Error interno', 500);
}
