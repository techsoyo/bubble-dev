<?php

/**
 * Endpoint para análisis completo de CV con GroqApiService
 * 
 * Versión producción usando Groq API (gratuito y ultra rápido)
 */

require_once __DIR__ . '/../../../config/bootstrap.php';

use Services\GroqApiService;
use Services\Exceptions\AiUnavailableException;
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

// Determinar la fuente del texto del CV
$cvText = '';

if (isset($data['cv_text'])) {
    $cvText = $data['cv_text'];
} elseif (isset($data['text_file_path'])) {
    $filePath = $data['text_file_path'];

    if (!file_exists($filePath)) {
        ResponseHelper::error('Archivo no encontrado: ' . $filePath, 404);
        exit;
    }

    // Cargar texto desde archivo
    $fileContent = file_get_contents($filePath);
    $fileData = json_decode($fileContent, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($fileData['text'])) {
        $cvText = $fileData['text'];
    } else {
        $cvText = $fileContent;
    }
}

if (empty($cvText)) {
    ResponseHelper::error('No se proporcionó texto del CV', 400);
    exit;
}

try {
    $startTime = microtime(true);

    // Usar GroqApiService para análisis de CV
    $groqService = new GroqApiService();
    $cvData = $groqService->analyzeCvFromText($cvText);

    $endTime = microtime(true);
    $processingTime = round($endTime - $startTime, 2);

    // Responder con los datos estructurados
    ResponseHelper::success('CV analizado correctamente con GroqAPI', [
        'structured_data' => $cvData,
        'processing_info' => [
            'method' => 'groq-api',
            'service' => 'Groq API (Llama3)',
            'text_length' => strlen($cvText),
            'processing_time' => $processingTime,
            'extracted_fields' => array_keys($cvData),
            'total_fields' => count($cvData),
            'free_service' => true,
            'ultra_fast' => true
        ],
        'success' => true
    ]);
} catch (AiUnavailableException $e) {
    ResponseHelper::error('Servicio de IA no disponible: ' . $e->getMessage(), 503);
} catch (Exception $e) {
    ResponseHelper::error('Error al analizar CV: ' . $e->getMessage(), 500);
}
