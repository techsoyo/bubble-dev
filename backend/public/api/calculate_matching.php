<?php

require_once __DIR__ . '/../bootstrap.php';
// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php
// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php

/**
 * Endpoint: Cálculo de matching
 * POST /ai/calculate-matching
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Utils/ResponseHelper.php';
require_once __DIR__ . '/../../src/Services/OllamaService.php';

use Services\OllamaService;
use Utils\ResponseHelper;

try {
    // Obtener datos de entrada
    $input = ResponseHelper::getJsonInput();

    // Validar entrada
    if (!isset($input['candidate_data']) || !isset($input['job_requirements'])) {
        ResponseHelper::error('Se requieren candidate_data y job_requirements', 400);
        exit;
    }

    $candidateData = $input['candidate_data'];
    $jobData = $input['job_requirements'];

    ResponseHelper::log('info', 'Calculando matching candidato-trabajo', [
      'candidate_fields' => array_keys($candidateData),
      'job_fields' => array_keys($jobData)
    ]);

    // Crear servicio de Ollama
    $ollamaService = new OllamaService();

    // Calcular matching
    $result = $ollamaService->calculateMatching($candidateData, $jobData);

    // Agregar metadatos
    $result['calculation_timestamp'] = date('c');
    $result['input_summary'] = [
      'candidate_name' => $candidateData['nombre'] ?? 'N/A',
      'job_title' => $jobData['title'] ?? $jobData['titulo'] ?? 'N/A'
    ];

    ResponseHelper::success('Matching calculado correctamente', $result);
} catch (Exception $e) {
    ResponseHelper::log('error', 'Error calculando matching: ' . $e->getMessage());
    ResponseHelper::error('Error al calcular matching: ' . $e->getMessage(), 500);
}
