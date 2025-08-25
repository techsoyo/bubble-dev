<?php


require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
require_once __DIR__ . '/../../src/Middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../../src/Services/ChatbotService.php';

use Services\ChatbotService;
use Utils\JWT;
use Utils\Request;
use Utils\ResponseHelper as Res;
use Utils\Validator as Val;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    if ($method !== 'POST') {
        Res::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
    }
    $authUser = JWT::requireAuth();
    $payload = Request::json();
    ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
        'messages' => 'required|array',
        'options' => 'array'
    ]);
    if (!$ok) {
        Res::error('ValidaciÃƒÆ’Ã‚Â³n fallida', 422, ['errors' => $errs]);
    }
    $service = new ChatbotService();
    $response = $service->chat($payload['messages'], $payload['options'] ?? []);
    Res::success('Respuesta generada', ['response' => $response]);
} catch (\Throwable $e) {
    Res::exception($e);
}


