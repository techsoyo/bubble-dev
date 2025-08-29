<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;
use Services\JobMatchingService;
use Utils\ResponseHelper as Res;

// Autenticación obligatoria
JWTMiddleware::requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Protege solo métodos que cambian estado
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect();
}

// En producción NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    Res::error('Unauthorized (cookie required)', null, 401);
}

// Este endpoint acepta solo POST
if ($method !== 'POST') {
    Res::error('Método no permitido', null, 405);
}

// Leer y validar JSON de entrada
$input = Res::getJsonInput();
if (!is_array($input)) {
    Res::error('JSON inválido o no enviado', null, 400);
}

if (!isset($input['candidate']) || !is_array($input['candidate'])) {
    Res::error('Se requieren datos del candidato', null, 400);
}
if (!isset($input['job']) || !is_array($input['job'])) {
    Res::error('Se requieren datos del trabajo', null, 400);
}

try {
    $start = microtime(true);

    $service = new JobMatchingService();
    $result = $service->evaluateMatch($input['candidate'], $input['job']);

    $elapsedMs = (int)round((microtime(true) - $start) * 1000);

    if (is_array($result)) {
        $result['processing_info'] = [
            'method' => 'php-matching',
            'time_ms' => $elapsedMs,
            'endpoint' => basename(__FILE__),
        ];
    } else {
        $result = [
            'result' => $result,
            'processing_info' => [
                'method' => 'php-matching',
                'time_ms' => $elapsedMs,
                'endpoint' => basename(__FILE__),
            ],
        ];
    }

    Res::success('Matching calculado correctamente', $result);
} catch (\Throwable $e) {
    Res::log('error', 'calculate-matching error', [
        'endpoint' => basename(__FILE__),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    Res::error('Error al calcular matching', $e, 500);
}
