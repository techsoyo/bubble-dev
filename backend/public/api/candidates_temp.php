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
        $operation = match($method) {
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
            'name', 'first_name', 'last_name', 'email', 'phone', 'location',
            'status', 'department_id', 'department_category_id',
            'experience_years', 'salary_expectation'
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


/**
 * Maneja solicitudes DELETE con control de acceso
 */
function handleDeleteRequest($pdo, $userId, $userRole)
{
    // CONTROL DE ACCESO: Solo admin puede eliminar candidatos
    if ($userRole !== 'admin') {
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

        // Verificar que el candidato existe
        $checkStmt = $pdo->prepare('SELECT id FROM bt_candidates WHERE id = :id');
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();

        if (!$checkStmt->fetch()) {
            $pdo->rollBack();
            ResponseHelper::error('Candidato no encontrado', 404);
            return;
        }

        // Borrar skills relacionados primero (foreign key)
        $deleteSkillsStmt = $pdo->prepare('DELETE FROM bt_candidate_skills WHERE candidate_id = :id');
        $deleteSkillsStmt->bindParam(':id', $id);
        $deleteSkillsStmt->execute();

        // Borrar candidato
        $deleteCandidateStmt = $pdo->prepare('DELETE FROM bt_candidates WHERE id = :id');
        $deleteCandidateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $success = $deleteCandidateStmt->execute();

        if ($success && $deleteCandidateStmt->rowCount() > 0) {
            $pdo->commit();
Logger::info('Candidato eliminado exitosamente', ['candidate_id' => $id]);
ResponseHelper::success('Candidato eliminado exitosamente');
        } else {
            $pdo->rollBack();
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
