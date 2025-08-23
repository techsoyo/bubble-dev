<?php

/**
 * Endpoint único para análisis completo de CV
 * 
 * Soporta análisis de:
 * - Texto de CV (usando GroqApiService)  
 * - Archivos PDF (usando GroqApiService con extracción de texto)
 * - Archivos de texto (TXT, JSON)
 * - Archivos de documentos (DOC, DOCX)
 * 
 * Funciona para todas las rutas:
 * - /api/analyze_cv.php (directo)
 * - /ai/analyze-cv (via index.php)
 * 
 * Versión consolidada usando solo Groq (sin Ollama)
 * 
 * @version 2.2.0 - Solo Groq API
 * @author Bubble of Talents Team
 */

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use Services\GroqApiService;
use Services\Exceptions\AiUnavailableException;
use Utils\ResponseHelper;

// Configuración de seguridad
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB máximo para archivos
define('MAX_TEXT_LENGTH', 1024 * 1024); // 1MB máximo para texto plano
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'txt']); // Extensiones permitidas
define('ALLOWED_PDF_TYPES', ['application/pdf']); // Solo PDFs
define('ALLOWED_TEXT_TYPES', ['text/plain', 'application/json']); // Tipos de texto

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ResponseHelper::error('Método no permitido. Use POST.', 405);
  exit;
}

/**
 * Valida archivo subido según su tipo
 */
function validateUploadedFile($fileData)
{
  // Comprobar errores de subida
  if ($fileData['error'] !== UPLOAD_ERR_OK) {
    $errors = [
      UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por PHP.',
      UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido.',
      UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente.',
      UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo.',
      UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal.',
      UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo.',
      UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la subida.',
    ];
    return ['status' => false, 'message' => $errors[$fileData['error']] ?? 'Error desconocido en la subida.'];
  }

  // Validar tamaño
  if ($fileData['size'] > MAX_FILE_SIZE) {
    return [
      'status' => false,
      'message' => 'El archivo excede el tamaño máximo permitido (' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB).'
    ];
  }

  // Validar extensión
  $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
  if (!in_array($extension, ALLOWED_EXTENSIONS)) {
    return ['status' => false, 'message' => 'Extensión de archivo no permitida. Use: ' . implode(', ', ALLOWED_EXTENSIONS)];
  }

  // Validar MIME type
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($fileData['tmp_name']);

  $allowedMimes = array_merge(ALLOWED_PDF_TYPES, ALLOWED_TEXT_TYPES, [
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
  ]);

  if (!in_array($mime, $allowedMimes)) {
    return ['status' => false, 'message' => 'Tipo de archivo no válido.'];
  }

  // Crear directorio si no existe
  $uploadDir = __DIR__ . '/../../uploads/cvs/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  // Generar nombre seguro
  $safeFilename = 'cv_' . date('Y-m-d_H-i-s') . '_' . md5(uniqid()) . '.' . $extension;
  $destination = $uploadDir . $safeFilename;

  if (!move_uploaded_file($fileData['tmp_name'], $destination)) {
    return ['status' => false, 'message' => 'Error al guardar el archivo.'];
  }

  return [
    'status' => true,
    'path' => $destination,
    'extension' => $extension,
    'mime' => $mime,
    'size' => $fileData['size']
  ];
}

/**
 * Extrae texto de diferentes tipos de archivo
 */
function extractTextFromFile($filePath, $extension, $mime)
{
  switch ($extension) {
    case 'txt':
      return file_get_contents($filePath);

    case 'pdf':
      // Para PDFs, retornar null para usar análisis directo con Vision
      return null;

    case 'doc':
    case 'docx':
      // TODO: Implementar extracción de Word si es necesario
      throw new Exception('Análisis de archivos Word no implementado aún');

    default:
      throw new Exception('Tipo de archivo no soportado: ' . $extension);
  }
}

// === PROCESAMIENTO PRINCIPAL ===

try {
  $startTime = microtime(true);
  $cvText = '';
  $isPdfAnalysis = false;
  $processingMethod = 'unknown';

  // Obtener input JSON
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  // Si hay error en JSON pero también archivos subidos, continuar
  if (json_last_error() !== JSON_ERROR_NONE && empty($_FILES)) {
    ResponseHelper::error('JSON inválido: ' . json_last_error_msg(), 400);
    exit;
  }

  // === OPCIÓN 1: Archivo subido ===
  if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileValidation = validateUploadedFile($_FILES['cv_file']);

    if (!$fileValidation['status']) {
      ResponseHelper::error($fileValidation['message'], 400);
      exit;
    }

    $filePath = $fileValidation['path'];
    $extension = $fileValidation['extension'];
    $mime = $fileValidation['mime'];

    if ($extension === 'pdf') {
      // Análisis directo de PDF con Vision
      $isPdfAnalysis = true;
      $processingMethod = 'ollama-vision';
    } else {
      // Extraer texto del archivo
      $cvText = extractTextFromFile($filePath, $extension, $mime);
      $processingMethod = 'groq-text-from-file';
    }
  }
  // === OPCIÓN 2: Texto directo en JSON ===
  elseif (isset($data['cv_text'])) {
    $cvText = $data['cv_text'];
    $processingMethod = 'groq-text-direct';

    if (strlen($cvText) > MAX_TEXT_LENGTH) {
      ResponseHelper::error('El texto del CV excede el tamaño máximo permitido.', 400);
      exit;
    }
  }
  // === OPCIÓN 3: Archivo de texto por ruta ===
  elseif (isset($data['text_file_path'])) {
    $filePath = $data['text_file_path'];

    if (!file_exists($filePath)) {
      ResponseHelper::error('Archivo no encontrado: ' . $filePath, 404);
      exit;
    }

    $fileContent = file_get_contents($filePath);
    $fileData = json_decode($fileContent, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($fileData['text'])) {
      $cvText = $fileData['text'];
    } else {
      $cvText = $fileContent;
    }
    $processingMethod = 'groq-text-from-path';
  } else {
    ResponseHelper::error('No se proporcionó CV. Use cv_file, cv_text o text_file_path.', 400);
    exit;
  }

  // === PROCESAMIENTO SEGÚN TIPO ===

  $groqService = new GroqApiService();

  if ($isPdfAnalysis) {
    // Análisis de PDF con Groq (extrae texto del PDF internamente)
    try {
      $cvData = $groqService->analyzeCvFromPdf($filePath);
      $processingService = 'Groq API (Llama3) - PDF';
    } catch (Exception $e) {
      ResponseHelper::error('Error en análisis PDF: ' . $e->getMessage(), 500);
      exit;
    }
  } else {
    // Análisis de texto con Groq
    if (empty($cvText)) {
      ResponseHelper::error('No se pudo extraer texto del CV', 400);
      exit;
    }

    $cvData = $groqService->analyzeCvFromText($cvText);
    $processingService = 'Groq API (Llama3) - Text';
  }

  $endTime = microtime(true);
  $processingTime = round($endTime - $startTime, 2);

  // Limpiar archivo temporal si existe
  if (isset($filePath) && file_exists($filePath) && strpos($filePath, '/uploads/cvs/') !== false) {
    unlink($filePath);
  }

  // === RESPUESTA EXITOSA ===
  ResponseHelper::success('CV analizado correctamente', [
    'structured_data' => $cvData,
    'processing_info' => [
      'method' => $processingMethod,
      'service' => $processingService,
      'text_length' => $isPdfAnalysis ? 'PDF' : strlen($cvText),
      'processing_time' => $processingTime,
      'extracted_fields' => array_keys($cvData),
      'total_fields' => count($cvData),
      'endpoint' => '/api/analyze_cv.php',
      'version' => '2.0.0'
    ],
    'success' => true
  ]);
} catch (AiUnavailableException $e) {
  ResponseHelper::error('Servicio de IA no disponible: ' . $e->getMessage(), 503);
} catch (Exception $e) {
  ResponseHelper::error('Error al analizar CV: ' . $e->getMessage(), 500);

  // Limpiar archivo temporal en caso de error
  if (isset($filePath) && file_exists($filePath) && strpos($filePath, '/uploads/cvs/') !== false) {
    unlink($filePath);
  }
}
