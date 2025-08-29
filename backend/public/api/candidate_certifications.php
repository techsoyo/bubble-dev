<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/candidate-certifications
 * Gestión segura de certificaciones de candidatos con validación robusta
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
 * Clase para rate limiting de operaciones con certificaciones
 */
class CertificationsRateLimiter
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
 * Configuración de seguridad para certificaciones
 */
class CertificationsSecurityConfig
{
    public const MAX_CERTIFICATION_NAME_LENGTH = 255;
    public const MAX_ISSUER_LENGTH = 255;
    public const MAX_CERTIFICATIONS_PER_CANDIDATE = 50;
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
 * Validar y sanitizar nombre de certificación
 */
function validateCertificationName(string $name): string
{
    $name = trim($name);

    if (empty($name)) {
        throw new InvalidArgumentException('Nombre de certificación requerido');
    }

    if (strlen($name) > CertificationsSecurityConfig::MAX_CERTIFICATION_NAME_LENGTH) {
        throw new InvalidArgumentException('Nombre de certificación demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $name)) {
        throw new InvalidArgumentException('Nombre de certificación contiene caracteres no permitidos');
    }

    return $name;
}

/**
 * Validar y sanitizar emisor
 */
function validateIssuer(string $issuer): string
{
    $issuer = trim($issuer);

    if (empty($issuer)) {
        throw new InvalidArgumentException('Emisor requerido');
    }

    if (strlen($issuer) > CertificationsSecurityConfig::MAX_ISSUER_LENGTH) {
        throw new InvalidArgumentException('Nombre del emisor demasiado largo');
    }

    // Remover caracteres de control y potencialmente peligrosos
    $issuer = preg_replace('/[\x00-\x1F\x7F]/', '', $issuer);

    // Validar que no contenga caracteres peligrosos
    if (preg_match('/[<>\"\';&]/', $issuer)) {
        throw new InvalidArgumentException('Emisor contiene caracteres no permitidos');
    }

    return $issuer;
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
    $minDate = (new DateTime())->modify('-50 years');
    $maxDate = (new DateTime())->modify('+10 years');

    if ($dateTime < $minDate || $dateTime > $maxDate) {
        throw new InvalidArgumentException('Fecha fuera del rango permitido');
    }

    return $date;
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
 * Verificar límite de certificaciones por candidato
 */
function checkCertificationsLimit(int $candidateId): void
{
    $db = \Utils\Database::getInstance()->getConnection();

    $stmt = $db->prepare('SELECT COUNT(*) FROM bt_candidate_certifications WHERE candidate_id = ?');
    $stmt->execute([$candidateId]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= CertificationsSecurityConfig::MAX_CERTIFICATIONS_PER_CANDIDATE) {
        throw new Exception('Límite máximo de certificaciones alcanzado para este candidato');
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Verificar método permitido
    if (!in_array($method, CertificationsSecurityConfig::ALLOWED_METHODS)) {
        http_response_code(405);
        Res::error('Método no permitido', 405);
        exit;
    }

    // Verificar rate limiting para operaciones de escritura
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $userId = (string)$userPayload['user_id'];

        if (!CertificationsRateLimiter::canOperate($userId)) {
            // Log intento de rate limit
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::security('certifications_rate_limited', [
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
            // Obtener certificaciones de un candidato
            $candidateId = $_GET['candidate_id'] ?? null;

            if (!$candidateId) {
                http_response_code(400);
                Res::error('ID de candidato requerido', 400);
                exit;
            }

            $candidateId = validateCandidateId($candidateId);
            verifyCandidateAccess($candidateId, $userPayload);

            $stmt = $db->prepare('
                SELECT id, candidate_id, certification_name, issuer, issue_date, expiry_date, created_at
                FROM bt_candidate_certifications
                WHERE candidate_id = ?
                ORDER BY issue_date DESC, created_at DESC
            ');
            $stmt->execute([$candidateId]);
            $certifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log consulta
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Certifications retrieved', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'count' => count($certifications),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Certificaciones obtenidas correctamente', [
                'items' => $certifications,
                'total' => count($certifications)
            ]);
            break;

        case 'POST':
            // Crear nueva certificación
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                http_response_code(400);
                Res::error('JSON inválido', 400);
                exit;
            }

            // Validar campos requeridos
            $requiredFields = ['candidate_id', 'certification_name', 'issuer', 'issue_date'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    http_response_code(400);
                    Res::error("Campo requerido faltante: {$field}", 400);
                    exit;
                }
            }

            // Validar y sanitizar datos
            $candidateId = validateCandidateId($input['candidate_id']);
            $certificationName = validateCertificationName($input['certification_name']);
            $issuer = validateIssuer($input['issuer']);
            $issueDate = validateDate($input['issue_date']);

            // Validar fecha de expiración opcional
            $expiryDate = null;
            if (!empty($input['expiry_date'])) {
                $expiryDate = validateDate($input['expiry_date']);
                // Verificar que la fecha de expiración sea posterior a la de emisión
                if ($expiryDate <= $issueDate) {
                    http_response_code(400);
                    Res::error('La fecha de expiración debe ser posterior a la fecha de emisión', 400);
                    exit;
                }
            }

            // Verificar permisos y límites
            verifyCandidateAccess($candidateId, $userPayload);
            checkCertificationsLimit($candidateId);

            // Insertar certificación
            $stmt = $db->prepare('
                INSERT INTO bt_candidate_certifications
                (candidate_id, certification_name, issuer, issue_date, expiry_date, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$candidateId, $certificationName, $issuer, $issueDate, $expiryDate]);

            $newId = (int)$db->lastInsertId();

            // Registrar operación
            CertificationsRateLimiter::recordOperation((string)$userPayload['user_id']);

            // Log creación
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Certification created', [
                    'user_id' => $userPayload['user_id'],
                    'candidate_id' => $candidateId,
                    'certification_id' => $newId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            Res::success('Certificación creada correctamente', [
                'id' => $newId,
                'candidate_id' => $candidateId,
                'certification_name' => $certificationName,
                'issuer' => $issuer,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate
            ], 201);
            break;

        case 'PUT':
            // Actualizar certificación (no implementado en el original, pero agregamos estructura)
            http_response_code(501);
            Res::error('Método PUT no implementado', 501);
            break;

        case 'DELETE':
            // Eliminar certificación (no implementado en el original, pero agregamos estructura)
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
        \Utils\Logger::warning('Certifications validation error', [
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
        \Utils\Logger::error('Certifications endpoint error', [
            'user_id' => $userPayload['user_id'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'error' => $e->getMessage(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    http_response_code(500);
    Res::error('Error interno del servidor', 500);
}
