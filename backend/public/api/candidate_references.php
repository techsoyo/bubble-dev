<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/candidate-references
 * Gestión segura de referencias de candidatos con validación robusta
 * Maneja datos sensibles (emails, teléfonos) con protección especial
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
 * Clase para rate limiting de operaciones con referencias
 */
class ReferencesRateLimiter
{
    private static $operations = [];
    private static $maxOperationsPerHour = 30; // Más restrictivo por datos sensibles
    private static $maxOperationsPerDay = 100;
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
 * Configuración de seguridad para referencias
 */
class ReferencesSecurityConfig
{
    public const MAX_REF_NAME_LENGTH = 255;
    public const MAX_REF_COMPANY_LENGTH = 255;
    public const MAX_REF_EMAIL_LENGTH = 255;
    public const MAX_REF_PHONE_LENGTH = 50;
    public const MAX_NOTES_LENGTH = 1000;
    public const MAX_REFERENCES_PER_CANDIDATE = 10;
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
 * Validar y sanitizar nombre de referencia
 */
function validateReferenceName(string $name): string
{
    $name = trim($name);

    if (empty($name)) {
        throw new InvalidArgumentException('Nombre de referencia requerido');
    }

    if (strlen($name) > ReferencesSecurityConfig::MAX_REF_NAME_LENGTH) {
        throw new InvalidArgumentException('Nombre de referencia demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $name)) {
        throw new InvalidArgumentException('Nombre de referencia contiene caracteres no permitidos');
    }

    return $name;
}

/**
 * Validar y sanitizar compañía de referencia
 */
function validateReferenceCompany(?string $company): ?string
{
    if ($company === null || $company === '') {
        return null;
    }

    $company = trim($company);

    if (strlen($company) > ReferencesSecurityConfig::MAX_REF_COMPANY_LENGTH) {
        throw new InvalidArgumentException('Nombre de compañía demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $company = preg_replace('/[\x00-\x1F\x7F]/', '', $company);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $company)) {
        throw new InvalidArgumentException('Nombre de compañía contiene caracteres no permitidos');
    }

    return $company;
}

/**
 * Validar y sanitizar email de referencia
 */
function validateReferenceEmail(?string $email): ?string
{
    if ($email === null || $email === '') {
        return null;
    }

    $email = trim($email);

    if (strlen($email) > ReferencesSecurityConfig::MAX_REF_EMAIL_LENGTH) {
        throw new InvalidArgumentException('Email de referencia demasiado largo');
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Formato de email inválido');
    }

    // Validar dominio del email (no permitir emails temporales o sospechosos)
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    $suspiciousDomains = [
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'temp-mail.org',
        'throwaway.email',
        'yopmail.com',
        'maildrop.cc',
        'tempail.com'
    ];

    if (in_array($domain, $suspiciousDomains)) {
        throw new InvalidArgumentException('Dominio de email no permitido');
    }

    return $email;
}

/**
 * Validar y sanitizar teléfono de referencia
 */
function validateReferencePhone(?string $phone): ?string
{
    if ($phone === null || $phone === '') {
        return null;
    }

    $phone = trim($phone);

    if (strlen($phone) > ReferencesSecurityConfig::MAX_REF_PHONE_LENGTH) {
        throw new InvalidArgumentException('Teléfono de referencia demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $phone = preg_replace('/[\x00-\x1F\x7F]/', '', $phone);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $phone)) {
        throw new InvalidArgumentException('Teléfono de referencia contiene caracteres no permitidos');
    }

    // Permitir solo números, espacios, guiones, paréntesis y el símbolo +
    if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
        throw new InvalidArgumentException('Formato de teléfono inválido');
    }

    return $phone;
}

/**
 * Validar y sanitizar notas
 */
function validateNotes(?string $notes): ?string
{
    if ($notes === null || $notes === '') {
        return null;
    }

    $notes = trim($notes);

    if (strlen($notes) > ReferencesSecurityConfig::MAX_NOTES_LENGTH) {
        throw new InvalidArgumentException('Notas demasiado largas');
    }

    // Remover caracteres de control pero permitir algunos caracteres de formato
    $notes = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $notes);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\';&]/', $notes)) {
        throw new InvalidArgumentException('Notas contienen caracteres no permitidos');
    }

    return $notes;
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
 * Verificar límite de referencias por candidato
 */
function checkReferencesLimit(int $candidateId): void
{
    $db = \Utils\Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT COUNT(*) FROM bt_candidate_references WHERE candidate_id = ?');
    $stmt->execute([$candidateId]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= ReferencesSecurityConfig::MAX_REFERENCES_PER_CANDIDATE) {
        throw new Exception('Límite máximo de referencias alcanzado para este candidato');
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Verificar método permitido
    if (!in_array($method, ReferencesSecurityConfig::ALLOWED_METHODS)) {
        http_response_code(405);
        Res::error('Método no permitido', 405);
        exit;
    }

    // Verificar rate limiting para operaciones de escritura
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $userId = (string)$userPayload['user_id'];

        if (!ReferencesRateLimiter::canOperate($userId)) {
            // Log intento de rate limit
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::security('references_rate_limited', [
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
            // Obtener referencias de un candidato
            $candidateId = $_GET['candidate_id'] ?? null;

            if (!$candidateId) {
                http_response_code(400);
                Res::error('ID de candidato requerido', 400);
                exit;
            }

            $candidateId = validateCandidateId($candidateId);
            verifyCandidateAccess($candidateId, $userPayload);

            $stmt = $db->prepare('
                SELECT id, candidate_id, ref_name, ref_company, ref_email, ref_phone, notes, created_at
                FROM bt_candidate_references
                WHERE candidate_id = ?
                ORDER BY created_at DESC
            ');
            $stmt->execute([$candidateId]);
            $references = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log consulta (sin datos sensibles)
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('References retrieved', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'count' => count($references),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Referencias obtenidas correctamente', [
                'items' => $references,
                'total' => count($references)
            ]);
            break;

        case 'POST':
            // Crear nueva referencia
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                http_response_code(400);
                Res::error('JSON inválido', 400);
                exit;
            }

            // Validar campos requeridos
            $requiredFields = ['candidate_id', 'ref_name'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    http_response_code(400);
                    Res::error("Campo requerido faltante: {$field}", 400);
                    exit;
                }
            }

            // Validar y sanitizar datos
            $candidateId = validateCandidateId($input['candidate_id']);
            $refName = validateReferenceName($input['ref_name']);
            $refCompany = validateReferenceCompany($input['ref_company'] ?? null);
            $refEmail = validateReferenceEmail($input['ref_email'] ?? null);
            $refPhone = validateReferencePhone($input['ref_phone'] ?? null);
            $notes = validateNotes($input['notes'] ?? null);

            // Verificar permisos y límites
            verifyCandidateAccess($candidateId, $userPayload);
            checkReferencesLimit($candidateId);

            // Insertar referencia
            $stmt = $db->prepare('
                INSERT INTO bt_candidate_references
                (candidate_id, ref_name, ref_company, ref_email, ref_phone, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$candidateId, $refName, $refCompany, $refEmail, $refPhone, $notes]);

            $newId = (int)$db->lastInsertId();

            // Registrar operación
            ReferencesRateLimiter::recordOperation((string)$userPayload['user_id']);

            // Log creación (sin datos sensibles)
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Reference created', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'reference_id' => $newId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Referencia creada correctamente', [
                'id' => $newId,
                'candidate_id' => $candidateId,
                'ref_name' => $refName,
                'ref_company' => $refCompany,
                'ref_email' => $refEmail,
                'ref_phone' => $refPhone,
                'notes' => $notes
            ], 201);
            break;

        case 'PUT':
            // Actualizar referencia (no implementado en el original, pero agregamos estructura)
            http_response_code(501);
            Res::error('Método PUT no implementado', 501);
            break;

        case 'DELETE':
            // Eliminar referencia (no implementado en el original, pero agregamos estructura)
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
        \Utils\Logger::warning('References validation error', [
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
        \Utils\Logger::error('References endpoint error', [
            'user_id' => $userPayload['user_id'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'error' => $e->getMessage(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    http_response_code(500);
    Res::error('Error interno del servidor', 500);
}
