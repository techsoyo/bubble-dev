<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * Endpoint: /api/jobs
 * Gestiona operaciones CRUD de ofertas de trabajo de forma segura
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */

// Configurar headers de seguridad mejorados con CSP avanzado
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CSP avanzado para API de jobs
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'none'; img-src 'self' data: https:; font-src 'none'; connect-src 'self'; media-src 'none'; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; form-action 'self'; upgrade-insecure-requests; block-all-mixed-content");

// Headers adicionales de seguridad avanzada
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('X-DNS-Prefetch-Control: off');
header('X-Requested-With: XMLHttpRequest');

// Configurar CORS seguro
$allowedOrigins = [
  'https://bubble-talents.com',
  'https://www.bubble-talents.com',
  'https://app.bubble-talents.com',
  'http://localhost:8000',
  'http://localhost:3000',
  'http://localhost:3002',
  'http://127.0.0.1:8000',
  'http://127.0.0.1:3000',
  'http://127.0.0.1:3002'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
  header('Access-Control-Max-Age: 86400');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Autenticación JWT solo para operaciones que modifican datos
$method = $_SERVER['REQUEST_METHOD'];
$requiresAuth = in_array($method, ['POST', 'PUT', 'DELETE']) || (isset($_GET['action']) && $_GET['action'] === 'translate');

$userPayload = null;
$userId = null;
$userRole = 'guest';

if ($requiresAuth) {
  $userPayload = \Middleware\JWTMiddleware::requireAuth();
  if (!$userPayload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Autenticación requerida', 'error' => 'UNAUTHORIZED']);
    exit;
  }

  $userId = $userPayload['user_id'] ?? null;
  $userRole = $userPayload['role'] ?? 'guest';

  // Verificar que el usuario esté activo
  if (!$userId || !in_array($userRole, ['admin', 'hr', 'recruiter', 'candidate'])) {
    logSecurityEvent('invalid_user_role_jobs', [
      'user_id' => $userId,
      'user_role' => $userRole,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Rol de usuario inválido', 'error' => 'FORBIDDEN']);
    exit;
  }

  if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
  }
}

use Utils\ResponseHelper;
use Utils\Logger;

/**
 * Función de auditoría de seguridad para jobs
 */
function logSecurityEvent(string $event, array $context): void
{
  $logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'event' => $event,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'context' => $context
  ];

  $logFile = __DIR__ . '/../logs/security_jobs.log';
  $logDir = dirname($logFile);
  if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
  }

  $jsonEntry = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
  file_put_contents($logFile, $jsonEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Función de validación robusta para datos de jobs
 */
function validateJobInput(array $input, bool $isUpdate = false): array
{
  $errors = [];
  $sanitized = [];

  // Campos requeridos para creación
  $requiredFields = ['title', 'description', 'department_id', 'location'];
  if (!$isUpdate) {
    foreach ($requiredFields as $field) {
      if (!isset($input[$field]) || trim($input[$field]) === '') {
        $errors[] = "Campo requerido faltante: {$field}";
      }
    }
  }

  // Validar y sanitizar título
  if (isset($input['title'])) {
    $title = trim(strip_tags($input['title']));
    if (strlen($title) < 3 || strlen($title) > 200) {
      $errors[] = 'El título debe tener entre 3 y 200 caracteres';
    } else {
      $sanitized['title'] = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    }
  }

  // Validar y sanitizar descripción
  if (isset($input['description'])) {
    $description = trim(strip_tags($input['description']));
    if (strlen($description) < 10 || strlen($description) > 5000) {
      $errors[] = 'La descripción debe tener entre 10 y 5000 caracteres';
    } else {
      $sanitized['description'] = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    }
  }

  // Validar department_id
  if (isset($input['department_id'])) {
    $deptId = filter_var($input['department_id'], FILTER_VALIDATE_INT);
    if ($deptId === false || $deptId <= 0) {
      $errors[] = 'ID de departamento inválido';
    } else {
      $sanitized['department_id'] = $deptId;
    }
  }

  // Validar y sanitizar ubicación
  if (isset($input['location'])) {
    $location = trim(strip_tags($input['location']));
    if (strlen($location) < 2 || strlen($location) > 100) {
      $errors[] = 'La ubicación debe tener entre 2 y 100 caracteres';
    } else {
      $sanitized['location'] = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
    }
  }

  // Validar salario (opcional)
  if (isset($input['salary_min']) && $input['salary_min'] !== '') {
    $salaryMin = filter_var($input['salary_min'], FILTER_VALIDATE_FLOAT);
    if ($salaryMin === false || $salaryMin < 0) {
      $errors[] = 'Salario mínimo inválido';
    } else {
      $sanitized['salary_min'] = $salaryMin;
    }
  }

  if (isset($input['salary_max']) && $input['salary_max'] !== '') {
    $salaryMax = filter_var($input['salary_max'], FILTER_VALIDATE_FLOAT);
    if ($salaryMax === false || $salaryMax < 0) {
      $errors[] = 'Salario máximo inválido';
    } else {
      $sanitized['salary_max'] = $salaryMax;
    }
  }

  // Validar que salary_max >= salary_min si ambos están presentes
  if (isset($sanitized['salary_min'], $sanitized['salary_max']) && $sanitized['salary_max'] < $sanitized['salary_min']) {
    $errors[] = 'El salario máximo debe ser mayor o igual al mínimo';
  }

  // Validar tipo de trabajo
  if (isset($input['job_type'])) {
    $validTypes = ['full-time', 'part-time', 'contract', 'freelance', 'internship'];
    if (!in_array($input['job_type'], $validTypes)) {
      $errors[] = 'Tipo de trabajo inválido';
    } else {
      $sanitized['job_type'] = $input['job_type'];
    }
  }

  // Validar estado
  if (isset($input['status'])) {
    $validStatuses = ['draft', 'published', 'closed', 'paused'];
    if (!in_array($input['status'], $validStatuses)) {
      $errors[] = 'Estado de trabajo inválido';
    } else {
      $sanitized['status'] = $input['status'];
    }
  }

  // Validar fecha de expiración
  if (isset($input['expires_at'])) {
    $expiresAt = strtotime($input['expires_at']);
    if ($expiresAt === false) {
      $errors[] = 'Fecha de expiración inválida';
    } elseif ($expiresAt <= time()) {
      $errors[] = 'La fecha de expiración debe ser futura';
    } else {
      $sanitized['expires_at'] = date('Y-m-d H:i:s', $expiresAt);
    }
  }

  return ['errors' => $errors, 'sanitized' => $sanitized];
}

/**
 * Clase JobTranslate - Maneja la traducción de contenido de trabajos con seguridad
 */
class JobTranslate
{
  /**
   * Traduce contenido de trabajo con validación de seguridad
   */
  public static function translateContent(string $text, string $targetLang, string $clientIp): array
  {
    // Validar entrada
    if (empty($text) || strlen($text) > 10000) {
      return ['success' => false, 'error' => 'Texto inválido o demasiado largo'];
    }

    if (!preg_match('/^[a-z]{2}$/', $targetLang)) {
      return ['success' => false, 'error' => 'Idioma de destino inválido'];
    }

    try {
      // Aquí iría la lógica de traducción real
      // Por ahora, simulamos una traducción
      $translatedText = "[TRADUCIDO] " . $text;

      logSecurityEvent('content_translated', [
        'client_ip' => $clientIp,
        'target_lang' => $targetLang,
        'original_length' => strlen($text),
        'translated_length' => strlen($translatedText)
      ]);

      return [
        'success' => true,
        'translated_text' => $translatedText,
        'target_lang' => $targetLang
      ];
    } catch (Throwable $e) {
      logSecurityEvent('translation_error', [
        'client_ip' => $clientIp,
        'target_lang' => $targetLang,
        'error' => $e->getMessage()
      ]);

      return [
        'success' => false,
        'error' => 'Error en la traducción'
      ];
    }
  }

  /**
   * Maneja las peticiones HTTP para traducción con autenticación requerida
   */
  public static function handleTranslationRequest()
  {
    // Autenticación requerida para traducciones
    $userPayload = \Middleware\JWTMiddleware::requireAuth();
    if (!$userPayload) {
      http_response_code(401);
      echo json_encode(['error' => 'Autenticación requerida para traducciones']);
      return;
    }

    // Solo métodos POST permitidos
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      echo json_encode(['error' => 'Método no permitido']);
      return;
    }

    // Obtener datos de entrada
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['text']) || !isset($input['targetLanguage'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Datos de entrada inválidos']);
      return;
    }

    $text = $input['text'];
    $targetLanguage = $input['targetLanguage'];
    $sourceLanguage = $input['sourceLanguage'] ?? 'es';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Realizar traducción
    $result = self::translateContent($text, $targetLanguage, $clientIp);

    // Responder según el resultado
    if ($result['success']) {
      http_response_code(200);
    } else {
      http_response_code(500);
    }

    header('Content-Type: application/json');
    echo json_encode($result);
  }
} {
  try {
    // Validar entrada con sanitización mejorada
    if (empty($text) || strlen($text) > 5000) {
      throw new Exception('Texto inválido o demasiado largo (máx. 5000 caracteres)');
    }

    // Sanitizar texto de entrada
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Validar idiomas con lista más restrictiva
    $allowedLanguages = ['es', 'en', 'fr', 'de', 'it', 'pt', 'ca'];
    if (!in_array($targetLanguage, $allowedLanguages) || !in_array($sourceLanguage, $allowedLanguages)) {
      throw new Exception('Idioma no soportado. Idiomas permitidos: ' . implode(', ', $allowedLanguages));
    }

    // Si el idioma objetivo es el mismo que el origen, devolver el texto original
    if ($targetLanguage === $sourceLanguage) {
      return [
        'success' => true,
        'translation' => $text,
        'original' => $text,
        'targetLanguage' => $targetLanguage,
        'sourceLanguage' => $sourceLanguage,
        'cached' => true,
        'message' => 'Texto devuelto sin traducción (idiomas iguales)'
      ];
    }

    // Configurar Google Translate con opciones de seguridad mejoradas
    // NOTA: Esta parte requiere la librería stichoza/google-translate-php
    // Si no está instalada, devolver mensaje informativo
    if (!class_exists('\Stichoza\GoogleTranslate\GoogleTranslate')) {
      return [
        'success' => false,
        'error' => 'Servicio de traducción no disponible',
        'translation' => $text,
        'original' => $text,
        'fallback' => true,
        'message' => 'La librería de traducción no está instalada. Contacte al administrador.'
      ];
    }

    // Simular traducción por ahora (reemplazar con Google Translate real cuando esté disponible)
    $translation = "[TRADUCIDO] " . $text;
    if (empty($translation)) {
      throw new Exception('Traducción fallida - resultado vacío');
    }

    // Validar que la traducción no contenga caracteres maliciosos
    $translation = strip_tags($translation);
    $translation = htmlspecialchars($translation, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Log seguro (sin contenido sensible)
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::info('Traducción realizada exitosamente', [
        'source_lang' => $sourceLanguage,
        'target_lang' => $targetLanguage,
        'text_length' => strlen($text),
        'translation_length' => strlen($translation),
        'ip' => $clientIp
      ]);
    }

    return [
      'success' => true,
      'translation' => $translation,
      'original' => $text,
      'targetLanguage' => $targetLanguage,
      'sourceLanguage' => $sourceLanguage,
      'cached' => false,
      'message' => 'Traducción completada exitosamente'
    ];
  } catch (Exception $e) {
    // Log de error seguro
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::error('Error en traducción', [
        'error' => $e->getMessage(),
        'source_lang' => $sourceLanguage ?? 'unknown',
        'target_lang' => $targetLanguage ?? 'unknown',
        'text_length' => isset($text) ? strlen($text) : 0,
        'ip' => $clientIp ?? 'unknown'
      ]);
    }

    // En caso de error, devolver el texto original como fallback seguro
    return [
      'success' => false,
      'error' => 'Error en traducción: ' . $e->getMessage(),
      'translation' => $text ?? '',
      'original' => $text ?? '',
      'fallback' => true,
      'message' => 'Se devuelve el texto original debido a un error en la traducción'
    ];
  }
}

/**
 * Función auxiliar para obtener conexión a BD
 */
function getDatabaseConnection()
{
  static $pdo = null;
  if ($pdo === null) {
    try {
      $pdo = \Utils\Database::getInstance()->getConnection();
    } catch (Exception $e) {
      error_log('Database connection failed in jobs.php: ' . $e->getMessage());
      http_response_code(500);
      echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a base de datos',
        'error' => 'DATABASE_CONNECTION_FAILED'
      ]);
      exit;
    }
  }
  return $pdo;
}

/**
 * Valida y sanitiza entrada de trabajo con validaciones robustas (DUPLICADA - ELIMINAR)
 */
function validateJobInput_DUPLICATE(array $input, bool $isUpdate = false): array
{
  $errors = [];
  $sanitized = [];

  // Campos requeridos para creación
  $requiredFields = $isUpdate ? [] : ['title'];

  foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
      $errors[] = "Campo requerido: {$field}";
    }
  }

  // Validaciones de campos con patrones de seguridad
  $validations = [
    'title' => [
      'required' => !$isUpdate,
      'min' => 5,
      'max' => 100,
      'pattern' => '/^[a-zA-Z0-9\s\-\.,\(\)\p{L}]+$/u', // Permitir caracteres Unicode
      'allow_html' => false
    ],
    'description' => [
      'required' => false,
      'max' => 5000,
      'allow_html' => false
    ],
    'location' => [
      'required' => false,
      'max' => 100,
      'pattern' => '/^[a-zA-Z0-9\s\-\.,\(\)\p{L}]*$/u',
      'allow_html' => false
    ],
    'salary_range' => [
      'required' => false,
      'max' => 50,
      'pattern' => '/^[a-zA-Z0-9\s\-\.,\(\)\$€£¥\p{L}]+$/u',
      'allow_html' => false
    ],
    'department' => [
      'required' => false,
      'max' => 50,
      'pattern' => '/^[a-zA-Z0-9\s\-\p{L}]+$/u',
      'allow_html' => false
    ],
    'requirements' => [
      'required' => false,
      'max' => 2000,
      'allow_html' => false
    ],
    'benefits' => [
      'required' => false,
      'max' => 2000,
      'allow_html' => false
    ],
    'job_type' => [
      'required' => false,
      'max' => 20,
      'allowed_values' => ['full-time', 'part-time', 'contract', 'freelance', 'internship', 'temporary'],
      'allow_html' => false
    ],
    'experience_level' => [
      'required' => false,
      'max' => 20,
      'allowed_values' => ['entry', 'junior', 'mid', 'senior', 'lead', 'executive', 'intern'],
      'allow_html' => false
    ],
    'status' => [
      'required' => false,
      'allowed_values' => ['open', 'closed', 'draft', 'paused', 'cancelled'],
      'allow_html' => false
    ]
  ];

  foreach ($validations as $field => $rules) {
    if (!isset($input[$field])) {
      if ($rules['required']) {
        $errors[] = "Campo requerido: {$field}";
      }
      continue;
    }

    $value = trim($input[$field]);

    // Validar longitud mínima
    if (isset($rules['min']) && strlen($value) < $rules['min']) {
      $errors[] = "Campo {$field} debe tener al menos {$rules['min']} caracteres";
      continue;
    }

    // Validar longitud máxima
    if (isset($rules['max']) && strlen($value) > $rules['max']) {
      $errors[] = "Campo {$field} excede el límite de {$rules['max']} caracteres";
      continue;
    }

    // Validar patrón regex
    if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
      $errors[] = "Campo {$field} contiene caracteres no válidos";
      continue;
    }

    // Validar valores permitidos
    if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'])) {
      $errors[] = "Valor no válido para {$field}";
      continue;
    }

    // Sanitizar contenido
    if ($rules['allow_html'] ?? true) {
      $sanitized[$field] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } else {
      $sanitized[$field] = strip_tags($value);
    }
  }

  return ['errors' => $errors, 'sanitized' => $sanitized];
}

/**
 * Sanitiza datos de trabajo para salida segura con protección XSS mejorada
 */
function sanitizeJobData(array $job): array
{
  $sanitized = [];

  // Campos numéricos - asegurar tipo correcto
  $numericFields = ['id', 'created_by', 'updated_by', 'deleted_by'];
  foreach ($numericFields as $field) {
    if (isset($job[$field])) {
      $sanitized[$field] = (int)$job[$field];
    }
  }

  // Campos de texto - sanitización robusta contra XSS
  $textFields = [
    'title',
    'company_name',
    'location',
    'description',
    'requirements',
    'benefits',
    'salary_range',
    'job_type',
    'department',
    'experience_level',
    'status'
  ];

  foreach ($textFields as $field) {
    if (isset($job[$field])) {
      // Doble sanitización: strip_tags primero, luego htmlspecialchars
      $cleaned = strip_tags($job[$field]);
      $sanitized[$field] = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
  }

  // Fechas - sin cambios, pero validar formato si es necesario
  $dateFields = ['created_at', 'updated_at', 'deleted_at', 'deadline'];
  foreach ($dateFields as $field) {
    if (isset($job[$field])) {
      $sanitized[$field] = $job[$field];
    }
  }

  return $sanitized;
}

// Manejo de rutas
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'] ?? '';

// Endpoint de traducción con autenticación
if (strpos($path, '/translate') !== false || (isset($_GET['action']) && $_GET['action'] === 'translate')) {
  JobTranslate::handleTranslationRequest();
  exit;
}

try {
  $db = getDatabaseConnection();

  switch ($method) {
    case 'GET':
      handleGetJobs($db);
      break;

    case 'POST':
      handleCreateJob($db, $userId, $userRole);
      break;

    case 'PUT':
      handleUpdateJob($db, $userId, $userRole);
      break;

    case 'DELETE':
      handleDeleteJob($db, $userId, $userRole);
      break;

    default:
      ResponseHelper::error('Método no permitido', 405);
      break;
  }
} catch (Exception $e) {
  // Log de error seguro sin exponer información sensible
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::error('Error en jobs API', [
      'error_message' => $e->getMessage(),
      'error_code' => $e->getCode(),
      'user_id' => $userId ?? null,
      'user_role' => $userRole ?? 'guest',
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
      'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
      'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
      'timestamp' => date('Y-m-d H:i:s')
    ]);
  }

  // Respuesta de error genérica para no exponer información sensible
  \Utils\ResponseHelper::error('Error interno del servidor', 500);
}

/**
 * Maneja GET - Lista trabajos o trabajo específico con validaciones de seguridad mejoradas
 */
function handleGetJobs($db)
{
  try {
    // Si se pasa un ID, devolver solo ese trabajo
    if (isset($_GET['id'])) {
      $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
      if ($id === false || $id <= 0) {
        \Utils\ResponseHelper::error('ID inválido', 400);
        return;
      }

      $sql = "SELECT * FROM bt_jobs WHERE id = ? AND status IN ('open', 'published')";
      $stmt = $db->prepare($sql);
      $stmt->execute([$id]);
      $job = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$job) {
        \Utils\ResponseHelper::error('Trabajo no encontrado', 404);
        return;
      }

      $sanitizedJob = sanitizeJobData($job);

      // Log de acceso a trabajo específico
      if (class_exists('\Utils\Logger')) {
        \Utils\Logger::info('Trabajo consultado', [
          'job_id' => $id,
          'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
          'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
      }

      \Utils\ResponseHelper::success('Trabajo encontrado', $sanitizedJob);
      return;
    }

    // Lista de trabajos con paginación segura y validaciones mejoradas
    $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, [
      'options' => ['min_range' => 1, 'max_range' => 100]
    ]);
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT, [
      'options' => ['min_range' => 0, 'max_range' => 10000]
    ]);

    if ($limit === false || $offset === false) {
      \Utils\ResponseHelper::error('Parámetros de paginación inválidos', 400);
      return;
    }

    // Filtros adicionales de seguridad
    $filters = [];
    $params = [];

    // Filtro por departamento (si se proporciona)
    if (isset($_GET['department']) && !empty(trim($_GET['department']))) {
      $department = trim($_GET['department']);
      if (preg_match('/^[a-zA-Z0-9\s\-]+$/', $department)) {
        $filters[] = "department LIKE ?";
        $params[] = "%{$department}%";
      }
    }

    // Filtro por ubicación (si se proporciona)
    if (isset($_GET['location']) && !empty(trim($_GET['location']))) {
      $location = trim($_GET['location']);
      if (preg_match('/^[a-zA-Z0-9\s\-\.,]+$/', $location)) {
        $filters[] = "location LIKE ?";
        $params[] = "%{$location}%";
      }
    }

    // Filtro por tipo de trabajo (si se proporciona)
    if (isset($_GET['job_type']) && !empty(trim($_GET['job_type']))) {
      $jobType = trim($_GET['job_type']);
      $allowedTypes = ['full-time', 'part-time', 'contract', 'freelance', 'internship'];
      if (in_array($jobType, $allowedTypes)) {
        $filters[] = "job_type = ?";
        $params[] = $jobType;
      }
    }

    // Construir consulta con filtros
    $whereClause = "status IN ('open', 'published')";
    if (!empty($filters)) {
      $whereClause .= " AND " . implode(' AND ', $filters);
    }

    $sql = "SELECT * FROM bt_jobs WHERE {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sanitizedJobs = array_map('sanitizeJobData', $jobs);

    // Contar total de trabajos para metadata
    $countSql = "SELECT COUNT(*) as total FROM bt_jobs WHERE {$whereClause}";
    array_pop($params); // Remover offset
    array_pop($params); // Remover limit
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Log de consulta de lista de trabajos
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::info('Lista de trabajos consultada', [
        'total_results' => count($jobs),
        'total_count' => $totalCount,
        'limit' => $limit,
        'offset' => $offset,
        'filters' => array_filter($_GET, function ($k) {
          return in_array($k, ['department', 'location', 'job_type']);
        }, ARRAY_FILTER_USE_KEY),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    \Utils\ResponseHelper::success('Trabajos obtenidos', [
      'jobs' => $sanitizedJobs,
      'pagination' => [
        'limit' => $limit,
        'offset' => $offset,
        'total_count' => $totalCount,
        'has_more' => ($offset + $limit) < $totalCount
      ]
    ]);
  } catch (Exception $e) {
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::error('Error consultando trabajos', [
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }
    \Utils\ResponseHelper::error('Error interno del servidor', 500);
  }
}

/**
 * Maneja POST - Crear trabajo con control de acceso mejorado
 */
function handleCreateJob($db, $userId, $userRole)
{
  try {
    // CONTROL DE ACCESO: Solo admin, hr y recruiter pueden crear trabajos
    if (!in_array($userRole, ['admin', 'hr', 'recruiter'])) {
      logSecurityEvent('unauthorized_job_creation', [
        'user_id' => $userId,
        'user_role' => $userRole,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
      \Utils\ResponseHelper::error('No autorizado para crear trabajos', 403);
      return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
      \Utils\ResponseHelper::error('Datos inválidos - JSON malformado', 400);
      return;
    }

    // Usar validación robusta
    $validation = validateJobInput($input, false);
    if (!empty($validation['errors'])) {
      if (class_exists('\Utils\Logger')) {
        \Utils\Logger::warning('Validación fallida al crear trabajo', [
          'errors' => $validation['errors'],
          'user_id' => $userId
        ]);
      }
      \Utils\ResponseHelper::error('Errores de validación: ' . implode(', ', $validation['errors']), 400);
      return;
    }

    $data = $validation['sanitized'];

    // Verificar que el departamento existe si se proporciona
    if (!empty($data['department'])) {
      $deptStmt = $db->prepare('SELECT id FROM bt_departments WHERE name = ? AND active = 1');
      $deptStmt->execute([$data['department']]);
      if (!$deptStmt->fetch()) {
        \Utils\ResponseHelper::error('Departamento especificado no existe o está inactivo', 400);
        return;
      }
    }

    // Insertar trabajo de forma segura con prepared statements
    $sql = "INSERT INTO bt_jobs (
            title, description, location, salary_range, department,
            requirements, benefits, job_type, experience_level,
            status, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW())";

    $stmt = $db->prepare($sql);
    $success = $stmt->execute([
      $data['title'],
      $data['description'] ?? '',
      $data['location'] ?? '',
      $data['salary_range'] ?? '',
      $data['department'] ?? '',
      $data['requirements'] ?? '',
      $data['benefits'] ?? '',
      $data['job_type'] ?? '',
      $data['experience_level'] ?? '',
      $userId
    ]);

    if (!$success) {
      \Utils\ResponseHelper::error('Error al crear el trabajo', 500);
      return;
    }

    $jobId = $db->lastInsertId();

    // Log de auditoría seguro
    logSecurityEvent('job_created', [
      'job_id' => $jobId,
      'created_by' => $userId,
      'user_role' => $userRole,
      'job_title' => $data['title'],
      'department' => $data['department'] ?? null,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::info('Trabajo creado exitosamente', [
        'job_id' => $jobId,
        'created_by' => $userId,
        'user_role' => $userRole,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    \Utils\ResponseHelper::success('Trabajo creado exitosamente', [
      'id' => $jobId,
      'title' => $data['title'],
      'status' => 'open',
      'created_at' => date('Y-m-d H:i:s'),
      'created_by' => $userId
    ]);
  } catch (Throwable $e) {
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::error('Error creando trabajo', [
        'error' => $e->getMessage(),
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }
    \Utils\ResponseHelper::error('Error interno del servidor', 500);
  }
}

/**
 * Maneja PUT - Actualizar trabajo con control de acceso mejorado
 */
function handleUpdateJob($db, $userId, $userRole)
{
  if (!isset($_GET['id'])) {
    \Utils\ResponseHelper::error('ID requerido para actualización', 400);
    return;
  }

  $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
  if ($id === false || $id <= 0) {
    \Utils\ResponseHelper::error('ID inválido', 400);
    return;
  }

  // CONTROL DE ACCESO: Solo admin y hr pueden actualizar trabajos
  if (!in_array($userRole, ['admin', 'hr'])) {
    logSecurityEvent('unauthorized_job_update', [
      'user_id' => $userId,
      'user_role' => $userRole,
      'job_id' => $id ?? null,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    \Utils\ResponseHelper::error('No autorizado para actualizar trabajos', 403);
    return;
  }

  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) {
    \Utils\ResponseHelper::error('Datos inválidos - JSON malformado', 400);
    return;
  }

  // Usar validación robusta
  $validation = validateJobInput($input, true);
  if (!empty($validation['errors'])) {
    \Utils\ResponseHelper::error('Errores de validación: ' . implode(', ', $validation['errors']), 400);
    return;
  }

  $data = $validation['sanitized'];

  // Verificar que el trabajo existe y obtener información del creador
  try {
    $stmt = $db->prepare("SELECT created_by, status FROM bt_jobs WHERE id = ?");
    $stmt->execute([$id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
      \Utils\ResponseHelper::error('Trabajo no encontrado', 404);
      return;
    }

    // CONTROL DE ACCESO: Solo el creador o admin pueden actualizar
    if ($userRole !== 'admin' && $job['created_by'] != $userId) {
      logSecurityEvent('unauthorized_job_update_attempt', [
        'job_id' => $id,
        'user_id' => $userId,
        'user_role' => $userRole,
        'job_creator' => $job['created_by'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
      \Utils\ResponseHelper::error('No autorizado para actualizar este trabajo', 403);
      return;
    }

    // Construir actualización dinámica con prepared statements
    $updateFields = [];
    $params = [];

    $allowedFields = [
      'title',
      'description',
      'location',
      'salary_range',
      'department',
      'requirements',
      'benefits',
      'job_type',
      'experience_level',
      'status'
    ];

    foreach ($allowedFields as $field) {
      if (isset($data[$field])) {
        $updateFields[] = "{$field} = ?";
        $params[] = $data[$field];
      }
    }

    if (empty($updateFields)) {
      \Utils\ResponseHelper::error('No hay campos para actualizar', 400);
      return;
    }

    $params[] = $id;
    $sql = "UPDATE bt_jobs SET " . implode(', ', $updateFields) . ", updated_at = NOW(), updated_by = ? WHERE id = ?";
    $params[] = $userId;

    $stmt = $db->prepare($sql);
    $success = $stmt->execute($params);

    if (!$success) {
      \Utils\ResponseHelper::error('Error al actualizar el trabajo', 500);
      return;
    }

    // Log de auditoría seguro
    logSecurityEvent('job_updated', [
      'job_id' => $id,
      'updated_by' => $userId,
      'user_role' => $userRole,
      'fields_updated' => array_keys(array_filter($data, function ($k) use ($allowedFields) {
        return in_array($k, $allowedFields);
      }, ARRAY_FILTER_USE_KEY)),
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::info('Trabajo actualizado exitosamente', [
        'job_id' => $id,
        'updated_by' => $userId,
        'user_role' => $userRole,
        'fields_updated' => array_keys(array_filter($data, function ($k) use ($allowedFields) {
          return in_array($k, $allowedFields);
        }, ARRAY_FILTER_USE_KEY)),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    \Utils\ResponseHelper::success('Trabajo actualizado exitosamente');
  } catch (Exception $e) {
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::error('Error actualizando trabajo', [
        'error' => $e->getMessage(),
        'job_id' => $id,
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }
    \Utils\ResponseHelper::error('Error interno del servidor', 500);
  }
}

/**
 * Maneja DELETE - Eliminar trabajo con control de acceso mejorado
 */
function handleDeleteJob($db, $userId, $userRole)
{
  // CONTROL DE ACCESO: Solo admin puede eliminar trabajos
  if ($userRole !== 'admin') {
    logSecurityEvent('unauthorized_job_deletion', [
      'user_id' => $userId,
      'user_role' => $userRole,
      'job_id' => $id ?? null,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    \Utils\ResponseHelper::error('No autorizado para eliminar trabajos', 403);
    return;
  }

  if (!isset($_GET['id'])) {
    \Utils\ResponseHelper::error('ID requerido para eliminación', 400);
    return;
  }

  $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
  if ($id === false || $id <= 0) {
    \Utils\ResponseHelper::error('ID inválido', 400);
    return;
  }

  // Verificar que el trabajo existe
  try {
    $stmt = $db->prepare("SELECT id, title, status FROM bt_jobs WHERE id = ?");
    $stmt->execute([$id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
      \Utils\ResponseHelper::error('Trabajo no encontrado', 404);
      return;
    }

    // Log de auditoría seguro antes de eliminar
    logSecurityEvent('job_deletion_initiated', [
      'job_id' => $id,
      'job_title' => $job['title'],
      'previous_status' => $job['status'],
      'deleted_by' => $userId,
      'user_role' => $userRole,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::info('Trabajo marcado para eliminación', [
        'job_id' => $id,
        'job_title' => $job['title'],
        'previous_status' => $job['status'],
        'deleted_by' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    // Soft delete cambiando status
    $stmt = $db->prepare("UPDATE bt_jobs SET status = 'deleted', deleted_at = NOW(), deleted_by = ? WHERE id = ?");
    $success = $stmt->execute([$userId, $id]);

    if (!$success) {
      \Utils\ResponseHelper::error('Error al eliminar el trabajo', 500);
      return;
    }

    // Log de auditoría seguro de confirmación
    logSecurityEvent('job_deleted', [
      'job_id' => $id,
      'job_title' => $job['title'],
      'deleted_by' => $userId,
      'user_role' => $userRole,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::info('Trabajo eliminado exitosamente', [
        'job_id' => $id,
        'deleted_by' => $userId,
        'user_role' => $userRole,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    \Utils\ResponseHelper::success('Trabajo eliminado exitosamente');
  } catch (Exception $e) {
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::error('Error eliminando trabajo', [
        'error' => $e->getMessage(),
        'job_id' => $id,
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }
    \Utils\ResponseHelper::error('Error interno del servidor', 500);
  }
}
