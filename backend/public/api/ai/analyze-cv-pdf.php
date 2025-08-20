<?php

require_once __DIR__ . '/../bootstrap.php';

/**
 * Endpoint: Análisis completo de CV PDF con Llama3.2-Vision
 * POST /ai/analyze-cv-pdf
 *
 * Endpoint principal para análisis directo de PDFs usando OllamaServiceStandard
 * con librería hanwoolderink/ollama-php-client.
 *
 * @version 5.0.0 - Migración a librería estándar
 * @author Bubble of Talents AI Team
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Restringir métodos HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  echo json_encode([
    'success' => false,
    'message' => 'Método no permitido. Use POST.'
  ]);
  exit;
}

// Cargar dependencias
require_once __DIR__ . '/../../autoload.php';

use Services\OllamaServiceStandard;
use Services\Exceptions\AiUnavailableException;
use Utils\ResponseHelper;

// Configuración de seguridad
define('MAX_PDF_SIZE', 10 * 1024 * 1024); // 10MB máximo para PDFs
define('ALLOWED_PDF_TYPES', ['application/pdf']); // Solo PDFs

/**
 * Valida archivo PDF subido
 */
function validatePdfFile($fileData)
{
  // Comprobar errores de subida
  if ($fileData['error'] !== UPLOAD_ERR_OK) {
    $errors = [
      UPLOAD_ERR_INI_SIZE => 'El archivo PDF excede el tamaño máximo permitido por PHP.',
      UPLOAD_ERR_FORM_SIZE => 'El archivo PDF excede el tamaño máximo permitido.',
      UPLOAD_ERR_PARTIAL => 'El archivo PDF se subió parcialmente.',
      UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo PDF.',
      UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal.',
      UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo PDF.',
      UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la subida del PDF.',
    ];
    return ['status' => false, 'message' => $errors[$fileData['error']] ?? 'Error desconocido en la subida.'];
  }

  // Validar tamaño
  if ($fileData['size'] > MAX_PDF_SIZE) {
    return [
      'status' => false,
      'message' => 'El PDF excede el tamaño máximo permitido (' . (MAX_PDF_SIZE / 1024 / 1024) . 'MB).'
    ];
  }

  // Validar extensión
  $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
  if ($extension !== 'pdf') {
    return ['status' => false, 'message' => 'Solo se permiten archivos PDF.'];
  }

  // Validar MIME type
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($fileData['tmp_name']);

  if (!in_array($mime, ALLOWED_PDF_TYPES)) {
    return ['status' => false, 'message' => 'Tipo de archivo no válido. Solo PDFs.'];
  }

  // Mover a ubicación segura
  $uploadDir = __DIR__ . '/../../uploads/cvs/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $safeFilename = 'cv_' . date('Y-m-d_H-i-s') . '_' . md5(uniqid()) . '.pdf';
  $destination = $uploadDir . $safeFilename;

  if (!move_uploaded_file($fileData['tmp_name'], $destination)) {
    return ['status' => false, 'message' => 'Error al guardar el archivo PDF.'];
  }

  return [
    'status' => true,
    'message' => 'PDF subido correctamente.',
    'data' => [
      'path' => $destination,
      'name' => htmlspecialchars($fileData['name'], ENT_QUOTES, 'UTF-8'),
      'type' => $mime,
      'size' => $fileData['size']
    ]
  ];
}

try {
  $startTime = microtime(true);
  $requestId = uniqid('PDF-CV-');

  error_log("[{$requestId}] Iniciando análisis de CV PDF con OllamaServiceStandard");

  // Validar que se subió un archivo PDF
  if (!isset($_FILES['cv']) || empty($_FILES['cv']['tmp_name'])) {
    ResponseHelper::error('Se requiere un archivo PDF del CV', 400);
    exit;
  }

  // Validar archivo PDF
  $fileValidation = validatePdfFile($_FILES['cv']);

  if (!$fileValidation['status']) {
    ResponseHelper::error($fileValidation['message'], 400);
    exit;
  }

  $cvPath = $fileValidation['data']['path'];
  $originalName = $fileValidation['data']['name'];

  error_log("[{$requestId}] PDF validado: {$originalName}, guardado en: " . basename($cvPath));

  // Inicializar servicio Ollama con librería estándar
  try {
    $ollamaService = new OllamaServiceStandard();

    // Verificar disponibilidad de Ollama
    if (!$ollamaService->isAvailable()) {
      error_log("[{$requestId}] Ollama no está disponible");
      ResponseHelper::error('Servicio de IA temporalmente no disponible', 503);
      exit;
    }

    error_log("[{$requestId}] OllamaServiceStandard inicializado correctamente");
  } catch (\Exception $e) {
    error_log("[{$requestId}] Error inicializando OllamaServiceStandard: " . $e->getMessage());
    ResponseHelper::error('Error inicializando servicio de IA', 500);
    exit;
  }

  // Procesar CV PDF con Llama3.2-Vision
  try {
    error_log("[{$requestId}] Iniciando análisis con Llama3.2-Vision");

    // MÉTODO CRÍTICO: analyzeCvFromPdf usando librería estándar
    $cvData = $ollamaService->analyzeCvFromPdf($cvPath);

    $processingTime = microtime(true) - $startTime;
    error_log("[{$requestId}] Análisis completado en " . round($processingTime, 2) . "s");

    // Validar que se obtuvieron datos
    if (empty($cvData)) {
      error_log("[{$requestId}] No se extrajeron datos del CV");
      ResponseHelper::error('No se pudo extraer información del CV', 422);
      exit;
    }

    // Agregar metadatos del procesamiento
    $cvData['processing_meta'] = [
      'request_id' => $requestId,
      'processing_time' => round($processingTime, 3) . 's',
      'original_filename' => $originalName,
      'file_size' => $fileValidation['data']['size'],
      'ai_service' => 'ollama-standard',
      'model_used' => 'llama3.2-vision:latest',
      'client_library' => 'hanwoolderink/ollama-php-client',
      'processed_at' => date('Y-m-d H:i:s')
    ];

    // Guardar resultado para debugging (opcional en desarrollo)
    $debugMode = ($_ENV['DEBUG_MODE'] ?? getenv('DEBUG_MODE')) === 'true';
    if ($debugMode) {
      $debugDir = __DIR__ . '/../../storage/debug/';
      if (!is_dir($debugDir)) mkdir($debugDir, 0755, true);
      $debugPath = $debugDir . $requestId . '_result.json';
      @file_put_contents($debugPath, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Limpiar archivo temporal después del procesamiento exitoso
    @unlink($cvPath);

    // Respuesta exitosa con todos los campos requeridos
    ResponseHelper::success('CV analizado correctamente con IA', $cvData);
  } catch (AiUnavailableException $e) {
    error_log("[{$requestId}] Error de IA: " . $e->getMessage());

    // Limpiar archivo temporal
    @unlink($cvPath);

    $errorCode = match ($e->getMessage()) {
      'PDF_NOT_FOUND' => 404,
      'PDF_ENCODING_FAILED' => 422,
      'EMPTY_PDF_DATA' => 400,
      'AI_UNAVAILABLE' => 503,
      default => 500
    };

    ResponseHelper::error('Error procesando CV: ' . $e->getMessage(), $errorCode);
  } catch (\Exception $e) {
    error_log("[{$requestId}] Error general: " . $e->getMessage());

    // Limpiar archivo temporal
    @unlink($cvPath);

    ResponseHelper::error('Error interno procesando CV', 500, [
      'request_id' => $requestId,
      'error_type' => get_class($e)
    ]);
  }
} catch (\Throwable $e) {
  $errorId = uniqid('FATAL-');
  error_log("[{$errorId}] Error crítico en analyze-cv-pdf: " . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

  ResponseHelper::error('Error crítico del servidor', 500, ['error_id' => $errorId]);
}
