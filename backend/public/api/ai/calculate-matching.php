<?php


require_once __DIR__ . '/../bootstrap.php';
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
/**
 * Endpoint para calcular el matching entre candidato y trabajo
 *
 * Reemplaza la funcionalidad del mÃƒÂ³dulo IA con implementaciÃƒÂ³n en PHP puro
 */

use Services\Matching\JobMatchingService;
use Utils\ResponseHelper;

// Solo permitir mÃƒÂ©todo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('MÃƒÂ©todo no permitido', 405);
    exit;
}

// Obtener input JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ResponseHelper::error('JSON invÃƒÂ¡lido: ' . json_last_error_msg(), 400);
    exit;
}

// Validar datos de entrada
if (!isset($data['candidate']) || !is_array($data['candidate'])) {
    ResponseHelper::error('Se requieren datos del candidato', 400);
    exit;
}

if (!isset($data['job']) || !is_array($data['job'])) {
    ResponseHelper::error('Se requieren datos del trabajo', 400);
    exit;
}

try {
    $startTime = microtime(true);

    // Crear servicio de matching y calcular
    $matchingService = new JobMatchingService();
    $matchResult = $matchingService->evaluateMatch($data['candidate'], $data['job']);

    $endTime = microtime(true);
    $processingTime = round($endTime - $startTime, 2);

    // AÃƒÂ±adir metadatos del proceso
    $matchResult['processing_info'] = [
        'method' => 'php-matching',
        'processing_time' => $processingTime
    ];

    // Responder con el resultado
    ResponseHelper::success('Matching calculado correctamente', $matchResult);
} catch (Exception $e) {
    ResponseHelper::error('Error al calcular matching: ' . $e->getMessage(), 500);
}

