<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/Services/NotificationService.php';

use Services\NotificationService;

/**
 * Endpoint: /api/notification
 * Gestiona envío de notificaciones (email, SMS, push) de forma segura
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
    http_response_code(200);
    exit;
}

/**
 * Clase de auditoría de seguridad para notificaciones
 */
class NotificationSecurityAuditor
{
    private static $logFile = __DIR__ . '/../../logs/notification_security.log';

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

    public static function logNotificationAttempt(string $userId, string $type, string $recipient, bool $success): void
    {
        self::logSecurityEvent('NOTIFICATION_ATTEMPT', [
            'user_id' => $userId,
            'type' => $type,
            'recipient' => $recipient,
            'success' => $success,
            'timestamp' => time()
        ], $success ? 'INFO' : 'WARNING');
    }

    public static function logRateLimitExceeded(string $userId, string $type): void
    {
        self::logSecurityEvent('NOTIFICATION_RATE_LIMIT_EXCEEDED', [
            'user_id' => $userId,
            'type' => $type,
            'timestamp' => time()
        ], 'WARNING');
    }

    public static function logValidationError(string $userId, string $field, string $error): void
    {
        self::logSecurityEvent('NOTIFICATION_VALIDATION_ERROR', [
            'user_id' => $userId,
            'field' => $field,
            'error' => $error,
            'timestamp' => time()
        ], 'ERROR');
    }

    public static function logSuspiciousActivity(string $userId, string $activity, array $details = []): void
    {
        self::logSecurityEvent('NOTIFICATION_SUSPICIOUS_ACTIVITY', [
            'user_id' => $userId,
            'activity' => $activity,
            'details' => $details,
            'timestamp' => time()
        ], 'ALERT');
    }

    public static function logAccessDenied(string $userId, string $operation, string $reason): void
    {
        self::logSecurityEvent('NOTIFICATION_ACCESS_DENIED', [
            'user_id' => $userId,
            'operation' => $operation,
            'reason' => $reason,
            'timestamp' => time()
        ], 'WARNING');
    }
}

/**
 * Rate limiting para notificaciones
 */
class NotificationRateLimiter
{
    private static $rateLimitFile = __DIR__ . '/../../storage/notification_rate_limits.json';
    private static $maxNotificationsPerHour = 20; // Más permisivo que emails
    private static $maxNotificationsPerDay = 100;
    private static $windowSeconds = 3600;

    public static function canSendNotification(string $userId): bool
    {
        $limits = self::loadRateLimits();
        self::cleanupOldEntries($limits);

        $userEntries = $limits[$userId] ?? [];
        $currentCount = count($userEntries);

        return $currentCount < self::$maxNotificationsPerHour;
    }

    public static function recordNotificationSent(string $userId): void
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
 * Validar y sanitizar destinatario según tipo de notificación
 */
function validateAndSanitizeRecipient(string $recipient, string $type, string $userId): ?string
{
    $recipient = trim($recipient);

    if ($type === 'email') {
        // Validación de email
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            NotificationSecurityAuditor::logValidationError($userId, 'recipient', 'Formato de email inválido');
            return null;
        }

        // Verificar dominio básico
        $domain = substr(strrchr($recipient, "@"), 1);
        if (empty($domain) || strlen($domain) < 4) {
            NotificationSecurityAuditor::logValidationError($userId, 'recipient', 'Dominio de email inválido');
            return null;
        }

        // Lista negra de dominios
        $blockedDomains = ['10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'temp-mail.org'];
        if (in_array(strtolower($domain), $blockedDomains)) {
            NotificationSecurityAuditor::logSuspiciousActivity($userId, 'EMAIL_FROM_BLOCKED_DOMAIN', ['domain' => $domain]);
            return null;
        }
    } elseif ($type === 'sms') {
        // Validación básica de número de teléfono
        $recipient = preg_replace('/[^\d+\-\s()]/', '', $recipient);
        if (strlen($recipient) < 7 || strlen($recipient) > 20) {
            NotificationSecurityAuditor::logValidationError($userId, 'recipient', 'Número de teléfono inválido');
            return null;
        }
    } elseif ($type === 'push') {
        // Para push notifications, el recipient podría ser un token o ID de dispositivo
        if (strlen($recipient) < 10 || strlen($recipient) > 500) {
            NotificationSecurityAuditor::logValidationError($userId, 'recipient', 'Token de dispositivo inválido');
            return null;
        }
    }

    return $recipient;
}

/**
 * Validar y sanitizar contenido de notificación
 */
function validateAndSanitizeContent(string $content, string $type, string $userId): ?string
{
    $content = trim($content);

    // Límites de longitud según tipo
    $maxLengths = [
        'email' => 5000,
        'sms' => 160,
        'push' => 1000
    ];

    if (!isset($maxLengths[$type])) {
        NotificationSecurityAuditor::logValidationError($userId, 'content', 'Tipo de notificación no soportado');
        return null;
    }

    if (strlen($content) > $maxLengths[$type]) {
        NotificationSecurityAuditor::logValidationError($userId, 'content', 'Contenido demasiado largo para tipo ' . $type);
        return null;
    }

    if (strlen($content) < 1) {
        NotificationSecurityAuditor::logValidationError($userId, 'content', 'Contenido vacío');
        return null;
    }

    // Para emails, verificar contenido sospechoso
    if ($type === 'email') {
        $suspiciousPatterns = [
            '/\b(?:viagra|casino|lottery|winner|prize)\b/i',
            '/\$[0-9]+/',
            '/(?:http|https|www\.)\S+/i',
            '/\b(?:password|login|account|bank)\b/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                NotificationSecurityAuditor::logSuspiciousActivity($userId, 'SUSPICIOUS_CONTENT', [
                    'type' => $type,
                    'pattern' => $pattern
                ]);
                return null;
            }
        }
    }

    return $content;
}

/**
 * Validar permisos para envío de notificaciones
 */
function validateNotificationPermissions(array $userPayload): bool
{
    $allowedRoles = ['admin', 'hr', 'recruiter', 'staff'];

    if (!isset($userPayload['role']) || !in_array($userPayload['role'], $allowedRoles)) {
        return false;
    }

    return true;
}

try {
    // Autenticación JWT mejorada
    $userPayload = \Middleware\JWTMiddleware::requireAuth();
    if (!$userPayload) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Autenticación requerida']);
        exit;
    }

    $userId = (string)$userPayload['user_id'];
    $userRole = $userPayload['role'] ?? 'candidate';

    // Validar permisos de usuario
    if (!validateNotificationPermissions($userPayload)) {
        NotificationSecurityAuditor::logAccessDenied($userId, 'send_notification', 'Rol insuficiente: ' . $userRole);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para enviar notificaciones']);
        exit;
    }

    // Solo permitir método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    // Rate limiting
    if (!NotificationRateLimiter::canSendNotification($userId)) {
        NotificationSecurityAuditor::logRateLimitExceeded($userId, 'notification_hourly');
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Límite de notificaciones excedido. Intenta más tarde.']);
        exit;
    }

    // Obtener y validar entrada JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        NotificationSecurityAuditor::logValidationError($userId, 'input', 'JSON inválido o vacío');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos - JSON malformado o vacío']);
        exit;
    }

    // Validar campos requeridos
    $requiredFields = ['type', 'to'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            NotificationSecurityAuditor::logValidationError($userId, $field, 'Campo requerido faltante');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Campo requerido faltante: {$field}"]);
            exit;
        }
    }

    // Validar tipo de notificación
    $allowedTypes = ['email', 'sms', 'push'];
    $type = trim($input['type']);
    if (!in_array($type, $allowedTypes)) {
        NotificationSecurityAuditor::logValidationError($userId, 'type', 'Tipo de notificación no válido');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo de notificación no válido']);
        exit;
    }

    // Validar y sanitizar destinatario
    $to = validateAndSanitizeRecipient($input['to'], $type, $userId);
    if (!$to) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Destinatario inválido']);
        exit;
    }

    // Validar y sanitizar contenido según tipo
    $subject = '';
    $body = '';
    $message = '';

    if ($type === 'email') {
        if (!isset($input['subject']) || !isset($input['body'])) {
            NotificationSecurityAuditor::logValidationError($userId, 'content', 'Asunto y cuerpo requeridos para email');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Asunto y cuerpo requeridos para email']);
            exit;
        }

        $subject = validateAndSanitizeContent($input['subject'], 'email', $userId);
        $body = validateAndSanitizeContent($input['body'], 'email', $userId);

        if (!$subject || !$body) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Contenido del email inválido']);
            exit;
        }
    } else {
        // Para SMS y push, usar el campo 'message'
        if (!isset($input['message'])) {
            NotificationSecurityAuditor::logValidationError($userId, 'message', 'Mensaje requerido para ' . $type);
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Mensaje requerido para ' . $type]);
            exit;
        }

        $message = validateAndSanitizeContent($input['message'], $type, $userId);
        if (!$message) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Mensaje inválido']);
            exit;
        }
    }

    // Enviar notificación usando el servicio
    $service = new NotificationService();
    $result = false;

    if ($type === 'email') {
        $result = $service->sendEmail($to, $subject, $body);
    } elseif ($type === 'sms') {
        $result = $service->sendSMS($to, $message);
    } elseif ($type === 'push') {
        $result = $service->sendPush($to, $message);
    }

    // Registrar resultado y actualizar rate limiting
    if ($result) {
        NotificationRateLimiter::recordNotificationSent($userId);
        NotificationSecurityAuditor::logNotificationAttempt($userId, $type, $to, true);

        // Log adicional para debugging
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::info('Notificación enviada exitosamente', [
                'user_id' => $userId,
                'type' => $type,
                'recipient' => $to,
                'user_role' => $userRole,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Notificación enviada exitosamente',
            'data' => [
                'type' => $type,
                'recipient' => $to,
                'sent_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        NotificationSecurityAuditor::logNotificationAttempt($userId, $type, $to, false);

        // Log de error
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::error('Error enviando notificación', [
                'user_id' => $userId,
                'type' => $type,
                'recipient' => $to,
                'error' => 'Servicio de notificación falló'
            ]);
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo enviar la notificación']);
    }
} catch (Throwable $e) {
    // Auditoría de seguridad para errores generales
    if (isset($userId)) {
        NotificationSecurityAuditor::logNotificationAttempt($userId, 'unknown', 'unknown', false);
    }

    // Log de error general
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::error('Error en endpoint de notificaciones', [
            'error' => $e->getMessage(),
            'user_id' => $userId ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
