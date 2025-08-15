<?php

/**
 * CV Parsing Service with OpenAI Integration
 *
 * Este endpoint reemplaza la funcionalidad del MVP para procesar CVs.
 * Ahora usa OpenAI para extraer información de archivos PDF y DOCX.
 *
 * @package Backend\API\AI
 * @version 2.0.0
 * @since 2025-01-10
 */

declare(strict_types=1);
$env = getenv('APP_ENV') ?: 'production';
if ($env === 'production') {
    http_response_code(404);
    exit;
}
$ROOT = dirname(__DIR__, 2);             // ai -> api -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

require_once __DIR__ . '/../../src/Services/OpenAIService.php';
require_once __DIR__ . '/../../src/Services/PDFExtractorService.php';

use Services\OpenAIService;
use Services\PDFExtractorService;

// Log para debugging
error_log('CV Parse Request - Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('CV Parse Request - Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'No origin'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Verificar que se haya subido un archivo
    if (!isset($_FILES['cv_file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No se encontró el archivo CV']);
        exit;
    }

    $file = $_FILES['cv_file'];
    $userEmail = $_POST['user_email'] ?? 'unknown';
    $useOpenAI = $_POST['use_openai'] ?? 'false';

    // Validar el archivo
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Error al subir el archivo']);
        exit;
    }

    // Verificar tipo de archivo
    $allowedTypes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de archivo no permitido. Solo PDF y DOCX']);
        exit;
    }

    // Verificar tamaño (10MB máximo)
    if ($file['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'El archivo es demasiado grande. Máximo 10MB']);
        exit;
    }

    // Crear directorios si no existen
    $uploadDir = __DIR__ . '/../../uploads';
    $cvsDir = $uploadDir . '/cvs';
    $textsDir = $uploadDir . '/textos';
    $jsonDir = $uploadDir . '/json';

    foreach ([$uploadDir, $cvsDir, $textsDir, $jsonDir] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // Generar nombre único para el archivo
    $timestamp = date('Y-m-d_H-i-s');
    $userSlug = preg_replace('/[^a-zA-Z0-9]/', '_', $userEmail);
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "cv_{$userSlug}_{$timestamp}.{$extension}";

    // Guardar archivo subido
    $cvPath = $cvsDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $cvPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo guardar el archivo']);
        exit;
    }

    // Inicializar servicios
    $openAIService = new OpenAIService();
    $pdfExtractorService = new PDFExtractorService();

    // Extraer texto del archivo
    $extractedText = null;
    try {
        if ($file['type'] === 'application/pdf') {
            $extractedText = $pdfExtractorService->extractText($cvPath);
        } else {
            // Para archivos DOCX, por ahora usaremos el mismo método
            // En futuro se puede agregar soporte específico para DOCX
            $extractedText = $pdfExtractorService->extractText($cvPath);
        }
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al extraer texto: ' . $e->getMessage()]);
        exit;
    }

    if (!$extractedText) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo extraer texto del archivo']);
        exit;
    }

    // Guardar texto extraído
    $textFilename = "cv_{$userSlug}_{$timestamp}.txt";
    $textPath = $textsDir . '/' . $textFilename;
    file_put_contents($textPath, $extractedText);

    // Procesar con OpenAI para extraer datos estructurados
    $cvData = $openAIService->analyzeCVWithOpenAI($extractedText);

    if (!$cvData) {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo procesar el CV con OpenAI']);
        exit;
    }

    // Guardar JSON procesado
    $jsonFilename = "cv_{$userSlug}_{$timestamp}.json";
    $jsonPath = $jsonDir . '/' . $jsonFilename;
    file_put_contents($jsonPath, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'CV procesado exitosamente con OpenAI',
        'cv_data' => $cvData,
        'files' => [
            'original' => $filename,
            'text' => $textFilename,
            'json' => $jsonFilename
        ],
        'processing_info' => [
            'ai_model' => 'OpenAI GPT-4',
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'processed_at' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    error_log('CV Processing Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
}
