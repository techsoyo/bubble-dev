<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/recruiters
 * Gestiona operaciones CRUD de reclutadores de forma segura
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 2.0.0 - Security Enhanced
 */

// Configurar headers de seguridad mejorados con CSP avanzado
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
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Implementar rate limiting mejorado para operaciones de recruiters
class RecruiterRateLimiter
{
    private static $rateLimitFile = __DIR__ . '/../../storage/recruiter_rate_limits.json';
    private static $maxCreatePerHour = 2;
    private static $maxUpdatePerHour = 5;
    private static $maxDeletePerHour = 1;
    private static $windowSeconds = 3600; // 1 hora

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
        foreach ($limits as $userId => &$userLimits) {
            foreach ($userLimits as $operation => &$entries) {
                $entries = array_filter($entries, function ($timestamp) use ($now) {
                    return ($now - $timestamp) < self::$windowSeconds;
                });
            }
            // Remover usuarios sin entradas
            if (empty(array_filter($userLimits))) {
                unset($limits[$userId]);
            }
        }
    }

    public static function canPerformOperation(string $userId, string $operation): bool
    {
        $limits = self::loadRateLimits();
        self::cleanupOldEntries($limits);

        $maxLimits = [
            'create' => self::$maxCreatePerHour,
            'update' => self::$maxUpdatePerHour,
            'delete' => self::$maxDeletePerHour
        ];

        if (!isset($maxLimits[$operation])) {
            return true; // Operación no limitada
        }

        $userEntries = $limits[$userId][$operation] ?? [];
        $currentCount = count($userEntries);

        return $currentCount < $maxLimits[$operation];
    }

    public static function recordOperation(string $userId, string $operation): void
    {
        $limits = self::loadRateLimits();
        self::cleanupOldEntries($limits);

        $now = time();
        if (!isset($limits[$userId])) {
            $limits[$userId] = [];
        }
        if (!isset($limits[$userId][$operation])) {
            $limits[$userId][$operation] = [];
        }

        $limits[$userId][$operation][] = $now;
        self::saveRateLimits($limits);
    }

    public static function getRemainingOperations(string $userId, string $operation): int
    {
        $limits = self::loadRateLimits();
        self::cleanupOldEntries($limits);

        $maxLimits = [
            'create' => self::$maxCreatePerHour,
            'update' => self::$maxUpdatePerHour,
            'delete' => self::$maxDeletePerHour
        ];

        if (!isset($maxLimits[$operation])) {
            return PHP_INT_MAX; // Sin límite
        }

        $userEntries = $limits[$userId][$operation] ?? [];
        $currentCount = count($userEntries);

        return max(0, $maxLimits[$operation] - $currentCount);
    }
}

// Autenticación requerida para todas las operaciones con validación mejorada
$userPayload = \Middleware\JWTMiddleware::requireAuth();
if (!$userPayload) {
    // El middleware ya maneja la respuesta de error
    exit;
}

$userId = (int)$userPayload['user_id'];
$userRole = $userPayload['role'] ?? 'candidate';

// Validar que el userId sea válido
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no válido'
    ]);
    exit;
}

// Validar que el rol sea válido
$validRoles = ['admin', 'recruiter', 'hr', 'candidate'];
if (!in_array($userRole, $validRoles)) {
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::warning('Invalid user role detected', [
            'user_id' => $userId,
            'role' => $userRole,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Rol de usuario no autorizado'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    \Middleware\CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

use Utils\ResponseHelper;
use Utils\Logger;

/**
 * Función helper para respuestas JSON seguras
 */
function jsend(bool $success, string $message, $data = null, int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Sanitiza datos de reclutador para salida segura con validación mejorada
 */
function sanitizeRecruiterData(array $recruiter): array
{
    $sanitized = [];

    // Campos numéricos - asegurar tipo correcto con validación estricta
    $numericFields = ['id'];
    foreach ($numericFields as $field) {
        if (isset($recruiter[$field])) {
            $value = $recruiter[$field];
            // Validar que sea numérico y convertir a int
            if (is_numeric($value)) {
                $sanitized[$field] = (int)$value;
            } else {
                $sanitized[$field] = 0; // Valor por defecto seguro
            }
        }
    }

    // Campos de texto - sanitización robusta contra XSS con validación Unicode
    $textFields = ['first_name', 'last_name', 'email', 'phone', 'company_name', 'department', 'role', 'avatar'];
    foreach ($textFields as $field) {
        if (isset($recruiter[$field])) {
            $value = $recruiter[$field];

            // Validar que sea string
            if (!is_string($value)) {
                $sanitized[$field] = '';
                continue;
            }

            // Primera sanitización: remover tags HTML
            $cleaned = strip_tags($value);

            // Segunda sanitización: htmlspecialchars con configuración segura
            $sanitized[$field] = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

            // Validar longitud máxima para prevenir ataques de denegación de servicio
            if (strlen($sanitized[$field]) > 1000) {
                $sanitized[$field] = substr($sanitized[$field], 0, 1000);
            }
        }
    }

    // Campos de estado - validación estricta con lista blanca
    if (isset($recruiter['status'])) {
        $allowedStatuses = ['active', 'inactive', 'pending', 'suspended'];
        $status = strtolower(trim($recruiter['status']));

        if (in_array($status, $allowedStatuses)) {
            $sanitized['status'] = $status;
        } else {
            $sanitized['status'] = 'inactive'; // Valor por defecto seguro
        }
    }

    // Fechas - validación de formato y sanitización
    $dateFields = ['created_at', 'updated_at'];
    foreach ($dateFields as $field) {
        if (isset($recruiter[$field])) {
            $dateValue = $recruiter[$field];

            // Validar formato de fecha básico
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateValue)) {
                $sanitized[$field] = $dateValue;
            } else {
                $sanitized[$field] = date('Y-m-d H:i:s'); // Fecha por defecto segura
            }
        }
    }

    return $sanitized;
}

/**
 * Valida y sanitiza entrada de reclutador con validación avanzada
 */
function validateRecruiterInput(array $input, bool $isUpdate = false): array
{
    $errors = [];
    $sanitized = [];

    // Campos requeridos para creación con validación estricta
    $requiredFields = $isUpdate ? [] : ['first_name', 'last_name', 'email', 'company_name', 'department', 'role'];

    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            $errors[] = "Campo requerido faltante o vacío: {$field}";
        }
    }

    // Validaciones avanzadas con expresiones regulares mejoradas
    $validations = [
        'first_name' => [
            'required' => !$isUpdate,
            'min' => 1,
            'max' => 100,
            'pattern' => '/^[a-zA-Z\s\-\.\'\p{L}]+$/u', // Soporte Unicode mejorado
            'allow_html' => false,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'last_name' => [
            'required' => !$isUpdate,
            'min' => 1,
            'max' => 100,
            'pattern' => '/^[a-zA-Z\s\-\.\'\p{L}]+$/u', // Soporte Unicode mejorado
            'allow_html' => false,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'email' => [
            'required' => !$isUpdate,
            'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'max' => 255,
            'allow_html' => false,
            'no_xss' => true,
            'email_format' => true
        ],
        'phone' => [
            'required' => false,
            'max' => 30,
            'pattern' => '/^[\+]?[0-9\s\-\(\)\.]+$/',
            'allow_html' => false,
            'no_xss' => true
        ],
        'company_name' => [
            'required' => !$isUpdate,
            'min' => 1,
            'max' => 255,
            'allow_html' => false,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'department' => [
            'required' => !$isUpdate,
            'min' => 1,
            'max' => 100,
            'allow_html' => false,
            'no_xss' => true,
            'no_sql_injection' => true
        ],
        'role' => [
            'required' => !$isUpdate,
            'min' => 1,
            'max' => 50,
            'pattern' => '/^[a-zA-Z\s\-_]+$/',
            'allow_html' => false,
            'no_xss' => true,
            'allowed_values' => ['recruiter', 'hr', 'manager', 'senior_recruiter']
        ],
        'status' => [
            'required' => !$isUpdate,
            'allowed_values' => ['active', 'inactive', 'pending', 'suspended'],
            'no_xss' => true
        ],
        'avatar' => [
            'required' => false,
            'max' => 500,
            'pattern' => '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/',
            'allow_html' => false,
            'no_xss' => true
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

        // Validar formato de email si es campo email
        if (isset($rules['email_format']) && $rules['email_format']) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Formato de email inválido para {$field}";
                continue;
            }
        }

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
            foreach ($sqlPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $errors[] = "Campo {$field} contiene caracteres potencialmente peligrosos";
                    continue 2;
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
        } else {
            $sanitized[$field] = $value;
        }
    }

    return ['errors' => $errors, 'sanitized' => $sanitized];
}

/**
 * Función helper para obtener conexión a BD segura
 */
function getSecureDatabaseConnection()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = \Utils\Database::getInstance()->getConnection();
        } catch (Exception $e) {
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::error('Database connection failed in recruiters.php', [
                    'error' => $e->getMessage()
                ]);
            }
            jsend(false, 'Error de conexión a base de datos', null, 500);
            exit;
        }
    }
    return $pdo;
}

/**
 * Clase de auditoría de seguridad para operaciones de recruiters
 */
class RecruiterSecurityAuditor
{
    private static $logFile = __DIR__ . '/../../logs/recruiter_security.log';

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

    public static function logRecruiterOperation(string $userId, string $operation, bool $success, ?int $recruiterId = null): void
    {
        self::logSecurityEvent('RECRUITER_OPERATION', [
            'user_id' => $userId,
            'operation' => $operation,
            'recruiter_id' => $recruiterId,
            'success' => $success,
            'timestamp' => time()
        ], $success ? 'INFO' : 'WARNING');
    }

    public static function logRateLimitExceeded(string $userId, string $operation): void
    {
        self::logSecurityEvent('RECRUITER_RATE_LIMIT_EXCEEDED', [
            'user_id' => $userId,
            'operation' => $operation,
            'timestamp' => time()
        ], 'WARNING');
    }

    public static function logValidationError(string $userId, string $field, string $error): void
    {
        self::logSecurityEvent('RECRUITER_VALIDATION_ERROR', [
            'user_id' => $userId,
            'field' => $field,
            'error' => $error,
            'timestamp' => time()
        ], 'ERROR');
    }

    public static function logAccessDenied(string $userId, string $operation, string $reason): void
    {
        self::logSecurityEvent('RECRUITER_ACCESS_DENIED', [
            'user_id' => $userId,
            'operation' => $operation,
            'reason' => $reason,
            'timestamp' => time()
        ], 'ALERT');
    }

    public static function logSuspiciousActivity(string $userId, string $activity, array $details = []): void
    {
        self::logSecurityEvent('RECRUITER_SUSPICIOUS_ACTIVITY', [
            'user_id' => $userId,
            'activity' => $activity,
            'details' => $details,
            'timestamp' => time()
        ], 'ALERT');
    }
}

/**
 * Maneja GET - Listar reclutadores o reclutador específico con control de acceso
 */
function handleGetRecruiters($db, $userId, $userRole)
{
    try {
        // Validar y sanitizar parámetros de entrada
        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 50) : 20;
        $offset = ($page - 1) * $limit;

        // Filtros opcionales con sanitización
        $statusFilter = isset($_GET['status']) ? strip_tags($_GET['status']) : null;
        $companyFilter = isset($_GET['company']) ? strip_tags($_GET['company']) : null;

        // Validar filtros
        if ($statusFilter && !in_array($statusFilter, ['active', 'inactive', 'pending', 'suspended'])) {
            jsend(false, 'Filtro de estado no válido', null, 400);
            return;
        }

        // Si se solicita un reclutador específico
        if ($id !== null && $id > 0) {
            $sql = "SELECT * FROM bt_staff_profiles WHERE id = ?";
            $params = [$id];

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $recruiter = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$recruiter) {
                jsend(false, 'Reclutador no encontrado', null, 404);
                return;
            }

            $sanitizedRecruiter = sanitizeRecruiterData($recruiter);

            // Log de consulta
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::info('Reclutador consultado', [
                    'recruiter_id' => $id,
                    'user_id' => $userId,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }

            jsend(true, 'Reclutador encontrado', $sanitizedRecruiter);
            return;
        }

        // Listar reclutadores con filtros y paginación
        $sql = "SELECT * FROM bt_staff_profiles WHERE 1=1";
        $params = [];

        if ($statusFilter) {
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
        }

        if ($companyFilter) {
            $sql .= " AND company_name LIKE ?";
            $params[] = '%' . $companyFilter . '%';
        }

        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $recruiters = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sanitizedRecruiters = array_map('sanitizeRecruiterData', $recruiters);

        // Obtener total para paginación
        $countSql = "SELECT COUNT(*) FROM bt_staff_profiles WHERE 1=1";
        $countParams = [];

        if ($statusFilter) {
            $countSql .= " AND status = ?";
            $countParams[] = $statusFilter;
        }

        if ($companyFilter) {
            $countSql .= " AND company_name LIKE ?";
            $countParams[] = '%' . $companyFilter . '%';
        }

        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();

        // Log de consulta de lista
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Lista de reclutadores consultada', [
                'total_results' => count($recruiters),
                'total_count' => $total,
                'page' => $page,
                'limit' => $limit,
                'status_filter' => $statusFilter,
                'company_filter' => $companyFilter,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        jsend(true, 'Reclutadores obtenidos exitosamente', [
            'recruiters' => $sanitizedRecruiters,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    } catch (Throwable $e) {
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error consultando reclutadores', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja POST - Crear reclutador con control de acceso mejorado y rate limiting
 */
function handleCreateRecruiter($db, $userId, $userRole)
{
    try {
        // CONTROL DE ACCESO: Solo admin puede crear reclutadores con validación mejorada
        if ($userRole !== 'admin') {
            jsend(false, 'No autorizado para crear reclutadores', null, 403);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Unauthorized recruiter creation attempt', [
                    'user_id' => $userId,
                    'user_role' => $userRole,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            return;
        }

        // Rate limiting mejorado
        if (!RecruiterRateLimiter::canPerformOperation((string)$userId, 'create')) {
            RecruiterSecurityAuditor::logRateLimitExceeded((string)$userId, 'create_recruiter');
            jsend(false, 'Límite de creación de reclutadores excedido. Intente más tarde.', null, 429);
            return;
        }

        // Validar entrada JSON con mejor manejo de errores
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            jsend(false, 'Datos inválidos - JSON malformado o vacío', null, 400);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Invalid JSON input for recruiter creation', [
                    'user_id' => $userId,
                    'raw_input' => file_get_contents('php://input'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        // Usar validación robusta mejorada
        $validation = validateRecruiterInput($input, false);
        if (!empty($validation['errors'])) {
            jsend(false, 'Errores de validación: ' . implode(', ', $validation['errors']), null, 400);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Recruiter creation validation failed', [
                    'user_id' => $userId,
                    'errors' => $validation['errors'],
                    'input_data' => array_keys($input),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        $data = $validation['sanitized'];

        // Verificar que no exista un reclutador con el mismo email
        $dupeStmt = $db->prepare('SELECT id FROM bt_staff_profiles WHERE email = ?');
        $dupeStmt->execute([$data['email']]);

        if ($dupeStmt->fetch()) {
            jsend(false, 'Ya existe un reclutador con ese email', null, 409);
            return;
        }

        // Generar ID único para el reclutador con mayor seguridad
        $recruiterId = 'rec-' . bin2hex(random_bytes(8)) . '-' . time();

        // Preparar consulta SQL con parámetros nombrados para mayor seguridad
        $sql = "INSERT INTO bt_staff_profiles (
            id, first_name, last_name, email, phone, company_name,
            department, role, status, avatar, created_at, updated_at
        ) VALUES (
            :id, :first_name, :last_name, :email, :phone, :company_name,
            :department, :role, :status, :avatar, NOW(), NOW()
        )";

        $stmt = $db->prepare($sql);
        $params = [
            ':id' => $recruiterId,
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => strtolower($data['email']), // Normalizar email
            ':phone' => $data['phone'] ?? '',
            ':company_name' => $data['company_name'],
            ':department' => $data['department'],
            ':role' => $data['role'],
            ':status' => $data['status'] ?? 'active',
            ':avatar' => $data['avatar'] ?? null
        ];

        $success = $stmt->execute($params);

        if (!$success) {
            $errorInfo = $stmt->errorInfo();
            jsend(false, 'Error al crear el reclutador', null, 500);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::error('Database error during recruiter creation', [
                    'user_id' => $userId,
                    'error_code' => $errorInfo[0] ?? 'unknown',
                    'error_message' => $errorInfo[2] ?? 'unknown',
                    'recruiter_data' => array_keys($data),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        // Log de auditoría seguro mejorado
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Reclutador creado exitosamente', [
                'recruiter_id' => $recruiterId,
                'created_by' => $userId,
                'user_role' => $userRole,
                'recruiter_email' => $data['email'],
                'recruiter_name' => $data['first_name'] . ' ' . $data['last_name'],
                'recruiter_company' => $data['company_name'],
                'recruiter_department' => $data['department'],
                'recruiter_role' => $data['role'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'session_id' => session_id(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }

        // Auditoría de seguridad - creación exitosa
        RecruiterSecurityAuditor::logRecruiterOperation((string)$userId, 'create', true, null);
        RecruiterRateLimiter::recordOperation((string)$userId, 'create');

        // Preparar respuesta sanitizada
        $responseData = [
            'id' => $recruiterId,
            'first_name' => htmlspecialchars($data['first_name'], ENT_QUOTES, 'UTF-8'),
            'last_name' => htmlspecialchars($data['last_name'], ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8'),
            'company_name' => htmlspecialchars($data['company_name'], ENT_QUOTES, 'UTF-8'),
            'department' => htmlspecialchars($data['department'], ENT_QUOTES, 'UTF-8'),
            'role' => htmlspecialchars($data['role'], ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($data['status'] ?? 'active', ENT_QUOTES, 'UTF-8'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        jsend(true, 'Reclutador creado exitosamente', $responseData, 201);
    } catch (Throwable $e) {
        // Auditoría de seguridad - error en creación
        RecruiterSecurityAuditor::logRecruiterOperation((string)$userId, 'create', false, null);

        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error creando reclutador', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja PUT - Actualizar reclutador con control de acceso mejorado y rate limiting
 */
function handleUpdateRecruiter($db, $userId, $userRole)
{
    try {
        // CONTROL DE ACCESO: Solo admin puede actualizar reclutadores
        if ($userRole !== 'admin') {
            jsend(false, 'No autorizado para actualizar reclutadores', null, 403);
            return;
        }

        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        if (!$id) {
            jsend(false, 'ID de reclutador requerido', null, 400);
            return;
        }

        // Rate limiting mejorado
        if (!RecruiterRateLimiter::canPerformOperation((string)$userId, 'update')) {
            RecruiterSecurityAuditor::logRateLimitExceeded((string)$userId, 'update_recruiter');
            jsend(false, 'Límite de actualización de reclutadores excedido. Intente más tarde.', null, 429);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            jsend(false, 'Datos inválidos - JSON malformado', null, 400);
            return;
        }

        // Usar validación robusta
        $validation = validateRecruiterInput($input, true);
        if (!empty($validation['errors'])) {
            jsend(false, 'Errores de validación: ' . implode(', ', $validation['errors']), null, 400);
            return;
        }

        $data = $validation['sanitized'];

        // Verificar que el reclutador existe
        $stmt = $db->prepare('SELECT id, email FROM bt_staff_profiles WHERE id = ?');
        $stmt->execute([$id]);
        $existingRecruiter = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingRecruiter) {
            jsend(false, 'Reclutador no encontrado', null, 404);
            return;
        }

        // Verificar que no exista otro reclutador con el mismo email
        if (isset($data['email'])) {
            $dupeStmt = $db->prepare('SELECT id FROM bt_staff_profiles WHERE email = ? AND id != ?');
            $dupeStmt->execute([$data['email'], $id]);

            if ($dupeStmt->fetch()) {
                jsend(false, 'Ya existe otro reclutador con ese email', null, 409);
                return;
            }
        }

        // Preparar actualización
        $updateFields = [];
        $params = [];

        $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'company_name', 'department', 'role', 'status', 'avatar'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $dbField = $field === 'company_name' ? 'company_name' : $field;
                $updateFields[] = "{$dbField} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updateFields)) {
            jsend(false, 'No hay campos para actualizar', null, 400);
            return;
        }

        $params[] = $id;
        $sql = 'UPDATE bt_staff_profiles SET ' . implode(', ', $updateFields) . ', updated_at = NOW() WHERE id = ?';
        $params[] = $userId; // Para updated_by si existe la columna

        $stmt = $db->prepare($sql);
        $success = $stmt->execute($params);

        if (!$success) {
            jsend(false, 'Error al actualizar el reclutador', null, 500);
            return;
        }

        // Log de auditoría seguro
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Reclutador actualizado exitosamente', [
                'recruiter_id' => $id,
                'updated_by' => $userId,
                'user_role' => $userRole,
                'fields_updated' => array_keys($data),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Auditoría de seguridad - actualización exitosa
        RecruiterSecurityAuditor::logRecruiterOperation((string)$userId, 'update', true, $id);
        RecruiterRateLimiter::recordOperation((string)$userId, 'update');

        jsend(true, 'Reclutador actualizado exitosamente');
    } catch (Throwable $e) {
        // Auditoría de seguridad - error en actualización
        RecruiterSecurityAuditor::logRecruiterOperation((string)$userId, 'update', false, $id ?? null);

        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error actualizando reclutador', [
                'error' => $e->getMessage(),
                'recruiter_id' => $id ?? null,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

/**
 * Maneja DELETE - Eliminar reclutador con control de acceso mejorado y rate limiting
 */
function handleDeleteRecruiter($db, $userId, $userRole)
{
    try {
        // CONTROL DE ACCESO: Solo admin puede eliminar reclutadores
        if ($userRole !== 'admin') {
            jsend(false, 'No autorizado para eliminar reclutadores', null, 403);
            if (class_exists('\Utils\Logger')) {
                \Utils\Logger::warning('Unauthorized recruiter deletion attempt', [
                    'user_id' => $userId,
                    'user_role' => $userRole,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return;
        }

        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        if (!$id) {
            jsend(false, 'ID de reclutador requerido', null, 400);
            return;
        }

        // Rate limiting mejorado
        if (!RecruiterRateLimiter::canPerformOperation((string)$userId, 'delete')) {
            RecruiterSecurityAuditor::logRateLimitExceeded((string)$userId, 'delete_recruiter');
            jsend(false, 'Límite de eliminación de reclutadores excedido. Intente más tarde.', null, 429);
            return;
        }

        // Verificar que el reclutador existe
        $stmt = $db->prepare('SELECT id, first_name, last_name, email FROM bt_staff_profiles WHERE id = ?');
        $stmt->execute([$id]);
        $recruiter = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recruiter) {
            jsend(false, 'Reclutador no encontrado', null, 404);
            return;
        }

        // Log antes de eliminar
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Reclutador marcado para eliminación', [
                'recruiter_id' => $id,
                'recruiter_name' => $recruiter['first_name'] . ' ' . $recruiter['last_name'],
                'recruiter_email' => $recruiter['email'],
                'deleted_by' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Soft delete - marcar como inactivo en lugar de eliminar físicamente
        $deleteStmt = $db->prepare('UPDATE bt_staff_profiles SET status = \'inactive\', updated_at = NOW() WHERE id = ?');
        $success = $deleteStmt->execute([$id]);

        if (!$success) {
            jsend(false, 'Error al eliminar el reclutador', null, 500);
            return;
        }

        // Log de confirmación
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Reclutador eliminado exitosamente', [
                'recruiter_id' => $id,
                'deleted_by' => $userId,
                'user_role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        // Auditoría de seguridad - eliminación exitosa
        RecruiterSecurityAuditor::logRecruiterOperation((string)$userId, 'delete', true, $id);
        RecruiterRateLimiter::recordOperation((string)$userId, 'delete');

        jsend(true, 'Reclutador eliminado exitosamente');
    } catch (Throwable $e) {
        // Auditoría de seguridad - error en eliminación
        RecruiterSecurityAuditor::logRecruiterOperation((string)$userId, 'delete', false, $id ?? null);

        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error eliminando reclutador', [
                'error' => $e->getMessage(),
                'recruiter_id' => $id ?? null,
                'user_id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        jsend(false, 'Error interno del servidor', null, 500);
    }
}

// Routing principal
try {
    $db = getSecureDatabaseConnection();

    switch ($method) {
        case 'GET':
            handleGetRecruiters($db, $userId, $userRole);
            break;

        case 'POST':
            if (!$createLimit['allowed']) {
                jsend(false, 'Límite de creación de reclutadores excedido. Intente más tarde.', null, 429);
                exit;
            }
            handleCreateRecruiter($db, $userId, $userRole);
            break;

        case 'PUT':
            if (!$updateLimit['allowed']) {
                jsend(false, 'Límite de actualización de reclutadores excedido. Intente más tarde.', null, 429);
                exit;
            }
            handleUpdateRecruiter($db, $userId, $userRole);
            break;

        case 'DELETE':
            if (!$deleteLimit['allowed']) {
                jsend(false, 'Límite de eliminación de reclutadores excedido. Intente más tarde.', null, 429);
                exit;
            }
            handleDeleteRecruiter($db, $userId, $userRole);
            break;

        default:
            jsend(false, 'Método no permitido', null, 405);
            break;
    }
} catch (Throwable $e) {
    // Log de error seguro sin exponer información sensible
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::error('Error en recruiters API', [
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
    jsend(false, 'Error interno del servidor', null, 500);
}
