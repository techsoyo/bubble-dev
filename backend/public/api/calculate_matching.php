<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/calculate-matching
 * Cálculo seguro de matching candidato-trabajo con validación robusta y rate limiting
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 2.0.0 - Security Enhanced
 */

// Configurar headers de seguridad avanzados con CSP avanzado
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), magnetometer=(), gyroscope=(), payment=()');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'none\'; object-src \'none\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'none\'');
header('X-Permitted-Cross-Domain-Policies: none');
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

/**
 * Clase de auditoría de seguridad para cálculos de matching
 */
class MatchingSecurityAuditor
{
  private static $logFile = __DIR__ . '/../../logs/matching_security.log';

  public static function logSecurityEvent(string $event, array $data = [], string $severity = 'INFO'): void
  {
    $timestamp = date('Y-m-d H:i:s');
    $userId = $data['user_id'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $logEntry = [
      'timestamp' => $timestamp,
      'event' => $event,
      'severity' => $severity,
      'user_id' => $userId,
      'ip_address' => $ip,
      'user_agent' => substr($userAgent, 0, 200),
      'data' => json_encode($data, JSON_UNESCAPED_UNICODE)
    ];

    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    // Crear directorio de logs si no existe
    $logDir = dirname(self::$logFile);
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, true);
    }

    file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
  }

  public static function logMatchingCalculation(string $userId, bool $success, float $processingTime = 0): void
  {
    self::logSecurityEvent('MATCHING_CALCULATION', [
      'user_id' => $userId,
      'success' => $success,
      'processing_time' => $processingTime,
      'timestamp' => time()
    ], $success ? 'INFO' : 'WARNING');
  }

  public static function logRateLimitExceeded(string $userId): void
  {
    self::logSecurityEvent('MATCHING_RATE_LIMIT_EXCEEDED', [
      'user_id' => $userId,
      'timestamp' => time()
    ], 'WARNING');
  }

  public static function logValidationError(string $userId, string $field, string $error): void
  {
    self::logSecurityEvent('MATCHING_VALIDATION_ERROR', [
      'user_id' => $userId,
      'field' => $field,
      'error' => $error,
      'timestamp' => time()
    ], 'ERROR');
  }

  public static function logAccessDenied(string $userId, string $reason): void
  {
    self::logSecurityEvent('MATCHING_ACCESS_DENIED', [
      'user_id' => $userId,
      'reason' => $reason,
      'timestamp' => time()
    ], 'ALERT');
  }

  public static function logSuspiciousActivity(string $userId, string $activity, array $details = []): void
  {
    self::logSecurityEvent('MATCHING_SUSPICIOUS_ACTIVITY', [
      'user_id' => $userId,
      'activity' => $activity,
      'details' => $details,
      'timestamp' => time()
    ], 'ALERT');
  }
}

/**
 * Rate limiting mejorado para cálculos de matching basado en archivos
 */
class MatchingRateLimiterV2
{
  private static $rateLimitFile = __DIR__ . '/../../storage/matching_rate_limits.json';
  private static $maxCalculationsPerHour = 100;
  private static $maxCalculationsPerDay = 500;
  private static $windowSeconds = 3600;

  public static function canCalculate(string $userId): bool
  {
    $limits = self::loadRateLimits();
    self::cleanupOldEntries($limits);

    $userEntries = $limits[$userId] ?? [];
    $currentCount = count($userEntries);

    return $currentCount < self::$maxCalculationsPerHour;
  }

  public static function recordCalculation(string $userId): void
  {
    $limits = self::loadRateLimits();
    self::cleanupOldEntries($limits);

    $now = time();
    if (!isset($limits[$userId])) {
      $limits[$userId] = [];
    }

    $limits[$userId][] = $now;
    self::saveRateLimits($limits);
  }

  public static function getRemainingCalculations(string $userId): array
  {
    $limits = self::loadRateLimits();
    self::cleanupOldEntries($limits);

    $userEntries = $limits[$userId] ?? [];
    $currentCount = count($userEntries);

    return [
      'hour' => max(0, self::$maxCalculationsPerHour - $currentCount),
      'day' => max(0, self::$maxCalculationsPerDay - $currentCount)
    ];
  }

  private static function loadRateLimits(): array
  {
    if (!file_exists(self::$rateLimitFile)) {
      return [];
    }

    $data = json_decode(file_get_contents(self::$rateLimitFile), true);
    return $data ?: [];
  }

  private static function saveRateLimits(array $limits): void
  {
    $dir = dirname(self::$rateLimitFile);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    file_put_contents(self::$rateLimitFile, json_encode($limits, JSON_PRETTY_PRINT));
  }

  private static function cleanupOldEntries(array &$limits): void
  {
    $now = time();
    foreach ($limits as $userId => &$entries) {
      $entries = array_filter($entries, function ($timestamp) use ($now) {
        return ($now - $timestamp) < self::$windowSeconds;
      });
      if (empty($entries)) {
        unset($limits[$userId]);
      }
    }
  }
}

/**
 * Validar permisos para cálculos de matching
 */
function validateMatchingPermissions(array $userPayload): bool
{
  $allowedRoles = ['admin', 'hr', 'recruiter', 'staff', 'candidate'];

  if (!isset($userPayload['role']) || !in_array($userPayload['role'], $allowedRoles)) {
    return false;
  }

  return true;
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

// Requerir autenticación JWT mejorada
try {
  $userPayload = \Middleware\JWTMiddleware::requireAuth();
} catch (Exception $e) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Autenticación requerida']);
  exit;
}

$userId = (string)$userPayload['user_id'];
$userRole = $userPayload['role'] ?? 'candidate';

// Validar permisos de usuario
if (!validateMatchingPermissions($userPayload)) {
  MatchingSecurityAuditor::logAccessDenied($userId, 'Rol insuficiente para cálculos de matching: ' . $userRole);
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'No tienes permisos para calcular matching']);
  exit;
}

use Utils\ResponseHelper as Res;
use Utils\Logger;

/**
 * Clase para rate limiting de cálculos de matching
 */
class MatchingRateLimiter
{
  private static $calculations = [];
  private static $maxCalculationsPerHour = 100;
  private static $maxCalculationsPerDay = 500;
  private static $windowHour = 3600;
  private static $windowDay = 86400;

  public static function canCalculate(string $userId): bool
  {
    $currentTime = time();

    // Limpiar entradas antiguas por hora
    self::$calculations[$userId]['hour'] = array_filter(
      self::$calculations[$userId]['hour'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowHour;
      }
    );

    // Limpiar entradas antiguas por día
    self::$calculations[$userId]['day'] = array_filter(
      self::$calculations[$userId]['day'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowDay;
      }
    );

    $calculationsThisHour = count(self::$calculations[$userId]['hour'] ?? []);
    $calculationsToday = count(self::$calculations[$userId]['day'] ?? []);

    return $calculationsThisHour < self::$maxCalculationsPerHour && $calculationsToday < self::$maxCalculationsPerDay;
  }

  public static function recordCalculation(string $userId): void
  {
    $currentTime = time();
    self::$calculations[$userId]['hour'][] = $currentTime;
    self::$calculations[$userId]['day'][] = $currentTime;
  }

  public static function getRemainingCalculations(string $userId): array
  {
    $currentTime = time();

    // Limpiar entradas antiguas
    self::$calculations[$userId]['hour'] = array_filter(
      self::$calculations[$userId]['hour'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowHour;
      }
    );

    self::$calculations[$userId]['day'] = array_filter(
      self::$calculations[$userId]['day'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowDay;
      }
    );

    $calculationsThisHour = count(self::$calculations[$userId]['hour'] ?? []);
    $calculationsToday = count(self::$calculations[$userId]['day'] ?? []);

    return [
      'hour' => max(0, self::$maxCalculationsPerHour - $calculationsThisHour),
      'day' => max(0, self::$maxCalculationsPerDay - $calculationsToday)
    ];
  }
}

/**
 * Configuración de seguridad para matching
 */
class MatchingSecurityConfig
{
  public const MAX_DATA_SIZE = 1024 * 1024; // 1MB máximo por conjunto de datos
  public const MAX_TEXT_LENGTH = 10000; // 10KB máximo por campo de texto
  public const MAX_ARRAY_ITEMS = 100; // Máximo 100 elementos en arrays
  public const REQUIRED_CANDIDATE_FIELDS = ['nombre', 'experiencia', 'habilidades'];
  public const REQUIRED_JOB_FIELDS = ['titulo', 'requisitos', 'descripcion'];
}

/**
 * Validar y sanitizar datos de candidato
 */
function validateCandidateData(array $candidateData): array
{
  if (!is_array($candidateData)) {
    throw new InvalidArgumentException('Los datos del candidato deben ser un array');
  }

  // Verificar tamaño total de datos
  $dataSize = strlen(json_encode($candidateData));
  if ($dataSize > MatchingSecurityConfig::MAX_DATA_SIZE) {
    throw new InvalidArgumentException('Los datos del candidato exceden el tamaño máximo permitido');
  }

  // Validar campos requeridos
  foreach (MatchingSecurityConfig::REQUIRED_CANDIDATE_FIELDS as $field) {
    if (!isset($candidateData[$field])) {
      throw new InvalidArgumentException("Campo requerido faltante en candidato: {$field}");
    }
  }

  $sanitized = [];

  foreach ($candidateData as $key => $value) {
    $key = trim($key);

    // Validar nombre del campo (solo caracteres alfanuméricos y guiones)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
      throw new InvalidArgumentException("Nombre de campo inválido en candidato: {$key}");
    }

    if (is_string($value)) {
      // Sanitizar strings
      $value = trim($value);
      if (strlen($value) > MatchingSecurityConfig::MAX_TEXT_LENGTH) {
        throw new InvalidArgumentException("Campo de texto demasiado largo en candidato: {$key}");
      }
      // Remover caracteres de control y potencialmente peligrosos
      $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
      $sanitized[$key] = $value;
    } elseif (is_array($value)) {
      // Validar arrays
      if (count($value) > MatchingSecurityConfig::MAX_ARRAY_ITEMS) {
        throw new InvalidArgumentException("Array demasiado grande en candidato: {$key}");
      }
      $sanitizedArray = [];
      foreach ($value as $item) {
        if (is_string($item)) {
          $item = trim($item);
          if (strlen($item) > MatchingSecurityConfig::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException("Elemento de array demasiado largo en candidato: {$key}");
          }
          $item = preg_replace('/[\x00-\x1F\x7F]/', '', $item);
          $sanitizedArray[] = $item;
        } elseif (is_numeric($item)) {
          $sanitizedArray[] = $item;
        }
      }
      $sanitized[$key] = $sanitizedArray;
    } elseif (is_numeric($value)) {
      $sanitized[$key] = $value;
    }
    // Ignorar otros tipos de datos
  }

  return $sanitized;
}

/**
 * Validar y sanitizar datos de trabajo
 */
function validateJobData(array $jobData): array
{
  if (!is_array($jobData)) {
    throw new InvalidArgumentException('Los datos del trabajo deben ser un array');
  }

  // Verificar tamaño total de datos
  $dataSize = strlen(json_encode($jobData));
  if ($dataSize > MatchingSecurityConfig::MAX_DATA_SIZE) {
    throw new InvalidArgumentException('Los datos del trabajo exceden el tamaño máximo permitido');
  }

  // Validar campos requeridos
  foreach (MatchingSecurityConfig::REQUIRED_JOB_FIELDS as $field) {
    if (!isset($jobData[$field])) {
      throw new InvalidArgumentException("Campo requerido faltante en trabajo: {$field}");
    }
  }

  $sanitized = [];

  foreach ($jobData as $key => $value) {
    $key = trim($key);

    // Validar nombre del campo
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
      throw new InvalidArgumentException("Nombre de campo inválido en trabajo: {$key}");
    }

    if (is_string($value)) {
      $value = trim($value);
      if (strlen($value) > MatchingSecurityConfig::MAX_TEXT_LENGTH) {
        throw new InvalidArgumentException("Campo de texto demasiado largo en trabajo: {$key}");
      }
      $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
      $sanitized[$key] = $value;
    } elseif (is_array($value)) {
      if (count($value) > MatchingSecurityConfig::MAX_ARRAY_ITEMS) {
        throw new InvalidArgumentException("Array demasiado grande en trabajo: {$key}");
      }
      $sanitizedArray = [];
      foreach ($value as $item) {
        if (is_string($item)) {
          $item = trim($item);
          if (strlen($item) > MatchingSecurityConfig::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException("Elemento de array demasiado largo en trabajo: {$key}");
          }
          $item = preg_replace('/[\x00-\x1F\x7F]/', '', $item);
          $sanitizedArray[] = $item;
        } elseif (is_numeric($item)) {
          $sanitizedArray[] = $item;
        }
      }
      $sanitized[$key] = $sanitizedArray;
    } elseif (is_numeric($value)) {
      $sanitized[$key] = $value;
    }
  }

  return $sanitized;
}

try {
  // Verificar rate limiting mejorado
  $userId = (string)$userPayload['user_id'];

  if (!MatchingRateLimiterV2::canCalculate($userId)) {
    $remaining = MatchingRateLimiterV2::getRemainingCalculations($userId);

    // Auditoría de seguridad - rate limit excedido
    MatchingSecurityAuditor::logRateLimitExceeded($userId);

    // Log intento de rate limit
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('matching_rate_limited', [
        'user_id' => $userId,
        'remaining_hour' => $remaining['hour'],
        'remaining_day' => $remaining['day'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    http_response_code(429);
    Res::error('Límite de cálculos de matching excedido. Intenta más tarde.', 429);
    exit;
  }

  // Obtener y validar entrada JSON
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    http_response_code(400);
    Res::error('JSON inválido', 400);
    exit;
  }

  // Validar campos requeridos
  if (!isset($input['candidate_data']) || !isset($input['job_requirements'])) {
    http_response_code(400);
    Res::error('Se requieren candidate_data y job_requirements', 400);
    exit;
  }

  // Validar y sanitizar datos
  $candidateData = validateCandidateData($input['candidate_data']);
  $jobData = validateJobData($input['job_requirements']);

  // Log cálculo (sin datos sensibles)
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::info('Matching calculation started', [
      'user_id' => $userId,
      'candidate_fields_count' => count($candidateData),
      'job_fields_count' => count($jobData),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  // Crear servicio de IA (usar el servicio disponible)
  if (class_exists('\Services\OllamaService')) {
    $aiService = new \Services\OllamaService();
  } elseif (class_exists('\Services\GroqApiService')) {
    $aiService = new \Services\GroqApiService();
  } else {
    throw new Exception('Servicio de IA no disponible');
  }

  // Calcular matching con timeout de seguridad
  $startTime = microtime(true);
  $result = $aiService->calculateMatching($candidateData, $jobData);
  $processingTime = microtime(true) - $startTime;

  // Verificar tiempo de procesamiento
  if ($processingTime > 30) { // 30 segundos máximo
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('matching_processing_timeout', [
        'user_id' => $userId,
        'processing_time' => $processingTime,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }
  }

  // Registrar cálculo exitoso
  MatchingRateLimiterV2::recordCalculation($userId);

  // Auditoría de seguridad - cálculo exitoso
  MatchingSecurityAuditor::logMatchingCalculation($userId, true, $processingTime);

  // Preparar respuesta segura (sin datos sensibles en metadatos)
  $response = [
    'calculation_timestamp' => date('c'),
    'processing_time' => round($processingTime, 2),
    'input_summary' => [
      'candidate_fields' => count($candidateData),
      'job_fields' => count($jobData)
    ]
  ];

  // Agregar resultado del cálculo si existe
  if (is_array($result)) {
    $response = array_merge($response, $result);
  }

  // Log éxito
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::info('Matching calculation completed', [
      'user_id' => $userId,
      'processing_time' => round($processingTime, 2),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  Res::success('Matching calculado correctamente', $response);
} catch (InvalidArgumentException $e) {
  // Auditoría de seguridad - error de validación
  MatchingSecurityAuditor::logValidationError($userId, 'input_data', $e->getMessage());

  // Log error de validación
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::warning('Matching validation error', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'error' => $e->getMessage(),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  http_response_code(400);
  Res::error('Datos inválidos: ' . $e->getMessage(), 400);
} catch (Exception $e) {
  // Auditoría de seguridad - error general
  MatchingSecurityAuditor::logMatchingCalculation($userId, false);

  // Log error general (sin información sensible)
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::error('Matching calculation error', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'error_type' => get_class($e),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  http_response_code(500);
  Res::error('Error interno al calcular matching', 500);
}
