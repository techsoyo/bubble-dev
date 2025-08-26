<?php declare(strict_types=1);




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

// preflightHandle(); // ELIMINADO: Preflight se maneja automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php

/**
 * Endpoint: ExtracciÃƒÆ’Ã‚Â³n de habilidades
 * POST /ai/extract-skills
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Services/AIIntegrationService.php';

use Services\AIIntegrationService;
use Utils\ResponseHelper;

try {
    // Obtener datos de entrada
    $input = ResponseHelper::getJsonInput();

    // Validar entrada
    if (!isset($input['text_file_path']) && !isset($input['cv_text'])) {
        ResponseHelper::error('Se requiere text_file_path o cv_text', 400);
        exit;
    }

    // Obtener texto del CV
    if (isset($input['text_file_path'])) {
        $filePath = $input['text_file_path'];

        if (!file_exists($filePath)) {
            ResponseHelper::error('Archivo no encontrado: ' . $filePath, 404);
            exit;
        }

        $cvText = file_get_contents($filePath);
        if ($cvText === false) {
            ResponseHelper::error('No se pudo leer el archivo', 500);
            exit;
        }
    } else {
        $cvText = $input['cv_text'];
    }

    ResponseHelper::log('info', 'Extrayendo habilidades de CV', [
      'text_length' => strlen($cvText)
    ]);


    // Crear servicio de integraciÃƒÆ’Ã‚Â³n IA (OpenAI-ready)
    $aiService = new AIIntegrationService();

    // Extraer habilidades usando IA
    $result = $aiService->extractSkills($cvText);

    ResponseHelper::success('Habilidades extraÃƒÆ’Ã‚Â­das correctamente', $result);
} catch (Exception $e) {
    ResponseHelper::log('error', 'Error extrayendo habilidades: ' . $e->getMessage());
    ResponseHelper::error('Error al extraer habilidades: ' . $e->getMessage(), 500);
}


