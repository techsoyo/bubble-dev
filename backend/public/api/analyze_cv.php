<?php

declare(strict_types=1);

use Security\CsrfMiddleware;
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

/**
 * Endpoint ÃƒÆ’Ã‚Âºnico para anÃƒÆ’Ã‚Â¡lisis completo de CV
 * 
 * Soporta anÃƒÆ’Ã‚Â¡lisis de:
 * - Texto de CV (usando GroqApiService)  
 * - Archivos PDF (usando GroqApiService con extracciÃƒÆ’Ã‚Â³n de texto)
 * - Archivos de texto (TXT, JSON)
 * - Archivos de documentos (DOC, DOCX)
 * 
 * Funciona para todas las rutas:
 * - /api/analyze_cv.php (directo)
 * - /ai/analyze-cv (via index.php)
 * 
 * VersiÃƒÆ’Ã‚Â³n consolidada usando solo Groq (sin Ollama)
 * 
 * @version 2.2.0 - Solo Groq API
 * @author Bubble of Talents Team
 */

// ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ HEADERS DE SEGURIDAD FIRST
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

use Services\GroqApiService;
use Services\Exceptions\AiUnavailableException;
use Utils\ResponseHelper;

// ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ REQUERIR AUTENTICACIÃƒÆ’Ã¢â‚¬Å“N JWT SIEMPRE
if (!$userPayload) {
  // JWTMiddleware ya enviÃƒÂ³ la respuesta de error
  exit;
}

// ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ CONFIGURACIÃƒÆ’Ã¢â‚¬Å“N DE SEGURIDAD MEJORADA
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB mÃƒÆ’Ã‚Â¡ximo (reducido)
define('MAX_TEXT_LENGTH', 512 * 1024); // 512KB mÃƒÆ’Ã‚Â¡ximo para texto
define('ALLOWED_EXTENSIONS', ['pdf']); // Solo PDFs por seguridad
define('ALLOWED_MIME_TYPES', ['application/pdf']);
define('UPLOAD_DIR', dirname(__DIR__, 2) . '/uploads/cvs/');
define('MAX_DAILY_UPLOADS', 10); // LÃƒÆ’Ã‚Â­mite diario por usuario

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'success' => false,
    'message' => 'Solo se permite mÃƒÆ’Ã‚Â©todo POST',
    'error_code' => 'METHOD_NOT_ALLOWED'
  ]);
  exit;
}

// ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ RATE LIMITING POR USUARIO
$userId = $userPayload['user_id'];
$rateLimitKey = "cv_upload_$userId";

if (!checkUploadRateLimit($userId)) {
  http_response_code(429);
  echo json_encode([
    'success' => false,
    'message' => 'Has superado el lÃƒÆ’Ã‚Â­mite diario de subidas de CV (10 por dÃƒÆ’Ã‚Â­a)',
    'error_code' => 'RATE_LIMIT_EXCEEDED'
  ]);
  exit;
}

/**
 * Verificar lÃƒÆ’Ã‚Â­mite de subidas por usuario
 */
function checkUploadRateLimit(string $userId): bool
{
  try {
    $db = getDbConnection();

    // Contar subidas del usuario hoy
    $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM bt_cv_uploads 
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ");
    $stmt->execute([$userId]);
    $todayUploads = (int)$stmt->fetchColumn();

    return $todayUploads < MAX_DAILY_UPLOADS;
  } catch (Exception $e) {
    error_log("Rate limit check error: " . $e->getMessage());
    return false; // Fallar seguro
  }
}

/**
 * Valida archivo subido segÃƒÆ’Ã‚Âºn su tipo
 */
function validateUploadedFile($fileData, string $userId): array
{
  // Verificar errores de subida
  if ($fileData['error'] !== UPLOAD_ERR_OK) {
    $errors = [
      UPLOAD_ERR_INI_SIZE => 'Archivo demasiado grande para PHP',
      UPLOAD_ERR_FORM_SIZE => 'Archivo excede el lÃƒÆ’Ã‚Â­mite permitido',
      UPLOAD_ERR_PARTIAL => 'Archivo se subiÃƒÆ’Ã‚Â³ parcialmente',
      UPLOAD_ERR_NO_FILE => 'No se subiÃƒÆ’Ã‚Â³ ningÃƒÆ’Ã‚Âºn archivo',
      UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: carpeta temporal',
      UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se puede escribir',
      UPLOAD_ERR_EXTENSION => 'Archivo bloqueado por extensiÃƒÆ’Ã‚Â³n'
    ];
    return [
      'success' => false,
      'message' => $errors[$fileData['error']] ?? 'Error desconocido en subida'
    ];
  }

  // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VALIDACIÃƒÆ’Ã¢â‚¬Å“N DE TAMAÃƒÆ’Ã¢â‚¬ËœO ESTRICTA
  if ($fileData['size'] > MAX_FILE_SIZE) {
    return [
      'success' => false,
      'message' => 'Archivo demasiado grande. MÃƒÆ’Ã‚Â¡ximo ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'
    ];
  }

  // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VALIDACIÃƒÆ’Ã¢â‚¬Å“N DE EXTENSIÃƒÆ’Ã¢â‚¬Å“N Y MIME TYPE
  $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
  if (!in_array($extension, ALLOWED_EXTENSIONS)) {
    return [
      'success' => false,
      'message' => 'Solo se permiten archivos PDF'
    ];
  }

  // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VALIDACIÃƒÆ’Ã¢â‚¬Å“N DE MIME TYPE REAL (no confiar en $_FILES)
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimeType = finfo_file($finfo, $fileData['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
    return [
      'success' => false,
      'message' => "Tipo de archivo no permitido. Detectado: $mimeType"
    ];
  }

  // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VALIDACIÃƒÆ’Ã¢â‚¬Å“N DE CONTENIDO PDF
  $fileContent = file_get_contents($fileData['tmp_name']);
  if (substr($fileContent, 0, 4) !== '%PDF') {
    return [
      'success' => false,
      'message' => 'El archivo no es un PDF vÃƒÆ’Ã‚Â¡lido'
    ];
  }

  // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ SCAN DE VIRUS BÃƒÆ’Ã‚ÂSICO (buscar patrones sospechosos)
  if (containsSuspiciousContent($fileContent)) {
    return [
      'success' => false,
      'message' => 'Archivo rechazado por seguridad'
    ];
  }

  return ['success' => true, 'message' => 'Archivo vÃƒÆ’Ã‚Â¡lido'];
}

/**
 * Detectar contenido sospechoso bÃƒÆ’Ã‚Â¡sico
 */
function containsSuspiciousContent(string $content): bool
{
  $suspiciousPatterns = [
    '/javascript:/i',
    '/<script/i',
    '/eval\s*\(/i',
    '/exec\s*\(/i',
    '/system\s*\(/i'
  ];

  foreach ($suspiciousPatterns as $pattern) {
    if (preg_match($pattern, $content)) {
      return true;
    }
  }

  return false;
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


/**
 * Extrae texto de diferentes tipos de archivo
 */
function extractTextFromFile($filePath, $extension, $mime)
{
  switch ($extension) {
    case 'txt':
      return file_get_contents($filePath);

    case 'pdf':
      // Para PDFs, retornar null para usar anÃƒÆ’Ã‚Â¡lisis directo con Vision
      return null;

    case 'doc':
    case 'docx':
      // TODO: Implementar extracciÃƒÆ’Ã‚Â³n de Word si es necesario
      throw new Exception('AnÃƒÆ’Ã‚Â¡lisis de archivos Word no implementado aÃƒÆ’Ã‚Âºn');

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

  // Si hay error en JSON pero tambiÃƒÆ’Ã‚Â©n archivos subidos, continuar
  if (json_last_error() !== JSON_ERROR_NONE && empty($_FILES)) {
    ResponseHelper::error('JSON invÃƒÆ’Ã‚Â¡lido: ' . json_last_error_msg(), null);
    http_response_code(400);
    exit;
  }

  // === OPCIÃƒÆ’Ã¢â‚¬Å“N 1: Archivo subido ===
  if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $fileValidation = validateUploadedFile($_FILES['cv_file'], $userId);

    if (!$fileValidation['status']) {
      ResponseHelper::error($fileValidation['message'], null);
      http_response_code(400);
      exit;
    }

    $filePath = $fileValidation['path'];
    $extension = $fileValidation['extension'];
    $mime = $fileValidation['mime'];

    if ($extension === 'pdf') {
      // AnÃƒÆ’Ã‚Â¡lisis directo de PDF con Vision
      $isPdfAnalysis = true;
      $processingMethod = 'ollama-vision';
    } else {
      // Extraer texto del archivo
      $cvText = extractTextFromFile($filePath, $extension, $mime);
      $processingMethod = 'groq-text-from-file';
    }
  }
  // === OPCIÃƒÆ’Ã¢â‚¬Å“N 2: Texto directo en JSON ===
  elseif (isset($data['cv_text'])) {
    $cvText = $data['cv_text'];
    $processingMethod = 'groq-text-direct';

    if (strlen($cvText) > MAX_TEXT_LENGTH) {
      ResponseHelper::error('El texto del CV excede el tamaÃƒÆ’Ã‚Â±o mÃƒÆ’Ã‚Â¡ximo permitido.', null);
      http_response_code(400);
      exit;
    }
  }
  // === OPCIÃƒÆ’Ã¢â‚¬Å“N 3: Archivo de texto por ruta ===
  elseif (isset($data['text_file_path'])) {
    $filePath = $data['text_file_path'];

    if (!file_exists($filePath)) {
      ResponseHelper::error('Archivo no encontrado: ' . $filePath, null);
      http_response_code(404);
      exit;
    }

    $fileContent = file_get_contents($filePath);
    $fileData = json_decode($fileContent, true);

    if (json_last_error() === JSON_ERROR_NONE && isset($fileData['text'])) {
      $cvText = $fileData['text'];
    } else {
      $cvText = $fileContent;
    }
  } else {
    ResponseHelper::error('No se proporcionÃƒÆ’Ã‚Â³ CV. Use cv_file, cv_text o text_file_path.', null);
    http_response_code(400);
    exit;
  }

  // === PROCESAMIENTO SEGÃƒÆ’Ã…Â¡N TIPO ===

  $groqService = new GroqApiService();

  if ($isPdfAnalysis) {
    // AnÃƒÆ’Ã‚Â¡lisis de PDF con Groq (extrae texto del PDF internamente)
    try {
      $cvData = $groqService->analyzeCvFromPdf($filePath);
      $processingService = 'Groq API (Llama3) - PDF';
    } catch (Exception $e) {
      ResponseHelper::error('Error en anÃƒÆ’Ã‚Â¡lisis PDF: ' . $e->getMessage(), $e);
      http_response_code(500);
      exit;
    }
  } else {
    // AnÃƒÆ’Ã‚Â¡lisis de texto con Groq
    if (empty($cvText)) {
      ResponseHelper::error('No se pudo extraer texto del CV', null);
      http_response_code(400);
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
    ]
  ]);
} catch (AiUnavailableException $e) {
} catch (AiUnavailableException $e) {
  ResponseHelper::error('Servicio de IA no disponible: ' . $e->getMessage(), $e);
  http_response_code(503);
} catch (Exception $e) {
  ResponseHelper::error('Error al analizar CV: ' . $e->getMessage(), null);
  http_response_code(500);
  // Limpiar archivo temporal en caso de error

  // Limpiar archivo temporal en caso de error
  if (isset($filePath) && file_exists($filePath) && strpos($filePath, '/uploads/cvs/') !== false) {
    unlink($filePath);
  }
}


