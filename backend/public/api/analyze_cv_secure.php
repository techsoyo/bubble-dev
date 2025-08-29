<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/analyze_cv.php
 * Análisis seguro de CVs con validación robusta de archivos y rate limiting avanzado
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 2.3.0
 */

// Configurar headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Configurar CORS seguro
$allowedOrigins = [
  'https://bubble-talents.com',
  'https://www.bubble-talents.com',
  'https://app.bubble-talents.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
  header('Access-Control-Max-Age: 86400');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

// Requerir autenticación JWT
try {
  $userPayload = \Middleware\JWTMiddleware::requireAuth();
} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Autenticación requerida']);
  exit;
}

use Utils\ResponseHelper as Res;
use Utils\Logger;
use Services\GroqApiService;
use Services\Exceptions\AiUnavailableException;

/**
 * Clase para rate limiting avanzado de uploads
 */
class UploadRateLimiter
{
  private static $attempts = [];
  private static $maxUploadsPerHour = 20;
  private static $maxUploadsPerDay = 50;
  private static $maxProcessingTime = 300; // 5 minutos máximo por archivo
  private static $windowHour = 3600;
  private static $windowDay = 86400;

  public static function canUpload(string $userId): bool
  {
    $currentTime = time();

    // Limpiar entradas antiguas por hora
    self::$attempts[$userId]['hour'] = array_filter(
      self::$attempts[$userId]['hour'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowHour;
      }
    );

    // Limpiar entradas antiguas por día
    self::$attempts[$userId]['day'] = array_filter(
      self::$attempts[$userId]['day'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowDay;
      }
    );

    $uploadsThisHour = count(self::$attempts[$userId]['hour'] ?? []);
    $uploadsToday = count(self::$attempts[$userId]['day'] ?? []);

    return $uploadsThisHour < self::$maxUploadsPerHour && $uploadsToday < self::$maxUploadsPerDay;
  }

  public static function recordUpload(string $userId): void
  {
    $currentTime = time();
    self::$attempts[$userId]['hour'][] = $currentTime;
    self::$attempts[$userId]['day'][] = $currentTime;
  }

  public static function getRemainingUploads(string $userId): array
  {
    $currentTime = time();

    // Limpiar entradas antiguas
    self::$attempts[$userId]['hour'] = array_filter(
      self::$attempts[$userId]['hour'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowHour;
      }
    );

    self::$attempts[$userId]['day'] = array_filter(
      self::$attempts[$userId]['day'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowDay;
      }
    );

    $uploadsThisHour = count(self::$attempts[$userId]['hour'] ?? []);
    $uploadsToday = count(self::$attempts[$userId]['day'] ?? []);

    return [
      'hour' => max(0, self::$maxUploadsPerHour - $uploadsThisHour),
      'day' => max(0, self::$maxUploadsPerDay - $uploadsToday)
    ];
  }
}

/**
 * Configuración de seguridad para uploads
 */
class UploadSecurityConfig
{
  public const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
  public const MAX_TEXT_LENGTH = 512 * 1024; // 512KB
  public const ALLOWED_EXTENSIONS = ['pdf'];
  public const ALLOWED_MIME_TYPES = ['application/pdf'];
  public const UPLOAD_DIR = '/uploads/cvs/';

  // Patrones de contenido sospechoso
  public const SUSPICIOUS_PATTERNS = [
    '/javascript:/i',
    '/<script/i',
    '/eval\s*\(/i',
    '/exec\s*\(/i',
    '/system\s*\(/i',
    '/passthru\s*\(/i',
    '/shell_exec\s*\(/i',
    '/phpinfo\s*\(/i',
    '/base64_decode\s*\(/i',
    '/%PDF-\d+\.\d+/', // Solo PDFs válidos
  ];
}

/**
 * Validar archivo subido con múltiples capas de seguridad
 */
function validateUploadedFile(array $fileData, string $userId): array
{
  // Verificar errores de subida
  if ($fileData['error'] !== UPLOAD_ERR_OK) {
    $errors = [
      UPLOAD_ERR_INI_SIZE => 'Archivo demasiado grande para la configuración del servidor',
      UPLOAD_ERR_FORM_SIZE => 'Archivo excede el límite permitido en el formulario',
      UPLOAD_ERR_PARTIAL => 'Archivo se subió parcialmente',
      UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
      UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: directorio temporal no disponible',
      UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se puede escribir el archivo',
      UPLOAD_ERR_EXTENSION => 'Archivo bloqueado por extensión del servidor'
    ];

    return [
      'success' => false,
      'message' => $errors[$fileData['error']] ?? 'Error desconocido en la subida del archivo'
    ];
  }

  // Validar tamaño del archivo
  if ($fileData['size'] > UploadSecurityConfig::MAX_FILE_SIZE) {
    return [
      'success' => false,
      'message' => 'Archivo demasiado grande. Máximo ' . (UploadSecurityConfig::MAX_FILE_SIZE / 1024 / 1024) . 'MB'
    ];
  }

  // Validar tamaño mínimo (evitar archivos vacíos)
  if ($fileData['size'] < 100) {
    return [
      'success' => false,
      'message' => 'Archivo demasiado pequeño o vacío'
    ];
  }

  // Validar extensión del archivo
  $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
  if (!in_array($extension, UploadSecurityConfig::ALLOWED_EXTENSIONS)) {
    return [
      'success' => false,
      'message' => 'Solo se permiten archivos PDF'
    ];
  }

  // Validar MIME type real (no confiar en $_FILES)
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  if (!$finfo) {
    return [
      'success' => false,
      'message' => 'Error en la validación del tipo de archivo'
    ];
  }

  $mimeType = finfo_file($finfo, $fileData['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mimeType, UploadSecurityConfig::ALLOWED_MIME_TYPES)) {
    return [
      'success' => false,
      'message' => "Tipo de archivo no permitido. Detectado: $mimeType"
    ];
  }

  // Leer contenido del archivo para validación profunda
  $fileContent = file_get_contents($fileData['tmp_name']);
  if ($fileContent === false) {
    return [
      'success' => false,
      'message' => 'Error al leer el contenido del archivo'
    ];
  }

  // Validar que sea un PDF válido
  if (substr($fileContent, 0, 4) !== '%PDF') {
    return [
      'success' => false,
      'message' => 'El archivo no es un PDF válido'
    ];
  }

  // Validar versión del PDF (solo versiones razonables)
  if (!preg_match('/%PDF-1\.[0-7]/', $fileContent)) {
    return [
      'success' => false,
      'message' => 'Versión del PDF no soportada'
    ];
  }

  // Escanear contenido sospechoso
  if (containsSuspiciousContent($fileContent)) {
    // Log intento de upload malicioso
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('suspicious_file_upload', [
        'user_id' => $userId,
        'file_name' => $fileData['name'],
        'file_size' => $fileData['size'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    return [
      'success' => false,
      'message' => 'Archivo rechazado por medidas de seguridad'
    ];
  }

  return [
    'success' => true,
    'message' => 'Archivo válido',
    'extension' => $extension,
    'mime_type' => $mimeType,
    'size' => $fileData['size']
  ];
}

/**
 * Detectar contenido sospechoso en el archivo
 */
function containsSuspiciousContent(string $content): bool
{
  foreach (UploadSecurityConfig::SUSPICIOUS_PATTERNS as $pattern) {
    if (preg_match($pattern, $content)) {
      return true;
    }
  }

  return false;
}

/**
 * Procesar archivo subido de forma segura
 */
function processUploadedFile(array $fileData, string $userId): array
{
  // Generar nombre de archivo seguro
  $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
  $safeFilename = 'cv_' . $userId . '_' . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

  // Crear directorio si no existe
  $uploadDir = dirname(__DIR__, 2) . UploadSecurityConfig::UPLOAD_DIR;
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $destination = $uploadDir . $safeFilename;

  // Mover archivo de forma segura
  if (!move_uploaded_file($fileData['tmp_name'], $destination)) {
    return [
      'success' => false,
      'message' => 'Error al guardar el archivo'
    ];
  }

  // Verificar que el archivo se guardó correctamente
  if (!file_exists($destination) || filesize($destination) !== $fileData['size']) {
    // Limpiar archivo si hay problemas
    if (file_exists($destination)) {
      unlink($destination);
    }
    return [
      'success' => false,
      'message' => 'Error en la verificación del archivo guardado'
    ];
  }

  return [
    'success' => true,
    'path' => $destination,
    'filename' => $safeFilename,
    'extension' => $extension,
    'size' => $fileData['size']
  ];
}

/**
 * Limpiar archivos temporales de forma segura
 */
function cleanupTempFile(?string $filePath): void
{
  if ($filePath && file_exists($filePath) && strpos($filePath, UploadSecurityConfig::UPLOAD_DIR) !== false) {
    unlink($filePath);
  }
}

// === PROCESAMIENTO PRINCIPAL ===

try {
  $startTime = microtime(true);
  $cvText = '';
  $isPdfAnalysis = false;
  $processingMethod = 'unknown';
  $filePath = null;

  // Verificar rate limiting
  $userId = (string)$userPayload['user_id'];

  if (!UploadRateLimiter::canUpload($userId)) {
    $remaining = UploadRateLimiter::getRemainingUploads($userId);

    // Log intento de rate limit
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('upload_rate_limited', [
        'user_id' => $userId,
        'remaining_hour' => $remaining['hour'],
        'remaining_day' => $remaining['day'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    http_response_code(429);
    Res::error('Límite de subida de archivos excedido. Intenta más tarde.', 429);
    exit;
  }

  // Obtener input JSON
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  // Si hay error en JSON pero también archivos subidos, continuar
  if (json_last_error() !== JSON_ERROR_NONE && empty($_FILES)) {
    http_response_code(400);
    Res::error('JSON inválido: ' . json_last_error_msg(), 400);
    exit;
  }

  // === OPCIÓN 1: Archivo subido ===
  if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Validar archivo
    $fileValidation = validateUploadedFile($_FILES['cv_file'], $userId);

    if (!$fileValidation['success']) {
      http_response_code(400);
      Res::error($fileValidation['message'], 400);
      exit;
    }

    // Procesar archivo
    $fileResult = processUploadedFile($_FILES['cv_file'], $userId);

    if (!$fileResult['success']) {
      http_response_code(500);
      Res::error($fileResult['message'], 500);
      exit;
    }

    $filePath = $fileResult['path'];
    $extension = $fileResult['extension'];

    if ($extension === 'pdf') {
      $isPdfAnalysis = true;
      $processingMethod = 'groq-vision-pdf';
    } else {
      // Extraer texto del archivo
      $cvText = extractTextFromFile($filePath, $extension, $fileResult['mime_type'] ?? '');
      $processingMethod = 'groq-text-from-file';
    }
  }
  // === OPCIÓN 2: Texto directo en JSON ===
  elseif (isset($data['cv_text'])) {
    $cvText = trim($data['cv_text']);
    $processingMethod = 'groq-text-direct';

    if (strlen($cvText) > UploadSecurityConfig::MAX_TEXT_LENGTH) {
      http_response_code(400);
      Res::error('El texto del CV excede el tamaño máximo permitido.', 400);
      exit;
    }

    if (empty($cvText)) {
      http_response_code(400);
      Res::error('El texto del CV no puede estar vacío.', 400);
      exit;
    }
  }
  // === OPCIÓN 3: Archivo de texto por ruta ===
  elseif (isset($data['text_file_path'])) {
    $requestedPath = $data['text_file_path'];

    // Validar que la ruta sea segura (no permita directory traversal)
    $realPath = realpath($requestedPath);
    $allowedDir = realpath(dirname(__DIR__, 2) . '/uploads/');

    if (!$realPath || strpos($realPath, $allowedDir) !== 0) {
      http_response_code(403);
      Res::error('Acceso denegado al archivo solicitado.', 403);
      exit;
    }

    if (!file_exists($realPath)) {
      http_response_code(404);
      Res::error('Archivo no encontrado.', 404);
      exit;
    }

    $fileContent = file_get_contents($realPath);
    $fileData = json_decode($fileContent, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($fileData['text'])) {
      $cvText = $fileData['text'];
    } else {
      $cvText = $fileContent;
    }

    if (strlen($cvText) > UploadSecurityConfig::MAX_TEXT_LENGTH) {
      http_response_code(400);
      Res::error('El contenido del archivo excede el tamaño máximo permitido.', 400);
      exit;
    }

    $processingMethod = 'groq-text-from-file-path';
  } else {
    http_response_code(400);
    Res::error('No se proporcionó CV. Use cv_file, cv_text o text_file_path.', 400);
    exit;
  }

  // === PROCESAMIENTO SEGÚN TIPO ===

  $groqService = new GroqApiService();

  if ($isPdfAnalysis) {
    // Análisis de PDF con Groq (extrae texto del PDF internamente)
    $cvData = $groqService->analyzeCvFromPdf($filePath);
    $processingService = 'Groq API (Vision) - PDF';
  } else {
    // Análisis de texto con Groq
    if (empty($cvText)) {
      http_response_code(400);
      Res::error('No se pudo extraer texto del CV.', 400);
      exit;
    }

    $cvData = $groqService->analyzeCvFromText($cvText);
    $processingService = 'Groq API (Llama3) - Text';
  }

  $endTime = microtime(true);
  $processingTime = round($endTime - $startTime, 2);

  // Verificar tiempo de procesamiento (evitar ataques de denegación de servicio)
  if ($processingTime > 300) { // 5 minutos máximo
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('processing_time_exceeded', [
        'user_id' => $userId,
        'processing_time' => $processingTime,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }
  }

  // Registrar upload exitoso
  UploadRateLimiter::recordUpload($userId);

  // Log análisis exitoso
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::info('CV analysis completed', [
      'user_id' => $userId,
      'method' => $processingMethod,
      'processing_time' => $processingTime,
      'file_size' => $isPdfAnalysis ? filesize($filePath) : strlen($cvText ?? ''),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  // Limpiar archivo temporal
  cleanupTempFile($filePath);

  // === RESPUESTA EXITOSA ===
  Res::success('CV analizado correctamente', [
    'structured_data' => $cvData,
    'processing_info' => [
      'method' => $processingMethod,
      'service' => $processingService,
      'text_length' => $isPdfAnalysis ? 'PDF' : strlen($cvText ?? ''),
      'processing_time' => $processingTime,
      'extracted_fields' => is_array($cvData) ? array_keys($cvData) : [],
      'total_fields' => is_array($cvData) ? count($cvData) : 0,
      'endpoint' => '/api/analyze_cv.php',
      'version' => '2.3.0'
    ]
  ]);
} catch (AiUnavailableException $e) {
  // Log error de IA
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::error('AI service unavailable', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'error' => $e->getMessage(),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  cleanupTempFile($filePath ?? null);
  http_response_code(503);
  Res::error('Servicio de IA no disponible: ' . $e->getMessage(), 503);
} catch (Throwable $e) {
  // Log error general
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::error('CV analysis error', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'error' => $e->getMessage(),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  cleanupTempFile($filePath ?? null);
  http_response_code(500);
  Res::error('Error al analizar CV: ' . $e->getMessage(), 500);
}

/**
 * Extraer texto de diferentes tipos de archivo de forma segura
 */
function extractTextFromFile(string $filePath, string $extension, string $mime): ?string
{
  // Verificar que el archivo existe y es accesible
  if (!file_exists($filePath) || !is_readable($filePath)) {
    return null;
  }

  // Verificar tamaño del archivo
  $fileSize = filesize($filePath);
  if ($fileSize > UploadSecurityConfig::MAX_FILE_SIZE) {
    return null;
  }

  switch ($extension) {
    case 'txt':
      $content = file_get_contents($filePath);
      return strlen($content) <= UploadSecurityConfig::MAX_TEXT_LENGTH ? $content : null;

    case 'pdf':
      // Para PDFs, retornamos null para usar análisis directo con Vision API
      return null;

    case 'doc':
    case 'docx':
      // No implementado por seguridad - solo PDFs permitidos
      throw new Exception('Análisis de archivos Word no implementado');

    default:
      throw new Exception('Tipo de archivo no soportado: ' . $extension);
  }
}
