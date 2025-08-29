<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/candidate-experiences
 * Gestión segura de experiencias laborales de candidatos (solo lectura)
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 1.0.0
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
  header('Access-Control-Allow-Methods: GET, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
  header('Access-Control-Max-Age: 86400');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
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

/**
 * Clase para rate limiting de consultas de experiencias
 */
class ExperiencesRateLimiter
{
  private static $queries = [];
  private static $maxQueriesPerHour = 100;
  private static $maxQueriesPerDay = 500;
  private static $windowHour = 3600;
  private static $windowDay = 86400;

  public static function canQuery(string $userId): bool
  {
    $currentTime = time();

    // Limpiar entradas antiguas por hora
    self::$queries[$userId]['hour'] = array_filter(
      self::$queries[$userId]['hour'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowHour;
      }
    );

    // Limpiar entradas antiguas por día
    self::$queries[$userId]['day'] = array_filter(
      self::$queries[$userId]['day'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowDay;
      }
    );

    $queriesThisHour = count(self::$queries[$userId]['hour'] ?? []);
    $queriesToday = count(self::$queries[$userId]['day'] ?? []);

    return $queriesThisHour < self::$maxQueriesPerHour && $queriesToday < self::$maxQueriesPerDay;
  }

  public static function recordQuery(string $userId): void
  {
    $currentTime = time();
    self::$queries[$userId]['hour'][] = $currentTime;
    self::$queries[$userId]['day'][] = $currentTime;
  }
}

/**
 * Configuración de seguridad para experiencias
 */
class ExperiencesSecurityConfig
{
  public const ALLOWED_METHODS = ['GET'];
}

/**
 * Validar y sanitizar ID de candidato
 */
function validateCandidateId($candidateId): int
{
  if (!is_numeric($candidateId)) {
    throw new InvalidArgumentException('ID de candidato inválido');
  }

  $id = (int)$candidateId;
  if ($id <= 0) {
    throw new InvalidArgumentException('ID de candidato debe ser positivo');
  }

  return $id;
}

/**
 * Verificar permisos de acceso al candidato
 */
function verifyCandidateAccess(int $candidateId, array $userPayload): void
{
  $userId = (int)$userPayload['user_id'];
  $userRole = $userPayload['role'] ?? 'candidate';

  // Si es el propio candidato, permitir acceso
  if ($userRole === 'candidate' && $userId === $candidateId) {
    return;
  }

  // Si es admin, hr o recruiter, permitir acceso
  if (in_array($userRole, ['admin', 'hr', 'recruiter'])) {
    return;
  }

  throw new Exception('Acceso denegado al candidato');
}

/**
 * Sanitizar datos de experiencia para salida segura
 */
function sanitizeExperienceData(array $experience): array
{
  return [
    'id' => (int)$experience['id'],
    'candidate_id' => (int)$experience['candidate_id'],
    'company' => htmlspecialchars($experience['company'] ?? '', ENT_QUOTES, 'UTF-8'),
    'position' => htmlspecialchars($experience['position'] ?? '', ENT_QUOTES, 'UTF-8'),
    'start_date' => $experience['start_date'],
    'end_date' => $experience['end_date'],
    'current' => (bool)($experience['current'] ?? false),
    'description' => htmlspecialchars($experience['description'] ?? '', ENT_QUOTES, 'UTF-8'),
    'location' => htmlspecialchars($experience['location'] ?? '', ENT_QUOTES, 'UTF-8')
  ];
}

try {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  // Verificar método permitido
  if (!in_array($method, ExperiencesSecurityConfig::ALLOWED_METHODS)) {
    http_response_code(405);
    Res::error('Método no permitido', 405);
    exit;
  }

  // Verificar rate limiting para consultas
  $userId = (string)$userPayload['user_id'];

  if (!ExperiencesRateLimiter::canQuery($userId)) {
    // Log intento de rate limit
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('experiences_rate_limited', [
        'user_id' => $userId,
        'method' => $method,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    http_response_code(429);
    Res::error('Demasiadas consultas. Intenta más tarde.', 429);
    exit;
  }

  // Obtener experiencias de un candidato
  $candidateId = $_GET['candidate_id'] ?? null;

  if (!$candidateId) {
    // Si no se especifica candidate_id, usar el del usuario autenticado
    $candidateId = (string)$userPayload['user_id'];
  }

  $candidateId = validateCandidateId($candidateId);
  verifyCandidateAccess($candidateId, $userPayload);

  $db = \Utils\Database::getInstance()->getConnection();

  // Obtener experiencias del candidato
  $stmt = $db->prepare('
        SELECT id, candidate_id, company, position, start_date, end_date, current, description, location
        FROM bt_candidate_experiences
        WHERE candidate_id = ?
        ORDER BY COALESCE(end_date, start_date) DESC, id DESC
    ');
  $stmt->execute([$candidateId]);
  $experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Sanitizar datos para salida segura
  $sanitizedExperiences = array_map('sanitizeExperienceData', $experiences);

  // Registrar consulta
  ExperiencesRateLimiter::recordQuery($userId);

  // Log consulta
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::info('Experiences retrieved', [
      'user_id' => $userPayload['user_id'],
      'candidate_id' => $candidateId,
      'count' => count($experiences),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  Res::success('Experiencias obtenidas correctamente', [
    'items' => $sanitizedExperiences,
    'total' => count($sanitizedExperiences)
  ]);
} catch (InvalidArgumentException $e) {
  // Log error de validación
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::warning('Experiences validation error', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'error' => $e->getMessage(),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  http_response_code(400);
  Res::error('Datos inválidos: ' . $e->getMessage(), 400);
} catch (Exception $e) {
  // Log error general
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::error('Experiences endpoint error', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
      'error' => $e->getMessage(),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  http_response_code(500);
  Res::error('Error interno del servidor', 500);
}
