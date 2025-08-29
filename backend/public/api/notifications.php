<?php

declare(strict_types=1);

/**
 * Endpoint: /api/notifications
 * Gestión de notificaciones del sistema con control de acceso seguro
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 2.0.0 - Security Enhanced
 */

require_once __DIR__ . '/./bootstrap.php';

/**
 * Headers de seguridad avuse Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if     // MÃ©todo no permitido
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'MÃ©todo no permitido', 'data' => null]);
} catch (Throwable $e) {
    // LOGGING DE ERRORES DE SEGURIDAD
    NotificationsSecurityAuditor::logSecurityEvent('application_error', [
        'user_id' => $userPayload['id'] ?? 'unknown',
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'endpoint' => 'notifications.php',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ], 'ERROR');

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Error interno del servidor',
        'data' => null
    ]);
}['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// preflight(); // ELIMINADO: Preflight se maneja automÃ¡ticamente en bootstrap.php
// sendCors(); // ELIMINADO: CORS se configura automÃ¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');

// AUTENTICACIÓN JWT MEJORADA
$userPayload = null;
$authError = null;

try {
    $userPayload = JWTMiddleware::authenticate();
    if (!$userPayload) {
        $authError = 'Token JWT inválido o expirado';
    }
} catch (Exception $e) {
    $authError = 'Error de autenticación: ' . $e->getMessage();
    NotificationsSecurityAuditor::logSecurityEvent('authentication_failure', [
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'endpoint' => 'notifications.php'
    ]);
}

// RATE LIMITING PARA NOTIFICATIONS
$rateLimitResult = NotificationsRateLimiter::checkLimit(
    $userPayload['id'] ?? 'anonymous',
    $method,
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
);

if (!$rateLimitResult['allowed']) {
    NotificationsSecurityAuditor::logSecurityEvent('rate_limit_exceeded', [
        'user_id' => $userPayload['id'] ?? 'anonymous',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $method,
        'endpoint' => 'notifications.php'
    ]);

    http_response_code(429);
    echo json_encode([
        'ok' => false,
        'message' => 'Límite de tasa excedido. Intente nuevamente más tarde.',
        'data' => [
            'retry_after' => $rateLimitResult['retry_after'],
            'limit_type' => $rateLimitResult['limit_type']
        ]
    ]);
    exit;
}

// VALIDACIÓN DE PERMISOS
if ($authError) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => $authError, 'data' => null]);
    exit;
}

if (!validateNotificationsPermissions($userPayload, $method)) {
    NotificationsSecurityAuditor::logSecurityEvent('permission_denied', [
        'user_id' => $userPayload['id'],
        'user_role' => $userPayload['role'],
        'method' => $method,
        'endpoint' => 'notifications.php'
    ]);

    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Permisos insuficientes para esta operación', 'data' => null]);
    exit;
}

// REGISTRAR ACCESO AUTORIZADO
NotificationsSecurityAuditor::logAccess($userPayload['id'], $method, 'notifications.php', [
    'user_role' => $userPayload['role'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

try {nzado
 */
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

/**
 * Configurar CORS seguro
 */
$allowedOrigins = [
    'https://bubble-talents.com',
    'https://www.bubble-talents.com',
    'https://app.bubble-talents.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
}

/**
 * Manejar preflight requests
 */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Clase de auditoría de seguridad para gestión de notificaciones
 */
class NotificationsSecurityAuditor
{
    private static $logFile = __DIR__ . '/../../logs/notifications_security.log';

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

    public static function logNotificationOperation(string $userId, string $operation, bool $success, ?int $notificationId = null): void
    {
        self::logSecurityEvent('NOTIFICATION_OPERATION', [
            'user_id' => $userId,
            'operation' => $operation,
            'notification_id' => $notificationId,
            'success' => $success,
            'timestamp' => time()
        ], $success ? 'INFO' : 'WARNING');
    }
    public static function logRateLimitExceeded(string $userId, string $operation): void
    {
        self::logSecurityEvent('NOTIFICATIONS_RATE_LIMIT_EXCEEDED', [
            'user_id' => $userId,
            'operation' => $operation,
            'timestamp' => time()
        ], 'WARNING');
    }

    public static function logValidationError(string $userId, string $field, string $error): void
    {
        self::logSecurityEvent('NOTIFICATIONS_VALIDATION_ERROR', [
            'user_id' => $userId,
            'field' => $field,
            'error' => $error,
            'timestamp' => time()
        ], 'ERROR');
    }

    public static function logAccessDenied(string $userId, string $operation, string $reason): void
    {
        self::logSecurityEvent('NOTIFICATIONS_ACCESS_DENIED', [
            'user_id' => $userId,
            'operation' => $operation,
            'reason' => $reason,
            'timestamp' => time()
        ], 'ALERT');
    }

    public static function logOperation(string $operation, string $userId, array $details = []): void
    {
        self::logSecurityEvent('NOTIFICATIONS_OPERATION', array_merge([
            'user_id' => $userId,
            'operation' => $operation,
            'timestamp' => time()
        ], $details), 'INFO');
    }

    public static function logAccess(string $userId, string $method, string $endpoint, array $details = []): void
    {
        self::logSecurityEvent('NOTIFICATIONS_ACCESS', array_merge([
            'user_id' => $userId,
            'method' => $method,
            'endpoint' => $endpoint,
            'timestamp' => time()
        ], $details), 'INFO');
    }
}

/**
 * Rate limiting para operaciones de notificaciones
 */
class NotificationsRateLimiter
{
    private static $rateLimitFile = __DIR__ . '/../../storage/notifications_rate_limits.json';
    private static $maxListPerHour = 60; // Más permisivo para listados
    private static $maxCreatePerHour = 10; // Moderado para creación

    public static function canPerformOperation(string $userId, string $operation): bool
    {
        $limits = self::loadRateLimits();
        self::cleanupOldEntries($limits);

        $maxLimits = [
            'list' => self::$maxListPerHour,
            'create' => self::$maxCreatePerHour
        ];

        if (!isset($maxLimits[$operation])) {
            return true;
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
        $windowSeconds = 3600; // 1 hora

        foreach ($limits as $userId => &$userLimits) {
            foreach ($userLimits as $operation => &$entries) {
                $entries = array_filter($entries, function ($timestamp) use ($now, $windowSeconds) {
                    return ($now - $timestamp) < $windowSeconds;
                });
            }
            // Remover usuarios sin entradas
            if (empty(array_filter($userLimits))) {
                unset($limits[$userId]);
            }
        }
    }
}

/**
 * Validar y sanitizar entrada para notificaciones
 */
function validateAndSanitizeNotificationInput(array $input, string $method): array
{
    $errors = [];
    $sanitized = [];

    if ($method === 'GET') {
        // Validación para parámetros de consulta
        if (isset($input['candidateId'])) {
            $candidateId = filter_var($input['candidateId'], FILTER_VALIDATE_INT);
            if ($candidateId === false || $candidateId <= 0) {
                $errors[] = 'ID de candidato inválido';
            } else {
                $sanitized['candidateId'] = $candidateId;
            }
        }

        if (isset($input['page'])) {
            $page = filter_var($input['page'], FILTER_VALIDATE_INT);
            if ($page === false || $page < 1) {
                $errors[] = 'Número de página inválido';
            } else {
                $sanitized['page'] = $page;
            }
        }

        if (isset($input['limit'])) {
            $limit = filter_var($input['limit'], FILTER_VALIDATE_INT);
            if ($limit === false || $limit < 1 || $limit > 100) {
                $errors[] = 'Límite inválido (1-100)';
            } else {
                $sanitized['limit'] = $limit;
            }
        }
    } elseif ($method === 'POST') {
        // Validación para creación de notificaciones
        $requiredFields = ['title', 'message'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                $errors[] = "Campo requerido faltante: {$field}";
            }
        }

        if (isset($input['title'])) {
            $title = trim($input['title']);
            if (strlen($title) > 200) {
                $errors[] = 'Título demasiado largo (máximo 200 caracteres)';
            } else {
                $sanitized['title'] = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            }
        }

        if (isset($input['message'])) {
            $message = trim($input['message']);
            if (strlen($message) > 1000) {
                $errors[] = 'Mensaje demasiado largo (máximo 1000 caracteres)';
            } else {
                $sanitized['message'] = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            }
        }

        if (isset($input['type'])) {
            $allowedTypes = ['info', 'warning', 'error', 'success'];
            $type = trim($input['type']);
            if (!in_array($type, $allowedTypes)) {
                $errors[] = 'Tipo de notificación inválido';
            } else {
                $sanitized['type'] = $type;
            }
        }
    }

    return ['errors' => $errors, 'sanitized' => $sanitized];
}

/**
 * Validar permisos para operaciones de notificaciones
 */
function validateNotificationsPermissions(array $userPayload, string $method): bool
{
    $userRole = $userPayload['role'] ?? 'candidate';

    if ($method === 'GET') {
        // Cualquier usuario autenticado puede ver notificaciones
        $allowedRoles = ['admin', 'hr', 'recruiter', 'staff', 'candidate'];
        return in_array($userRole, $allowedRoles);
    } elseif ($method === 'POST') {
        // Solo roles administrativos pueden crear notificaciones
        $allowedRoles = ['admin', 'hr', 'recruiter', 'staff'];
        return in_array($userRole, $allowedRoles);
    }

    return false;
}

use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// preflight(); // ELIMINADO: Preflight se maneja automÃƒÂ¡ticamente en bootstrap.php
// sendCors(); // ELIMINADO: CORS se configura automÃƒÂ¡ticamente en bootstrap.php
header('Content-Type: application/json; charset=UTF-8');

try {
    // Manejo de GET para listar notificaciones
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // VALIDACIÓN DE ENTRADA PARA GET
        $validation = validateAndSanitizeNotificationInput($_GET, 'GET');
        if (!empty($validation['errors'])) {
            NotificationsSecurityAuditor::logSecurityEvent('input_validation_failed', [
                'user_id' => $userPayload['id'],
                'errors' => $validation['errors'],
                'input' => $_GET,
                'endpoint' => 'notifications.php'
            ]);

            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Datos de entrada inválidos',
                'data' => ['errors' => $validation['errors']]
            ]);
            exit;
        }

        $sanitized = $validation['sanitized'];
        $candidateId = $sanitized['candidateId'] ?? null;
        $page = $sanitized['page'] ?? 1;
        $limit = $sanitized['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        $db = getDbConnection();

        if ($candidateId) {
            $st = $db->prepare('SELECT id, candidate_id, message, type, created_at FROM bt_notifications WHERE candidate_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
            $st->execute([$candidateId, $limit, $offset]);
            $list = $st->fetchAll();
        } else {
            $st = $db->prepare('SELECT id, candidate_id, message, type, created_at FROM bt_notifications ORDER BY created_at DESC LIMIT ? OFFSET ?');
            $st->execute([$limit, $offset]);
            $list = $st->fetchAll();
        }

        // REGISTRAR OPERACIÓN EXITOSA
        NotificationsSecurityAuditor::logOperation('list_notifications', $userPayload['id'], [
            'candidate_id' => $candidateId,
            'page' => $page,
            'limit' => $limit,
            'results_count' => count($list)
        ]);

        echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $list]);
        exit;
    }

    // Manejo de POST para crear notificaciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        // VALIDACIÓN DE ENTRADA PARA POST
        $validation = validateAndSanitizeNotificationInput($input, 'POST');
        if (!empty($validation['errors'])) {
            NotificationsSecurityAuditor::logSecurityEvent('input_validation_failed', [
                'user_id' => $userPayload['id'],
                'errors' => $validation['errors'],
                'input' => $input,
                'endpoint' => 'notifications.php'
            ]);

            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Datos de entrada inválidos',
                'data' => ['errors' => $validation['errors']]
            ]);
            exit;
        }

        $sanitized = $validation['sanitized'];

        // En producción, insertar en BD real
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            NotificationsSecurityAuditor::logSecurityEvent('production_feature_disabled', [
                'user_id' => $userPayload['id'],
                'operation' => 'create_notification',
                'endpoint' => 'notifications.php'
            ]);

            http_response_code(501);
            echo json_encode(['ok' => false, 'message' => 'Creación de notificaciones no implementada en producción', 'data' => null]);
            exit;
        }

        $notification = [
            'id' => 2001, // ID fijo para desarrollo
            'title' => $sanitized['title'] ?? 'Nueva notificación',
            'message' => $sanitized['message'] ?? 'Mensaje de notificación',
            'type' => $sanitized['type'] ?? 'info',
            'read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // REGISTRAR OPERACIÓN EXITOSA
        NotificationsSecurityAuditor::logOperation('create_notification', $userPayload['id'], [
            'notification_id' => $notification['id'],
            'title' => $notification['title'],
            'type' => $notification['type']
        ]);

        echo json_encode(['ok' => true, 'message' => 'Notificación creada exitosamente', 'data' => $notification]);
        exit;
    }

    // MÃƒÂ©todo no permitido
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'MÃƒÂ©todo no permitido', 'data' => null]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
