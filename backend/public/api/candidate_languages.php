<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/candidate-languages
 * Gestión segura de idiomas de candidatos con validación robusta
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
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
 * Clase para rate limiting de operaciones con idiomas
 */
class LanguagesRateLimiter
{
    private static $operations = [];
    private static $maxOperationsPerHour = 50;
    private static $maxOperationsPerDay = 200;
    private static $windowHour = 3600;
    private static $windowDay = 86400;

    public static function canOperate(string $userId): bool
    {
        $currentTime = time();

        // Limpiar entradas antiguas por hora
        self::$operations[$userId]['hour'] = array_filter(
            self::$operations[$userId]['hour'] ?? [],
            function ($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < self::$windowHour;
            }
        );

        // Limpiar entradas antiguas por día
        self::$operations[$userId]['day'] = array_filter(
            self::$operations[$userId]['day'] ?? [],
            function ($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < self::$windowDay;
            }
        );

        $operationsThisHour = count(self::$operations[$userId]['hour'] ?? []);
        $operationsToday = count(self::$operations[$userId]['day'] ?? []);

        return $operationsThisHour < self::$maxOperationsPerHour && $operationsToday < self::$maxOperationsPerDay;
    }

    public static function recordOperation(string $userId): void
    {
        $currentTime = time();
        self::$operations[$userId]['hour'][] = $currentTime;
        self::$operations[$userId]['day'][] = $currentTime;
    }
}

/**
 * Configuración de seguridad para idiomas
 */
class LanguagesSecurityConfig
{
    public const MAX_LANGUAGE_NAME_LENGTH = 100;
    public const MAX_PROFICIENCY_LEVEL_LENGTH = 50;
    public const MAX_LANGUAGES_PER_CANDIDATE = 15;
    public const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'DELETE'];

    // Niveles de proficiency permitidos
    public const ALLOWED_PROFICIENCY_LEVELS = [
        'beginner',
        'elementary',
        'intermediate',
        'upper-intermediate',
        'advanced',
        'fluent',
        'native',
        'basic',
        'conversational',
        'professional',
        'bilingual'
    ];
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
 * Validar y sanitizar nombre de idioma
 */
function validateLanguage(string $language): string
{
    $language = trim($language);

    if (empty($language)) {
        throw new InvalidArgumentException('Nombre de idioma requerido');
    }

    if (strlen($language) > LanguagesSecurityConfig::MAX_LANGUAGE_NAME_LENGTH) {
        throw new InvalidArgumentException('Nombre de idioma demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $language = preg_replace('/[\x00-\x1F\x7F]/', '', $language);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $language)) {
        throw new InvalidArgumentException('Nombre de idioma contiene caracteres no permitidos');
    }

    // Validar que sea un nombre de idioma razonable (solo letras, espacios, guiones)
    if (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $language)) {
        throw new InvalidArgumentException('Nombre de idioma contiene caracteres no válidos');
    }

    return $language;
}

/**
 * Validar y sanitizar nivel de proficiency
 */
function validateProficiencyLevel(string $proficiencyLevel): string
{
    $proficiencyLevel = trim($proficiencyLevel);

    if (empty($proficiencyLevel)) {
        throw new InvalidArgumentException('Nivel de proficiency requerido');
    }

    if (strlen($proficiencyLevel) > LanguagesSecurityConfig::MAX_PROFICIENCY_LEVEL_LENGTH) {
        throw new InvalidArgumentException('Nivel de proficiency demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $proficiencyLevel = preg_replace('/[\x00-\x1F\x7F]/', '', $proficiencyLevel);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $proficiencyLevel)) {
        throw new InvalidArgumentException('Nivel de proficiency contiene caracteres no permitidos');
    }

    // Convertir a minúsculas para comparación
    $proficiencyLevel = strtolower($proficiencyLevel);

    // Validar que sea un nivel permitido
    if (!in_array($proficiencyLevel, LanguagesSecurityConfig::ALLOWED_PROFICIENCY_LEVELS)) {
        throw new InvalidArgumentException('Nivel de proficiency no válido. Niveles permitidos: ' .
            implode(', ', LanguagesSecurityConfig::ALLOWED_PROFICIENCY_LEVELS));
    }

    return $proficiencyLevel;
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
 * Verificar límite de idiomas por candidato
 */
function checkLanguagesLimit(int $candidateId): void
{
    $db = \Utils\Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT COUNT(*) FROM bt_candidate_languages WHERE candidate_id = ?');
    $stmt->execute([$candidateId]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= LanguagesSecurityConfig::MAX_LANGUAGES_PER_CANDIDATE) {
        throw new Exception('Límite máximo de idiomas alcanzado para este candidato');
    }
}

/**
 * Verificar duplicados de idioma para un candidato
 */
function checkDuplicateLanguage(int $candidateId, string $language): void
{
    $db = \Utils\Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT COUNT(*) FROM bt_candidate_languages WHERE candidate_id = ? AND language = ?');
    $stmt->execute([$candidateId, $language]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        throw new Exception('Este idioma ya está registrado para el candidato');
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Verificar método permitido
    if (!in_array($method, LanguagesSecurityConfig::ALLOWED_METHODS)) {
        http_response_code(405);
        Res::error('Método no permitido', 405);
        exit;
    }

    // Verificar rate limiting para operaciones de escritura
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $userId = (string)$userPayload['user_id'];

        if (!LanguagesRateLimiter::canOperate($userId)) {
            // Log intento de rate limit
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::security('languages_rate_limited', [
                    'user_id' => $userId,
                    'method' => $method,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            http_response_code(429);
            Res::error('Demasiadas operaciones. Intenta más tarde.', 429);
            exit;
        }
    }

    $db = \Utils\Database::getInstance()->getConnection();

    switch ($method) {
        case 'GET':
            // Obtener idiomas de un candidato
            $candidateId = $_GET['candidate_id'] ?? null;

            if (!$candidateId) {
                http_response_code(400);
                Res::error('ID de candidato requerido', 400);
                exit;
            }

            $candidateId = validateCandidateId($candidateId);
            verifyCandidateAccess($candidateId, $userPayload);

            $stmt = $db->prepare('
                SELECT id, candidate_id, language, proficiency_level, created_at
                FROM bt_candidate_languages
                WHERE candidate_id = ?
                ORDER BY created_at DESC
            ');
            $stmt->execute([$candidateId]);
            $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log consulta
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Languages retrieved', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'count' => count($languages),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Idiomas obtenidos correctamente', [
                'items' => $languages,
                'total' => count($languages)
            ]);
            break;

        case 'POST':
            // Crear nuevo registro de idioma
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                http_response_code(400);
                Res::error('JSON inválido', 400);
                exit;
            }

            // Validar campos requeridos
            $requiredFields = ['candidate_id', 'language', 'proficiency_level'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    http_response_code(400);
                    Res::error("Campo requerido faltante: {$field}", 400);
                    exit;
                }
            }

            // Validar y sanitizar datos
            $candidateId = validateCandidateId($input['candidate_id']);
            $language = validateLanguage($input['language']);
            $proficiencyLevel = validateProficiencyLevel($input['proficiency_level']);

            // Verificar permisos y límites
            verifyCandidateAccess($candidateId, $userPayload);
            checkLanguagesLimit($candidateId);
            checkDuplicateLanguage($candidateId, $language);

            // Insertar registro de idioma
            $stmt = $db->prepare('
                INSERT INTO bt_candidate_languages
                (candidate_id, language, proficiency_level, created_at)
                VALUES (?, ?, ?, NOW())
            ');
            $stmt->execute([$candidateId, $language, $proficiencyLevel]);

            $newId = (int)$db->lastInsertId();

            // Registrar operación
            LanguagesRateLimiter::recordOperation((string)$userPayload['user_id']);

            // Log creación
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Language record created', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'language_id' => $newId,
                    'language' => $language,
                    'proficiency_level' => $proficiencyLevel,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Idioma agregado correctamente', [
                'id' => $newId,
                'candidate_id' => $candidateId,
                'language' => $language,
                'proficiency_level' => $proficiencyLevel
            ], 201);
            break;

        case 'PUT':
            // Actualizar registro de idioma (no implementado en el original, pero agregamos estructura)
            http_response_code(501);
            Res::error('Método PUT no implementado', 501);
            break;

        case 'DELETE':
            // Eliminar registro de idioma (no implementado en el original, pero agregamos estructura)
            http_response_code(501);
            Res::error('Método DELETE no implementado', 501);
            break;

        default:
            http_response_code(405);
            Res::error('Método no permitido', 405);
    }
} catch (InvalidArgumentException $e) {
    // Log error de validación
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::warning('Languages validation error', [
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
        \Utils\Logger::error('Languages endpoint error', [
            'user_id' => $userPayload['user_id'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'error' => $e->getMessage(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    http_response_code(500);
    Res::error('Error interno del servidor', 500);
}
