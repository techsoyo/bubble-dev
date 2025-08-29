<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/candidate-education
 * Gestión segura de educación de candidatos con validación robusta
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
 * Clase para rate limiting de operaciones con educación
 */
class EducationRateLimiter
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
 * Configuración de seguridad para educación
 */
class EducationSecurityConfig
{
    public const MAX_DEGREE_LENGTH = 255;
    public const MAX_FIELD_OF_STUDY_LENGTH = 255;
    public const MAX_INSTITUTION_LENGTH = 255;
    public const MAX_EDUCATION_LEVEL_LENGTH = 100;
    public const MAX_EDUCATIONS_PER_CANDIDATE = 20;
    public const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'DELETE'];
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
 * Validar y sanitizar título/grado
 */
function validateDegree(string $degree): string
{
    $degree = trim($degree);

    if (empty($degree)) {
        throw new InvalidArgumentException('Título/grado requerido');
    }

    if (strlen($degree) > EducationSecurityConfig::MAX_DEGREE_LENGTH) {
        throw new InvalidArgumentException('Título/grado demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $degree = preg_replace('/[\x00-\x1F\x7F]/', '', $degree);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $degree)) {
        throw new InvalidArgumentException('Título/grado contiene caracteres no permitidos');
    }

    return $degree;
}

/**
 * Validar y sanitizar campo de estudio
 */
function validateFieldOfStudy(?string $fieldOfStudy): ?string
{
    if ($fieldOfStudy === null || $fieldOfStudy === '') {
        return null;
    }

    $fieldOfStudy = trim($fieldOfStudy);

    if (strlen($fieldOfStudy) > EducationSecurityConfig::MAX_FIELD_OF_STUDY_LENGTH) {
        throw new InvalidArgumentException('Campo de estudio demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $fieldOfStudy = preg_replace('/[\x00-\x1F\x7F]/', '', $fieldOfStudy);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $fieldOfStudy)) {
        throw new InvalidArgumentException('Campo de estudio contiene caracteres no permitidos');
    }

    return $fieldOfStudy;
}

/**
 * Validar y sanitizar institución
 */
function validateInstitution(string $institution): string
{
    $institution = trim($institution);

    if (empty($institution)) {
        throw new InvalidArgumentException('Institución requerida');
    }

    if (strlen($institution) > EducationSecurityConfig::MAX_INSTITUTION_LENGTH) {
        throw new InvalidArgumentException('Nombre de institución demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $institution = preg_replace('/[\x00-\x1F\x7F]/', '', $institution);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $institution)) {
        throw new InvalidArgumentException('Institución contiene caracteres no permitidos');
    }

    return $institution;
}

/**
 * Validar y sanitizar nivel educativo
 */
function validateEducationLevel(?string $educationLevel): ?string
{
    if ($educationLevel === null || $educationLevel === '') {
        return null;
    }

    $educationLevel = trim($educationLevel);

    if (strlen($educationLevel) > EducationSecurityConfig::MAX_EDUCATION_LEVEL_LENGTH) {
        throw new InvalidArgumentException('Nivel educativo demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $educationLevel = preg_replace('/[\x00-\x1F\x7F]/', '', $educationLevel);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $educationLevel)) {
        throw new InvalidArgumentException('Nivel educativo contiene caracteres no permitidos');
    }

    return $educationLevel;
}

/**
 * Validar fecha
 */
function validateDate(string $date): string
{
    // Validar formato de fecha
    $dateTime = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Formato de fecha inválido (use YYYY-MM-DD)');
    }

    // Validar rango razonable (no fechas en el futuro muy lejano o pasado muy lejano)
    $now = new DateTime();
    $minDate = (new DateTime())->modify('-80 years');
    $maxDate = (new DateTime())->modify('+5 years');

    if ($dateTime < $minDate || $dateTime > $maxDate) {
        throw new InvalidArgumentException('Fecha fuera del rango permitido');
    }

    return $date;
}

/**
 * Validar fechas de educación (lógica de negocio)
 */
function validateEducationDates(string $startDate, ?string $endDate): void
{
    $startDateTime = new DateTime($startDate);
    $now = new DateTime();

    // La fecha de inicio no puede ser en el futuro
    if ($startDateTime > $now) {
        throw new InvalidArgumentException('La fecha de inicio no puede ser en el futuro');
    }

    if ($endDate !== null) {
        $endDateTime = new DateTime($endDate);

        // La fecha de fin debe ser posterior a la de inicio
        if ($endDateTime <= $startDateTime) {
            throw new InvalidArgumentException('La fecha de fin debe ser posterior a la fecha de inicio');
        }

        // La fecha de fin no puede ser demasiado en el futuro
        $maxEndDate = (new DateTime())->modify('+10 years');
        if ($endDateTime > $maxEndDate) {
            throw new InvalidArgumentException('La fecha de fin no puede ser más de 10 años en el futuro');
        }
    }
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
 * Verificar límite de registros de educación por candidato
 */
function checkEducationLimit(int $candidateId): void
{
    $db = \Utils\Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT COUNT(*) FROM bt_candidate_education WHERE candidate_id = ?');
    $stmt->execute([$candidateId]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= EducationSecurityConfig::MAX_EDUCATIONS_PER_CANDIDATE) {
        throw new Exception('Límite máximo de registros de educación alcanzado para este candidato');
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Verificar método permitido
    if (!in_array($method, EducationSecurityConfig::ALLOWED_METHODS)) {
        http_response_code(405);
        Res::error('Método no permitido', 405);
        exit;
    }

    // Verificar rate limiting para operaciones de escritura
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $userId = (string)$userPayload['user_id'];

        if (!EducationRateLimiter::canOperate($userId)) {
            // Log intento de rate limit
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::security('education_rate_limited', [
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
            // Obtener educación de un candidato
            $candidateId = $_GET['candidate_id'] ?? null;

            if (!$candidateId) {
                http_response_code(400);
                Res::error('ID de candidato requerido', 400);
                exit;
            }

            $candidateId = validateCandidateId($candidateId);
            verifyCandidateAccess($candidateId, $userPayload);

            $stmt = $db->prepare('
                SELECT id, candidate_id, degree, field_of_study, institution, start_date, end_date, education_level, created_at
                FROM bt_candidate_education
                WHERE candidate_id = ?
                ORDER BY COALESCE(end_date, start_date) DESC, created_at DESC
            ');
            $stmt->execute([$candidateId]);
            $educations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log consulta
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Education records retrieved', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'count' => count($educations),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Registros de educación obtenidos correctamente', [
                'items' => $educations,
                'total' => count($educations)
            ]);
            break;

        case 'POST':
            // Crear nuevo registro de educación
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                http_response_code(400);
                Res::error('JSON inválido', 400);
                exit;
            }

            // Validar campos requeridos
            $requiredFields = ['candidate_id', 'degree', 'institution', 'start_date'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    http_response_code(400);
                    Res::error("Campo requerido faltante: {$field}", 400);
                    exit;
                }
            }

            // Validar y sanitizar datos
            $candidateId = validateCandidateId($input['candidate_id']);
            $degree = validateDegree($input['degree']);
            $fieldOfStudy = validateFieldOfStudy($input['field_of_study'] ?? null);
            $institution = validateInstitution($input['institution']);
            $startDate = validateDate($input['start_date']);
            $educationLevel = validateEducationLevel($input['education_level'] ?? null);

            // Validar fecha de fin opcional
            $endDate = null;
            if (!empty($input['end_date'])) {
                $endDate = validateDate($input['end_date']);
            }

            // Validar lógica de fechas
            validateEducationDates($startDate, $endDate);

            // Verificar permisos y límites
            verifyCandidateAccess($candidateId, $userPayload);
            checkEducationLimit($candidateId);

            // Insertar registro de educación
            $stmt = $db->prepare('
                INSERT INTO bt_candidate_education
                (candidate_id, degree, field_of_study, institution, start_date, end_date, education_level, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$candidateId, $degree, $fieldOfStudy, $institution, $startDate, $endDate, $educationLevel]);

            $newId = (int)$db->lastInsertId();

            // Registrar operación
            EducationRateLimiter::recordOperation((string)$userPayload['user_id']);

            // Log creación
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Education record created', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'education_id' => $newId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Registro de educación creado correctamente', [
                'id' => $newId,
                'candidate_id' => $candidateId,
                'degree' => $degree,
                'field_of_study' => $fieldOfStudy,
                'institution' => $institution,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'education_level' => $educationLevel
            ], 201);
            break;

        case 'PUT':
            // Actualizar registro de educación (no implementado en el original, pero agregamos estructura)
            http_response_code(501);
            Res::error('Método PUT no implementado', 501);
            break;

        case 'DELETE':
            // Eliminar registro de educación (no implementado en el original, pero agregamos estructura)
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
        \Utils\Logger::warning('Education validation error', [
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
        \Utils\Logger::error('Education endpoint error', [
            'user_id' => $userPayload['user_id'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'error' => $e->getMessage(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    http_response_code(500);
    Res::error('Error interno del servidor', 500);
}
