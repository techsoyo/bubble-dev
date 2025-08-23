<?php

require_once __DIR__ . '/bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php

/**
 * Endpoint: Extracción de habilidades
 * POST /ai/extract-skills
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';
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


    // Crear servicio de integración IA (OpenAI-ready)
    $aiService = new AIIntegrationService();

    // Extraer habilidades usando IA
    $result = $aiService->extractSkills($cvText);

    ResponseHelper::success('Habilidades extraídas correctamente', $result);
} catch (Exception $e) {
    ResponseHelper::log('error', 'Error extrayendo habilidades: ' . $e->getMessage());
    ResponseHelper::error('Error al extraer habilidades: ' . $e->getMessage(), 500);
}
