<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/candidates
 * Gestiona operaciones CRUD de candidatos de forma segura
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
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CSP avanzado para API de candidatos
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'none'; img-src 'self' data: https:; font-src 'none'; connect-src 'self'; media-src 'none'; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; form-action 'self'; upgrade-insecure-requests; block-all-mixed-content");

// Headers adicionales de seguridad
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('X-DNS-Prefetch-Control: off');
header('X-Requested-With: XMLHttpRequest');

// Configurar CORS seguro con validación estricta
$allowedOrigins = [
    'https://bubble-talents.com',
    'https://www.bubble-talents.com',
    'https://app.bubble-talents.com',
    'https://localhost:3000',  // Para desarrollo
    'https://127.0.0.1:3000'   // Para desarrollo
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Access-Control-Expose-Headers: X-Total-Count, X-Rate-Limit-Remaining');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autenticación requerida - obtener payload del usuario
$userPayload = \Middleware\JWTMiddleware::requireAuth();
if (!$userPayload) {
    exit; // El middleware ya maneja la respuesta de error
}

$userId = (int)$userPayload['user_id'];
$userRole = $userPayload['role'] ?? 'candidate';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    \Middleware\CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// Cargar dependencias específicas
require_once __DIR__ . '/../../src/Middleware/SecurityMiddleware.php';
require_once __DIR__ . '/../../src/Middleware/ValidationMiddleware.php';

use Middleware\ValidationMiddleware;
use Utils\Logger;
use Utils\ResponseHelper;

/**
 * Sistema avanzado de rate limiting para operaciones con candidatos
 */
class CandidatesRateLimiter
{
    private static $operations = [];
    private static $maxReadPerHour = 60;      // Lectura: 60 por hora
    private static $maxCreatePerHour = 10;    // Creación: 10 por hora
    private static $maxUpdatePerHour = 30;    // Actualización: 30 por hora
    private static $maxDeletePerHour = 5;     // Eliminación: 5 por hora
    private static $maxReadPerDay = 200;      // Lectura: 200 por día
    private static $maxCreatePerDay = 20;     // Creación: 20 por día
    private static $maxUpdatePerDay = 100;    // Actualización: 100 por día
    private static $maxDeletePerDay = 10;     // Eliminación: 10 por día
    private static $windowHour = 3600;
    private static $windowDay = 86400;

    /**
     * Verifica si una operación puede ejecutarse
     */
    public static function canOperate(string $userId, string $operation): array
    {
        $currentTime = time();

        // Limpiar entradas antiguas
        self::cleanupOldEntries($userId, $currentTime);

        $operationsThisHour = count(self::$operations[$userId]['hour'][$operation] ?? []);
        $operationsToday = count(self::$operations[$userId]['day'][$operation] ?? []);

        // Determinar límites según operación
        $limits = self::getLimitsForOperation($operation);
        $canOperate = $operationsThisHour < $limits['hour'] && $operationsToday < $limits['day'];

        return [
            'allowed' => $canOperate,
            'remaining_hour' => max(0, $limits['hour'] - $operationsThisHour),
            'remaining_day' => max(0, $limits['day'] - $operationsToday),
            'reset_time_hour' => $currentTime + $limits['window'],
            'reset_time_day' => $currentTime + self::$windowDay
        ];
    }

    /**
     * Registra una operación ejecutada
     */
    public static function recordOperation(string $userId, string $operation): void
    {
        $currentTime = time();
        if (!isset(self::$operations[$userId])) {
            self::$operations[$userId] = ['hour' => [], 'day' => []];
        }
        if (!isset(self::$operations[$userId]['hour'][$operation])) {
            self::$operations[$userId]['hour'][$operation] = [];
        }
        if (!isset(self::$operations[$userId]['day'][$operation])) {
            self::$operations[$userId]['day'][$operation] = [];
        }

        self::$operations[$userId]['hour'][$operation][] = $currentTime;
        self::$operations[$userId]['day'][$operation][] = $currentTime;
    }

    /**
     * Obtiene los límites para una operación específica
     */
    private static function getLimitsForOperation(string $operation): array
    {
        switch ($operation) {
            case 'read':
                return ['hour' => self::$maxReadPerHour, 'day' => self::$maxReadPerDay, 'window' => self::$windowHour];
            case 'create':
                return ['hour' => self::$maxCreatePerHour, 'day' => self::$maxCreatePerDay, 'window' => self::$windowHour];
            case 'update':
                return ['hour' => self::$maxUpdatePerHour, 'day' => self::$maxUpdatePerDay, 'window' => self::$windowHour];
            case 'delete':
                return ['hour' => self::$maxDeletePerHour, 'day' => self::$maxDeletePerDay, 'window' => self::$windowHour];
            default:
                return ['hour' => 10, 'day' => 50, 'window' => self::$windowHour];
        }
    }

    /**
     * Limpia entradas antiguas de rate limiting
     */
    private static function cleanupOldEntries(string $userId, int $currentTime): void
    {
        if (!isset(self::$operations[$userId])) {
            return;
        }

        $operations = ['read', 'create', 'update', 'delete'];

        foreach ($operations as $operation) {
            // Limpiar por hora
            if (isset(self::$operations[$userId]['hour'][$operation])) {
                self::$operations[$userId]['hour'][$operation] = array_filter(
                    self::$operations[$userId]['hour'][$operation],
                    function ($timestamp) use ($currentTime) {
                        return ($currentTime - $timestamp) < self::$windowHour;
                    }
                );
            }

            // Limpiar por día
            if (isset(self::$operations[$userId]['day'][$operation])) {
                self::$operations[$userId]['day'][$operation] = array_filter(
                    self::$operations[$userId]['day'][$operation],
                    function ($timestamp) use ($currentTime) {
                        return ($currentTime - $timestamp) < self::$windowDay;
                    }
                );
            }
        }
    }
}

// Inicializar logger
Logger::init();

// Configuración de seguridad específica para candidatos con validaciones avanzadas
$securityConfig = [
    'rate_limit_type' => 'advanced',
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'max_size' => 2097152, // 2MB
    'csrf_protection' => true,
    'input_validation' => true,
    'output_sanitization' => true,
    'audit_logging' => true,
    'file_upload_validation' => true
];

$method = $_SERVER['REQUEST_METHOD'];

// Inicializar variables de rate limiting
$readLimit = ['allowed' => true, 'remaining_time' => 0];
$createLimit = ['allowed' => true, 'remaining_time' => 0];
$updateLimit = ['allowed' => true, 'remaining_time' => 0];
$deleteLimit = ['allowed' => true, 'remaining_time' => 0];

try {
    $pdo = getDBConnection();

    // Verificar rate limiting para operaciones de escritura con validación avanzada
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $userIdString = (string)$userId;
        $operation = match ($method) {
            'POST' => 'create',
            'PUT' => 'update',
            'DELETE' => 'delete',
            default => 'unknown'
        };

        $rateLimitResult = CandidatesRateLimiter::canOperate($userIdString, $operation);

        // Actualizar variables globales de rate limiting
        ${$operation . 'Limit'} = $rateLimitResult;

        if (!$rateLimitResult['allowed']) {
            // Log intento de rate limit
            Logger::security('candidates_rate_limited', [
                'user_id' => $userId,
                'method' => $method,
                'operation' => $operation,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'remaining_hour' => $rateLimitResult['remaining_hour'],
                'remaining_day' => $rateLimitResult['remaining_day']
            ]);

            ResponseHelper::error('Límite de operaciones excedido. Intente más tarde.', 429);
            exit;
        }

        // Registrar la operación si es permitida
        CandidatesRateLimiter::recordOperation($userIdString, $operation);
    }

    // Verificar rate limiting para operaciones de lectura
    if ($method === 'GET') {
        $userIdString = (string)$userId;
        $rateLimitResult = CandidatesRateLimiter::canOperate($userIdString, 'read');
        $readLimit = $rateLimitResult;

        if (!$rateLimitResult['allowed']) {
            Logger::security('candidates_read_rate_limited', [
                'user_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'remaining_hour' => $rateLimitResult['remaining_hour'],
                'remaining_day' => $rateLimitResult['remaining_day']
            ]);

            ResponseHelper::error('Límite de consultas excedido. Intente más tarde.', 429);
            exit;
        }

        CandidatesRateLimiter::recordOperation($userIdString, 'read');
    }

    switch ($method) {
        case 'GET':
            handleGetRequest($pdo, $userId, $userRole);
            break;

        case 'POST':
            handlePostRequest($pdo, $userId, $userRole);
            break;

        case 'PUT':
            handlePutRequest($pdo, $userId, $userRole);
            break;

        case 'DELETE':
            handleDeleteRequest($pdo, $userId, $userRole);
            break;

        default:
            ResponseHelper::error('Método no permitido', 405);
            break;
    }
} catch (PDOException $e) {
    Logger::error('Error de base de datos en candidates.php', [], $e);
    ResponseHelper::error('Error de base de datos', 500);
} catch (Exception $e) {
    Logger::error('Error general en candidates.php', [], $e);
    ResponseHelper::error('Error interno del servidor', 500);
}

/**
 * Maneja solicitudes GET con control de acceso
 */
function handleGetRequest($pdo, $userId, $userRole)
{
    if (isset($_GET['id'])) {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            ResponseHelper::error('ID inválido', 400);
            return;
        }

        // CONTROL DE ACCESO: Solo el propio candidato, admin o hr pueden ver datos específicos
        if ($userRole === 'candidate' && $id !== $userId) {
            ResponseHelper::error('No autorizado para ver este candidato', 403);
            return;
        }

        getSingleCandidate($pdo, $id);
    } else {
        // CONTROL DE ACCESO: Solo admin y hr pueden ver todos los candidatos
        if (!in_array($userRole, ['admin', 'hr', 'recruiter'])) {
            ResponseHelper::error('No autorizado para ver lista de candidatos', 403);
            return;
        }

        getAllCandidates($pdo);
    }
}

/**
 * Obtiene un candidato específico
 */
function getSingleCandidate($pdo, $id)
{
    // ID ya validado por el caller (entero > 0)
    $stmt = $pdo->prepare("
SELECT c.*,
d.name as department_name,
dc.name as department_category_name,
GROUP_CONCAT(DISTINCT cs.skill) as skills
FROM bt_candidates c
LEFT JOIN bt_departments d ON c.department_id = d.id
LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
LEFT JOIN bt_candidate_skills cs ON c.id = cs.candidate_id
WHERE c.id = :id
GROUP BY c.id, d.name, dc.name
");

    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $candidate = $stmt->fetch();

    if ($candidate) {
        // Sanitizar datos de salida
        $candidateFormatted = sanitizeCandidateOutput($candidate);

        Logger::info('Candidato consultado', ['candidate_id' => $id]);
        ResponseHelper::success('Candidato encontrado', $candidateFormatted);
    } else {
        Logger::warning('Candidato no encontrado', ['candidate_id' => $id]);
        ResponseHelper::error('Candidato no encontrado', 404);
    }
}

/**
 * Obtiene todos los candidatos con paginación
 */
function getAllCandidates($pdo)
{
    // Validar parámetros de paginación
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
    $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) ?: 10;
    $offset = ($page - 1) * $limit;

    // Construir filtros seguros
    $whereConditions = [];
    $params = [':limit' => $limit, ':offset' => $offset];

    // Filtro por estado
    if (!empty($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'pending'])) {
        $whereConditions[] = 'c.status = :status';
        $params[':status'] = $_GET['status'];
    }

    // Filtro por departamento
    if (!empty($_GET['department_id']) && filter_var($_GET['department_id'], FILTER_VALIDATE_INT)) {
        $whereConditions[] = 'c.department_id = :department_id';
        $params[':department_id'] = $_GET['department_id'];
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $stmt = $pdo->prepare("
SELECT c.*,
d.name as department_name,
dc.name as department_category_name,
GROUP_CONCAT(DISTINCT cs.skill) as skills
FROM bt_candidates c
LEFT JOIN bt_departments d ON c.department_id = d.id
LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
LEFT JOIN bt_candidate_skills cs ON c.id = cs.candidate_id
{$whereClause}
GROUP BY c.id, d.name, dc.name
ORDER BY c.created_at DESC
LIMIT :limit OFFSET :offset
");

    // Bind parameters con tipos específicos
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();
    $candidates = $stmt->fetchAll();

    // Sanitizar todos los candidatos
    $candidatesFormatted = array_map('sanitizeCandidateOutput', $candidates);

    // Contar total para paginación
    $countParams = array_filter($params, function ($key) {
        return !in_array($key, [':limit', ':offset']);
    }, ARRAY_FILTER_USE_KEY);

    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM bt_candidates c {$whereClause}");
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    Logger::info('Lista de candidatos consultada', [
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);

    ResponseHelper::success('Candidatos obtenidos exitosamente', [
        'candidates' => $candidatesFormatted,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Maneja solicitudes POST con validación avanzada y auditoría
 */
function handlePostRequest($pdo, $userId, $userRole)
{
    // CONTROL DE ACCESO: Solo admin y hr pueden crear candidatos
    if (!in_array($userRole, ['admin', 'hr', 'recruiter'])) {
        logSecurityEvent('unauthorized_create_attempt', [
            'user_id' => $userId,
            'user_role' => $userRole,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        ResponseHelper::error('No autorizado para crear candidatos', 403);
        return;
    }

    $input = ResponseHelper::getJsonInput();
    if ($input === null) {
        return;
    }

    // Validación avanzada con la función dedicada
    $validation = validateCandidateInput($input, false);
    if (!empty($validation['errors'])) {
        Logger::warning('Validación fallida al crear candidato', [
            'errors' => $validation['errors'],
            'user_id' => $userId
        ]);
        ResponseHelper::error('Error de validación', 422, ['errors' => $validation['errors']]);
        return;
    }

    $validData = $validation['sanitized'];

    // Validación adicional para archivos si existen
    if (!empty($_FILES)) {
        $fileRules = [
            'cv_file' => [
                'required' => false,
                'max_size' => 5 * 1024 * 1024, // 5MB
                'allowed_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ],
                'allowed_extensions' => ['pdf', 'doc', 'docx']
            ],
            'profile_photo' => [
                'required' => false,
                'max_size' => 2 * 1024 * 1024, // 2MB
                'allowed_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/jpg'
                ],
                'allowed_extensions' => ['jpg', 'jpeg', 'png']
            ]
        ];

        $fileErrors = ValidationMiddleware::validateFiles($_FILES, $fileRules);
        if (!empty($fileErrors)) {
            Logger::warning('Validación de archivos fallida al crear candidato', $fileErrors);
            ResponseHelper::error('Error de validación de archivos', 422, $fileErrors);
            return;
        }
    }

    // Verificar email único con prepared statement
    $emailCheck = $pdo->prepare('SELECT id FROM bt_candidates WHERE email = :email');
    $emailCheck->bindParam(':email', $validData['email'], PDO::PARAM_STR);
    $emailCheck->execute();

    if ($emailCheck->fetch()) {
        Logger::warning('Intento de crear candidato con email existente', [
            'email' => $validData['email'],
            'user_id' => $userId
        ]);
        ResponseHelper::error('El email ya está registrado', 409);
        return;
    }

    // Procesar nombre con validación adicional
    $nameParts = explode(' ', trim($validData['name']), 2);
    $firstName = htmlspecialchars($nameParts[0], ENT_QUOTES, 'UTF-8');
    $lastName = isset($nameParts[1]) ? htmlspecialchars($nameParts[1], ENT_QUOTES, 'UTF-8') : '';

    // Hash seguro de contraseña
    $passwordHash = password_hash($validData['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        // Insertar candidato con prepared statement completo
        $stmt = $pdo->prepare('INSERT INTO bt_candidates (
            name, first_name, last_name, email, password_hash,
            phone, location, status, registration_source, created_at,
            department_id, department_category_id, experience_years, salary_expectation
        ) VALUES (
            :name, :first_name, :last_name, :email, :password_hash,
            :phone, :location, :status, :registration_source, NOW(),
            :department_id, :department_category_id, :experience_years, :salary_expectation
        )');

        $stmt->execute([
            ':name' => $validData['name'],
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':email' => $validData['email'],
            ':password_hash' => $passwordHash,
            ':phone' => $validData['phone'] ?? null,
            ':location' => $validData['location'] ?? null,
            ':status' => $validData['status'] ?? 'active',
            ':registration_source' => 'api',
            ':department_id' => $validData['department_id'] ?? null,
            ':department_category_id' => $validData['department_category_id'] ?? null,
            ':experience_years' => $validData['experience_years'] ?? null,
            ':salary_expectation' => $validData['salary_expectation'] ?? null
        ]);

        $newId = (int)$pdo->lastInsertId();

        // Insertar skills si existen con validación adicional
        if (!empty($validData['skills']) && is_array($validData['skills'])) {
            $skillStmt = $pdo->prepare('INSERT INTO bt_candidate_skills (candidate_id, skill) VALUES (:candidate_id, :skill);');

            foreach ($validData['skills'] as $skill) {
                $cleanSkill = htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8');
                if (!empty($cleanSkill) && strlen($cleanSkill) <= 100 && !preg_match('/[<>\"\';]/', $cleanSkill)) {
                    $skillStmt->execute([
                        ':candidate_id' => $newId,
                        ':skill' => $cleanSkill
                    ]);
                }
            }
        }

        $pdo->commit();

        // Log de auditoría de seguridad
        logSecurityEvent('candidate_created', [
            'candidate_id' => $newId,
            'created_by' => $userId,
            'user_role' => $userRole,
            'email' => $validData['email']
        ]);

        Logger::info('Candidato creado exitosamente', [
            'candidate_id' => $newId,
            'email' => $validData['email'],
            'created_by' => $userId
        ]);

        ResponseHelper::success('Candidato creado exitosamente', ['id' => $newId], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error('Error al crear candidato', [
            'email' => $validData['email'],
            'user_id' => $userId
        ], $e);

        ResponseHelper::error('Error al crear candidato', 500);
    }
}

/**
 * Maneja solicitudes PUT con validación avanzada y auditoría
 */
function handlePutRequest($pdo, $userId, $userRole)
{
    if (!isset($_GET['id'])) {
        ResponseHelper::error('ID requerido para actualización', 400);
        return;
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        ResponseHelper::error('ID de candidato inválido', 400);
        return;
    }

    // CONTROL DE ACCESO: Solo el propio candidato, admin o hr pueden actualizar
    if ($userRole === 'candidate' && $id !== $userId) {
        logSecurityEvent('unauthorized_update_attempt', [
            'user_id' => $userId,
            'user_role' => $userRole,
            'target_id' => $id,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        ResponseHelper::error('No autorizado para actualizar este candidato', 403);
        return;
    }

    // CONTROL DE ACCESO: Solo admin y hr pueden cambiar ciertos campos sensibles
    $sensitiveFields = ['status', 'department_id', 'department_category_id'];
    $input = ResponseHelper::getJsonInput();
    if ($input === null) {
        return;
    }

    $hasSensitiveFields = false;
    foreach ($sensitiveFields as $field) {
        if (isset($input[$field])) {
            $hasSensitiveFields = true;
            break;
        }
    }

    if ($hasSensitiveFields && !in_array($userRole, ['admin', 'hr'])) {
        logSecurityEvent('unauthorized_sensitive_field_update', [
            'user_id' => $userId,
            'user_role' => $userRole,
            'target_id' => $id,
            'sensitive_fields' => array_intersect(array_keys($input), $sensitiveFields)
        ]);
        ResponseHelper::error('No autorizado para modificar campos sensibles', 403);
        return;
    }

    // Validación avanzada con la función dedicada
    $validation = validateCandidateInput($input, true);
    if (!empty($validation['errors'])) {
        Logger::warning('Validación fallida al actualizar candidato', [
            'errors' => $validation['errors'],
            'candidate_id' => $id,
            'user_id' => $userId
        ]);
        ResponseHelper::error('Error de validación', 422, ['errors' => $validation['errors']]);
        return;
    }

    $validData = $validation['sanitized'];

    try {
        $pdo->beginTransaction();

        // Verificar que el candidato existe
        $checkStmt = $pdo->prepare('SELECT id, email FROM bt_candidates WHERE id = :id');
        $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();

        $existingCandidate = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingCandidate) {
            $pdo->rollBack();
            ResponseHelper::error('Candidato no encontrado', 404);
            return;
        }

        // Verificar email único si se está cambiando
        if (isset($validData['email']) && $validData['email'] !== $existingCandidate['email']) {
            $emailCheck = $pdo->prepare('SELECT id FROM bt_candidates WHERE email = :email AND id != :id');
            $emailCheck->execute([
                ':email' => $validData['email'],
                ':id' => $id
            ]);

            if ($emailCheck->fetch()) {
                $pdo->rollBack();
                Logger::warning('Intento de actualizar candidato con email existente', [
                    'candidate_id' => $id,
                    'email' => $validData['email'],
                    'user_id' => $userId
                ]);
                ResponseHelper::error('El email ya está registrado por otro candidato', 409);
                return;
            }
        }

        // Procesar nombre si se proporciona
        if (isset($validData['name'])) {
            $nameParts = explode(' ', trim($validData['name']), 2);
            $validData['first_name'] = htmlspecialchars($nameParts[0], ENT_QUOTES, 'UTF-8');
            $validData['last_name'] = isset($nameParts[1]) ? htmlspecialchars($nameParts[1], ENT_QUOTES, 'UTF-8') : '';
        }

        // Construir query de actualización dinámicamente
        $updateFields = [];
        $params = [':id' => $id];

        $allowedFields = [
            'name',
            'first_name',
            'last_name',
            'email',
            'phone',
            'location',
            'status',
            'department_id',
            'department_category_id',
            'experience_years',
            'salary_expectation'
        ];

        foreach ($validData as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }

        if (!empty($updateFields)) {
            $updateQuery = 'UPDATE bt_candidates SET ' . implode(', ', $updateFields) . ', updated_at = NOW() WHERE id = :id';
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute($params);
        }

        // Actualizar skills si se proporcionaron
        if (isset($validData['skills']) && is_array($validData['skills'])) {
            // Borrar skills existentes
            $deleteSkillsStmt = $pdo->prepare('DELETE FROM bt_candidate_skills WHERE candidate_id = :id');
            $deleteSkillsStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $deleteSkillsStmt->execute();

            // Insertar nuevos skills
            $skillStmt = $pdo->prepare('INSERT INTO bt_candidate_skills (candidate_id, skill) VALUES (:candidate_id, :skill)');

            foreach ($validData['skills'] as $skill) {
                $cleanSkill = htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8');
                if (!empty($cleanSkill) && strlen($cleanSkill) <= 100 && !preg_match('/[<>\"\';]/', $cleanSkill)) {
                    $skillStmt->execute([
                        ':candidate_id' => $id,
                        ':skill' => $cleanSkill
                    ]);
                }
            }
        }

        $pdo->commit();

        // Log de auditoría de seguridad
        logSecurityEvent('candidate_updated', [
            'candidate_id' => $id,
            'updated_by' => $userId,
            'user_role' => $userRole,
            'updated_fields' => array_keys($validData)
        ]);

        Logger::info('Candidato actualizado exitosamente', [
            'candidate_id' => $id,
            'updated_by' => $userId,
            'updated_fields' => array_keys($validData)
        ]);

        ResponseHelper::success('Candidato actualizado exitosamente');
    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error('Error al actualizar candidato', [
            'candidate_id' => $id,
            'user_id' => $userId
        ], $e);

        ResponseHelper::error('Error al actualizar candidato', 500);
    }
}

/**
 * Función helper para generar logs de auditoría de seguridad
 */
function logSecurityEvent(string $event, array $context): void
{
    if (!class_exists('\Utils\Logger')) {
        return;
    }

    $securityContext = array_merge($context, [
        'event_type' => 'security',
        'api_endpoint' => 'candidates',
        'timestamp' => date('Y-m-d H:i:s'),
        'session_id' => session_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    \Utils\Logger::info("Security Event: {$event}", $securityContext);
}

/**
 * Valida y sanitiza entrada de candidato con validaciones avanzadas
 */
function validateCandidateInput(array $input, bool $isUpdate = false): array
{
    $errors = [];
    $sanitized = [];

    // Campos requeridos para creación con validación estricta
    $requiredFields = $isUpdate ? [] : ['name', 'email'];

    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            $errors[] = "Campo requerido faltante o vacío: {$field}";
        }
    }

    // Validaciones avanzadas con expresiones regulares mejoradas
    $validations = [
        'name' => [
            'required' => !$isUpdate,
            'type' => 'string',
            'min_length' => 2,
            'max_length' => 100,
            'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\.\']+$/u', // Soporte Unicode completo
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'email' => [
            'required' => !$isUpdate,
            'type' => 'email',
            'max_length' => 255,
            'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'phone' => [
            'required' => false,
            'type' => 'phone',
            'max_length' => 20,
            'pattern' => '/^[\+]?[0-9\s\-\(\)]+$/',
            'no_xss' => true
        ],
        'password' => [
            'required' => false,
            'type' => 'password',
            'min_length' => 8,
            'max_length' => 128,
            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'no_xss' => true
        ],
        'location' => [
            'required' => false,
            'max_length' => 255,
            'allow_html' => false,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'status' => [
            'required' => false,
            'allowed_values' => ['active', 'inactive', 'pending', 'reviewed', 'suspended'],
            'no_xss' => true
        ],
        'department_id' => [
            'required' => false,
            'type' => 'integer',
            'min' => 1,
            'no_xss' => true
        ],
        'department_category_id' => [
            'required' => false,
            'type' => 'integer',
            'min' => 1,
            'no_xss' => true
        ],
        'experience_years' => [
            'required' => false,
            'type' => 'integer',
            'min' => 0,
            'max' => 50,
            'no_xss' => true
        ],
        'salary_expectation' => [
            'required' => false,
            'type' => 'numeric',
            'min' => 0,
            'max' => 1000000,
            'no_xss' => true
        ],
        'skills' => [
            'required' => false,
            'type' => 'array',
            'max_items' => 20,
            'item_max_length' => 100,
            'no_xss' => true,
            'no_sql_injection' => true
        ]
    ];

    foreach ($validations as $field => $rules) {
        if (!isset($input[$field])) {
            if ($rules['required']) {
                $errors[] = "Campo requerido faltante: {$field}";
            }
            continue;
        }

        $value = trim($input[$field]);

        // Validar valores permitidos (lista blanca)
        if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'])) {
            $errors[] = "Valor no válido para {$field}: " . implode(', ', $rules['allowed_values']);
            continue;
        }

        // Validar tipo de dato
        if (isset($rules['type'])) {
            switch ($rules['type']) {
                case 'integer':
                    if (!is_numeric($value) || (int)$value != $value) {
                        $errors[] = "Campo {$field} debe ser un número entero válido";
                        continue 2;
                    }
                    $value = (int)$value;
                    break;
                case 'numeric':
                    if (!is_numeric($value)) {
                        $errors[] = "Campo {$field} debe ser un número válido";
                        continue 2;
                    }
                    $value = (float)$value;
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Campo {$field} debe ser un email válido";
                        continue 2;
                    }
                    break;
                case 'phone':
                    if (!preg_match($rules['pattern'], $value)) {
                        $errors[] = "Campo {$field} debe tener un formato de teléfono válido";
                        continue 2;
                    }
                    break;
                case 'password':
                    if (strlen($value) < $rules['min_length']) {
                        $errors[] = "Campo {$field} debe tener al menos {$rules['min_length']} caracteres";
                        continue 2;
                    }
                    if (!preg_match($rules['pattern'], $value)) {
                        $errors[] = "Campo {$field} debe contener al menos una mayúscula, una minúscula, un número y un carácter especial";
                        continue 2;
                    }
                    break;
                case 'array':
                    if (!is_array($value)) {
                        $errors[] = "Campo {$field} debe ser un array";
                        continue 2;
                    }
                    if (count($value) > $rules['max_items']) {
                        $errors[] = "Campo {$field} no puede tener más de {$rules['max_items']} elementos";
                        continue 2;
                    }
                    // Validar cada elemento del array
                    foreach ($value as $item) {
                        if (!is_string($item) || strlen($item) > $rules['item_max_length']) {
                            $errors[] = "Cada elemento de {$field} debe ser una cadena de máximo {$rules['item_max_length']} caracteres";
                            continue 3;
                        }
                    }
                    break;
            }
        }

        // Validar límites de longitud
        if (isset($rules['min_length']) && is_string($value) && strlen($value) < $rules['min_length']) {
            $errors[] = "Campo {$field} debe tener al menos {$rules['min_length']} caracteres";
            continue;
        }

        if (isset($rules['max_length']) && is_string($value) && strlen($value) > $rules['max_length']) {
            $errors[] = "Campo {$field} excede el límite de {$rules['max_length']} caracteres";
            continue;
        }

        // Validar límites numéricos
        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            $errors[] = "Campo {$field} debe ser al menos {$rules['min']}";
            continue;
        }

        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            $errors[] = "Campo {$field} excede el límite de {$rules['max']}";
            continue;
        }

        // Validar patrón regex
        if (isset($rules['pattern']) && is_string($value) && !preg_match($rules['pattern'], $value)) {
            $errors[] = "Campo {$field} contiene caracteres no válidos";
            continue;
        }

        // Validar protección XSS
        if (isset($rules['no_xss']) && $rules['no_xss']) {
            if (strip_tags($value) !== $value) {
                $errors[] = "Campo {$field} contiene código HTML no permitido";
                continue;
            }
        }

        // Validar protección SQL Injection básica
        if (isset($rules['no_sql_injection']) && $rules['no_sql_injection']) {
            $sqlPatterns = ['/\bUNION\b/i', '/\bSELECT\b/i', '/\bINSERT\b/i', '/\bUPDATE\b/i', '/\bDELETE\b/i', '/\bDROP\b/i', '/\bALTER\b/i'];
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $errors[] = "Campo {$field} contiene caracteres potencialmente peligrosos";
                        continue 2;
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item)) {
                        foreach ($sqlPatterns as $pattern) {
                            if (preg_match($pattern, $item)) {
                                $errors[] = "Campo {$field} contiene elementos con caracteres potencialmente peligrosos";
                                continue 3;
                            }
                        }
                    }
                }
            }
        }

        // Sanitizar contenido
        if (is_string($value)) {
            if ($rules['allow_html'] ?? true) {
                $sanitized[$field] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $sanitized[$field] = strip_tags($value);
            }
        } elseif (is_array($value)) {
            $sanitized[$field] = array_map(function ($item) {
                return htmlspecialchars(strip_tags($item), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }, $value);
        } else {
            $sanitized[$field] = $value;
        }
    }

    return ['errors' => $errors, 'sanitized' => $sanitized];
}

/**
 * Maneja solicitudes DELETE con soft delete y auditoría completa
 */
function handleDeleteRequest($pdo, $userId, $userRole)
{
    // CONTROL DE ACCESO: Solo admin puede eliminar candidatos
    if ($userRole !== 'admin') {
        logSecurityEvent('unauthorized_delete_attempt', [
            'user_id' => $userId,
            'user_role' => $userRole,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        ResponseHelper::error('No autorizado para eliminar candidatos', 403);
        return;
    }

    if (!isset($_GET['id'])) {
        ResponseHelper::error('ID requerido para eliminación', 400);
        return;
    }

    $id = $_GET['id'];
    if (!filter_var($id, FILTER_VALIDATE_INT) && !preg_match('/^cnd-[a-f0-9]+$/', $id)) {
        ResponseHelper::error('ID de candidato inválido', 400);
        return;
    }

    try {
        $pdo->beginTransaction();

        // Verificar que el candidato existe y obtener datos para auditoría
        $checkStmt = $pdo->prepare('
            SELECT id, email, name, status
            FROM bt_candidates
            WHERE id = :id AND deleted_at IS NULL
        ');
        $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();

        $candidate = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$candidate) {
            $pdo->rollBack();
            ResponseHelper::error('Candidato no encontrado', 404);
            return;
        }

        // Soft delete: Marcar como eliminado en lugar de borrar físicamente
        $deleteStmt = $pdo->prepare('
            UPDATE bt_candidates
            SET deleted_at = NOW(), status = "deleted", updated_at = NOW()
            WHERE id = :id
        ');
        $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $success = $deleteStmt->execute();

        if ($success && $deleteStmt->rowCount() > 0) {
            // Opcional: Marcar skills como eliminados también (soft delete)
            $deleteSkillsStmt = $pdo->prepare('
                UPDATE bt_candidate_skills
                SET deleted_at = NOW()
                WHERE candidate_id = :candidate_id
            ');
            $deleteSkillsStmt->bindParam(':candidate_id', $id, PDO::PARAM_INT);
            $deleteSkillsStmt->execute();

            $pdo->commit();

            // Log de auditoría de seguridad
            logSecurityEvent('candidate_deleted', [
                'candidate_id' => $id,
                'deleted_by' => $userId,
                'user_role' => $userRole,
                'candidate_email' => $candidate['email'],
                'candidate_name' => $candidate['name'],
                'deletion_type' => 'soft_delete'
            ]);

            Logger::info('Candidato eliminado exitosamente (soft delete)', [
                'candidate_id' => $id,
                'deleted_by' => $userId,
                'candidate_email' => $candidate['email']
            ]);

            ResponseHelper::success('Candidato eliminado exitosamente');
        } else {
            $pdo->rollBack();
            Logger::error('No se pudo eliminar el candidato', [
                'candidate_id' => $id,
                'user_id' => $userId
            ]);
            ResponseHelper::error('No se pudo eliminar el candidato', 500);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        Logger::error('Error al eliminar candidato', [
            'candidate_id' => $id,
            'user_id' => $userId
        ], $e);

        ResponseHelper::error('Error al eliminar candidato', 500);
    }
}

/**
 * Sanitiza la salida de datos de candidato para prevenir XSS
 */
function sanitizeCandidateOutput(array $candidate): array
{
    $sanitized = [];

    // Campos que requieren sanitización específica
    $textFields = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'location',
        'status',
        'registration_source'
    ];

    foreach ($textFields as $field) {
        if (isset($candidate[$field])) {
            // Doble sanitización: strip_tags primero, luego htmlspecialchars
            $cleaned = strip_tags($candidate[$field]);
            $sanitized[$field] = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    // Campos numéricos (no requieren sanitización XSS)
    $numericFields = [
        'id',
        'department_id',
        'department_category_id',
        'experience_years',
        'salary_expectation'
    ];

    foreach ($numericFields as $field) {
        if (isset($candidate[$field])) {
            $sanitized[$field] = is_numeric($candidate[$field]) ? (float)$candidate[$field] : $candidate[$field];
        }
    }

    // Campos de fecha (no requieren sanitización XSS)
    $dateFields = ['created_at', 'updated_at', 'deleted_at'];
    foreach ($dateFields as $field) {
        if (isset($candidate[$field])) {
            $sanitized[$field] = $candidate[$field];
        }
    }

    // Skills requieren sanitización especial
    if (isset($candidate['skills'])) {
        if (is_array($candidate['skills'])) {
            $sanitized['skills'] = array_map(function ($skill) {
                $cleaned = strip_tags($skill);
                return htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }, $candidate['skills']);
        } elseif (is_string($candidate['skills'])) {
            // Si viene como string JSON, parsearlo y sanitizar
            $skillsArray = json_decode($candidate['skills'], true);
            if (is_array($skillsArray)) {
                $sanitized['skills'] = array_map(function ($skill) {
                    $cleaned = strip_tags($skill);
                    return htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }, $skillsArray);
            }
        }
    }

    // Agregar metadatos de sanitización para auditoría
    $sanitized['_sanitized'] = true;
    $sanitized['_sanitized_at'] = date('Y-m-d H:i:s');
    $sanitized['_sanitized_by'] = 'sanitizeCandidateOutput';

    return $sanitized;
}

/**
 * Valida y procesa archivos de candidato con verificación de seguridad
 */
function validateCandidateFiles(array $files): array
{
    $errors = [];
    $validFiles = [];

    $fileRules = [
        'cv_file' => [
            'max_size' => 5 * 1024 * 1024, // 5MB
            'allowed_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'allowed_extensions' => ['pdf', 'doc', 'docx'],
            'scan_viruses' => true
        ],
        'profile_photo' => [
            'max_size' => 2 * 1024 * 1024, // 2MB
            'allowed_types' => [
                'image/jpeg',
                'image/png',
                'image/jpg'
            ],
            'allowed_extensions' => ['jpg', 'jpeg', 'png'],
            'max_width' => 1000,
            'max_height' => 1000,
            'scan_viruses' => true
        ]
    ];

    foreach ($fileRules as $fieldName => $rules) {
        if (!isset($files[$fieldName]) || $files[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            continue; // Archivo opcional no proporcionado
        }

        $file = $files[$fieldName];

        // Verificar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error al subir archivo {$fieldName}: " . getUploadErrorMessage($file['error']);
            continue;
        }

        // Verificar tamaño
        if ($file['size'] > $rules['max_size']) {
            $errors[] = "Archivo {$fieldName} excede el tamaño máximo permitido";
            continue;
        }

        // Verificar tipo MIME
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $file['tmp_name']);
        finfo_close($fileInfo);

        if (!in_array($mimeType, $rules['allowed_types'])) {
            $errors[] = "Tipo de archivo no permitido para {$fieldName}";
            continue;
        }

        // Verificar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $rules['allowed_extensions'])) {
            $errors[] = "Extensión de archivo no permitida para {$fieldName}";
            continue;
        }

        // Verificar nombre de archivo (prevenir path traversal)
        $originalName = basename($file['name']);
        if ($originalName !== $file['name'] || strpos($originalName, '..') !== false) {
            $errors[] = "Nombre de archivo inválido para {$fieldName}";
            continue;
        }

        // Para imágenes, verificar dimensiones
        if (isset($rules['max_width']) && strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo && ($imageInfo[0] > $rules['max_width'] || $imageInfo[1] > $rules['max_height'])) {
                $errors[] = "Imagen {$fieldName} excede las dimensiones máximas permitidas";
                continue;
            }
        }

        // Escaneo básico de virus (buscar patrones maliciosos)
        if ($rules['scan_viruses']) {
            $fileContent = file_get_contents($file['tmp_name']);
            $maliciousPatterns = [
                '/<script/i',
                '/javascript:/i',
                '/vbscript:/i',
                '/onload=/i',
                '/onerror=/i',
                '/eval\(/i',
                '/%3Cscript/i' // <script codificado
            ];

            foreach ($maliciousPatterns as $pattern) {
                if (preg_match($pattern, $fileContent)) {
                    $errors[] = "Archivo {$fieldName} contiene contenido potencialmente malicioso";
                    continue 2;
                }
            }
        }

        // Archivo válido
        $validFiles[$fieldName] = [
            'tmp_name' => $file['tmp_name'],
            'name' => $originalName,
            'type' => $mimeType,
            'size' => $file['size'],
            'extension' => $extension
        ];
    }

    return ['errors' => $errors, 'valid_files' => $validFiles];
}

/**
 * Obtiene mensaje de error de upload en español
 */
function getUploadErrorMessage(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'El archivo excede el tamaño máximo permitido por el servidor';
        case UPLOAD_ERR_FORM_SIZE:
            return 'El archivo excede el tamaño máximo permitido por el formulario';
        case UPLOAD_ERR_PARTIAL:
            return 'El archivo se subió parcialmente';
        case UPLOAD_ERR_NO_FILE:
            return 'No se seleccionó ningún archivo';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Falta el directorio temporal';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Error al escribir el archivo en el disco';
        case UPLOAD_ERR_EXTENSION:
            return 'Una extensión de PHP detuvo la subida del archivo';
        default:
            return 'Error desconocido al subir el archivo';
    }
}
