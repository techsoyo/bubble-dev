<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;
use Utils\ResponseHelper as Res;

JWTMiddleware::requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect();
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    Res::error('Unauthorized (cookie required)', null, 401);
}

// Este endpoint acepta solo POST
if ($method !== 'POST') {
    Res::fail('Método no permitido', 405);
}

if (file_exists(__DIR__ . '/../../src/Services/ContentGenerationService.php')) {
    require_once __DIR__ . '/../../src/Services/ContentGenerationService.php';
}

// Leer y validar JSON
$inputData = Res::getJsonInput();
if (!is_array($inputData)) {
    Res::fail('JSON inválido en request body', 400);
}

// Validar action
if (!isset($inputData['action'])) {
    Res::fail('Campo "action" es requerido', 400);
}

// Instanciar servicio
$contentService = null;
if (class_exists(\Services\ContentGenerationService::class)) {
    $contentService = new \Services\ContentGenerationService();
} else {
    Res::error('Servicio de ContentGeneration no disponible', null, 500);
}

try {
    $start = microtime(true);

    $action = $inputData['action'];
    switch ($action) {
        case 'generate_job_description':
            $result = handleJobDescriptionGeneration($contentService, $inputData);
            break;

        case 'generate_interview_questions':
            $result = handleInterviewQuestionsGeneration($contentService, $inputData);
            break;

        case 'predict_job_success':
            $result = handleJobSuccessPrediction($contentService, $inputData);
            break;

        case 'estimate_time_to_fill':
            $result = handleTimeToFillEstimation($contentService, $inputData);
            break;

        case 'generate_sourcing_templates':
            $result = handleSourcingTemplatesGeneration($contentService, $inputData);
            break;

        case 'analyze_diversity':
            $result = handleDiversityAnalysis($contentService, $inputData);
            break;

        case 'bulk_content_generation':
            $result = handleBulkContentGeneration($contentService, $inputData);
            break;

        default:
            Res::fail("Acción no válida: {$action}", 400);
    }

    $elapsedMs = (int)round((microtime(true) - $start) * 1000);

    Res::success('ok', [
        'action' => $action,
        'data' => $result ?? null,
        'timestamp' => date('Y-m-d H:i:s'),
        'service' => 'ContentGenerationService v1.0',
        'processing_info' => [
            'time_ms' => $elapsedMs,
            'endpoint' => basename(__FILE__),
        ],
    ]);
} catch (\Throwable $e) {
    Res::log('error', 'content-generation endpoint error', [
        'endpoint' => basename(__FILE__),
        'action' => $inputData['action'] ?? null,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    Res::error('Error en generación de contenido', $e, 500);
}

// ...existing helper functions (unchanged) ...
