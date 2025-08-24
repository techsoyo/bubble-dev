<?php

/**
 * Endpoint para calcular el matching entre candidato y trabajo
 *
 * Reemplaza la funcionalidad del módulo IA con implementación en PHP puro
 */

require_once dirname(__DIR__, 3) . '/config/bootstrap.php';

use Services\Matching\JobMatchingService;
use Utils\ResponseHelper;

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Método no permitido', 405);
    exit;
}

// Obtener input JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ResponseHelper::error('JSON inválido: ' . json_last_error_msg(), 400);
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

    // Añadir metadatos del proceso
    $matchResult['processing_info'] = [
        'method' => 'php-matching',
        'processing_time' => $processingTime
    ];

    // Responder con el resultado
    ResponseHelper::success('Matching calculado correctamente', $matchResult);
} catch (Exception $e) {
    ResponseHelper::error('Error al calcular matching: ' . $e->getMessage(), 500);
}
