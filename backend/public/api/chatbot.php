<?php

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php
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
        Res::error('MÃƒÂ©todo no permitido', 405);
    }
    $authUser = JWT::requireAuth();
    $payload = Request::json();
    ['ok' => $ok, 'errors' => $errs] = Val::validate($payload, [
        'messages' => 'required|array',
        'options' => 'array'
    ]);
    if (!$ok) {
        Res::error('ValidaciÃƒÂ³n fallida', 422, ['errors' => $errs]);
    }
    $service = new ChatbotService();
    $response = $service->chat($payload['messages'], $payload['options'] ?? []);
    Res::success('Respuesta generada', ['response' => $response]);
} catch (\Throwable $e) {
    Res::exception($e);
}

