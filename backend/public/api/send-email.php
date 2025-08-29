<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/send-email
 * Maneja envío seguro de emails con validación robusta y protección anti-spam
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
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CSP avanzado para API de envío de emails
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'none'; img-src 'self' data: https:; font-src 'none'; connect-src 'self'; media-src 'none'; object-src 'none'; frame-src 'none'; frame-ancestors 'none'; form-action 'self'; upgrade-insecure-requests; block-all-mixed-content");

// Headers adicionales de seguridad avanzada
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('X-DNS-Prefetch-Control: off');
header('X-Requested-With: XMLHttpRequest');

// Configurar CORS seguro
$allowedOrigins = [
  'https://bubblegum.agency',
  'https://www.bubblegum.agency',
  'https://app.bubblegum.agency'
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
  http_response_code(204);
  exit;
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
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
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Clase para rate limiting de envío de emails
 */
class EmailRateLimiter
{
  private static $emailAttempts = [];
  private static $maxEmailsPerHour = 10;
  private static $maxEmailsPerDay = 50;
  private static $windowHour = 3600;
  private static $windowDay = 86400;

  public static function canSendEmail(string $userId): bool
  {
    $currentTime = time();

    // Limpiar entradas antiguas por hora
    self::$emailAttempts[$userId]['hour'] = array_filter(
      self::$emailAttempts[$userId]['hour'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowHour;
      }
    );

    // Limpiar entradas antiguas por día
    self::$emailAttempts[$userId]['day'] = array_filter(
      self::$emailAttempts[$userId]['day'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowDay;
      }
    );

    $emailsThisHour = count(self::$emailAttempts[$userId]['hour'] ?? []);
    $emailsToday = count(self::$emailAttempts[$userId]['day'] ?? []);

    return $emailsThisHour < self::$maxEmailsPerHour && $emailsToday < self::$maxEmailsPerDay;
  }

  public static function recordEmailSent(string $userId): void
  {
    $currentTime = time();
    self::$emailAttempts[$userId]['hour'][] = $currentTime;
    self::$emailAttempts[$userId]['day'][] = $currentTime;
  }

  public static function getRemainingEmails(string $userId): array
  {
    $currentTime = time();

    // Limpiar entradas antiguas
    self::$emailAttempts[$userId]['hour'] = array_filter(
      self::$emailAttempts[$userId]['hour'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowHour;
      }
    );

    self::$emailAttempts[$userId]['day'] = array_filter(
      self::$emailAttempts[$userId]['day'] ?? [],
      function ($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < self::$windowDay;
      }
    );

    $emailsThisHour = count(self::$emailAttempts[$userId]['hour'] ?? []);
    $emailsToday = count(self::$emailAttempts[$userId]['day'] ?? []);

    return [
      'hour' => max(0, self::$maxEmailsPerHour - $emailsThisHour),
      'day' => max(0, self::$maxEmailsPerDay - $emailsToday)
    ];
  }
}

/**
 * Función de auditoría de seguridad para envío de emails
 */
class EmailSecurityAuditor
{
  private static $logFile = __DIR__ . '/../../logs/email_security.log';

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
      'user_agent' => substr($userAgent, 0, 200), // Limitar longitud
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

  public static function logEmailAttempt(string $userId, string $recipient, string $subject, bool $success): void
  {
    self::logSecurityEvent('EMAIL_ATTEMPT', [
      'user_id' => $userId,
      'recipient' => $recipient,
      'subject' => substr($subject, 0, 100), // Limitar longitud del asunto
      'success' => $success,
      'timestamp' => time()
    ], $success ? 'INFO' : 'WARNING');
  }

  public static function logRateLimitExceeded(string $userId, string $limitType): void
  {
    self::logSecurityEvent('RATE_LIMIT_EXCEEDED', [
      'user_id' => $userId,
      'limit_type' => $limitType,
      'timestamp' => time()
    ], 'WARNING');
  }

  public static function logValidationError(string $userId, string $field, string $error): void
  {
    self::logSecurityEvent('VALIDATION_ERROR', [
      'user_id' => $userId,
      'field' => $field,
      'error' => $error,
      'timestamp' => time()
    ], 'ERROR');
  }

  public static function logSuspiciousActivity(string $userId, string $activity, array $details = []): void
  {
    self::logSecurityEvent('SUSPICIOUS_ACTIVITY', [
      'user_id' => $userId,
      'activity' => $activity,
      'details' => $details,
      'timestamp' => time()
    ], 'ALERT');
  }
}

/**
 * Validar y sanitizar email con reglas estrictas mejoradas
 */
function validateAndSanitizeEmail(string $email, string $userId): ?string
{
  $email = trim($email);
  $email = filter_var($email, FILTER_SANITIZE_EMAIL);

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    EmailSecurityAuditor::logValidationError($userId, 'email', 'Formato de email inválido');
    return null;
  }

  // Validar longitud
  if (strlen($email) > 255) {
    EmailSecurityAuditor::logValidationError($userId, 'email', 'Email demasiado largo');
    return null;
  }

  // Validar dominio básico
  $domain = substr(strrchr($email, "@"), 1);
  if (empty($domain) || strlen($domain) < 4) {
    EmailSecurityAuditor::logValidationError($userId, 'email', 'Dominio de email inválido');
    return null;
  }

  // Verificar dominios bloqueados (lista negra básica)
  $blockedDomains = ['10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'temp-mail.org'];
  if (in_array(strtolower($domain), $blockedDomains)) {
    EmailSecurityAuditor::logSuspiciousActivity($userId, 'EMAIL_FROM_BLOCKED_DOMAIN', ['domain' => $domain]);
    return null;
  }

  return $email;
}

/**
 * Sanitizar y validar asunto del email mejorado
 */
function validateAndSanitizeSubject(string $subject, string $userId): ?string
{
  $subject = trim($subject);

  // Remover caracteres de control y líneas nuevas
  $subject = preg_replace('/[\x00-\x1F\x7F]/', '', $subject);

  // Validar longitud (máximo 255 caracteres)
  if (strlen($subject) > 255 || strlen($subject) < 1) {
    EmailSecurityAuditor::logValidationError($userId, 'subject', 'Longitud del asunto inválida');
    return null;
  }

  // Verificar contenido sospechoso en el asunto
  $suspiciousPatterns = [
    '/\b(?:viagra|casino|lottery|winner|prize)\b/i',
    '/\$[0-9]+/',
    '/(?:http|https|www\.)\S+/i',
    '/\b(?:password|login|account|bank)\b/i'
  ];

  foreach ($suspiciousPatterns as $pattern) {
    if (preg_match($pattern, $subject)) {
      EmailSecurityAuditor::logSuspiciousActivity($userId, 'SUSPICIOUS_SUBJECT_CONTENT', [
        'subject' => substr($subject, 0, 100),
        'pattern' => $pattern
      ]);
      return null;
    }
  }

  return $subject;
}

/**
 * Sanitizar y validar contenido del email mejorado
 */
function validateAndSanitizeBody(string $body, string $userId, bool $isHtml = false): ?string
{
  $body = trim($body);

  // Validar longitud máxima (100KB)
  if (strlen($body) > 102400) {
    EmailSecurityAuditor::logValidationError($userId, 'body', 'Contenido del email demasiado largo');
    return null;
  }

  if ($isHtml) {
    // Para HTML, usar purificador básico mejorado
    // Remover scripts y eventos peligrosos
    $body = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $body);
    $body = preg_replace('/on\w+="[^"]*"/i', '', $body);
    $body = preg_replace('/on\w+=\'[^\']*\'/i', '', $body);

    // Remover iframes y objetos peligrosos
    $body = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $body);
    $body = preg_replace('/<object[^>]*>.*?<\/object>/is', '', $body);
    $body = preg_replace('/<embed[^>]*>.*?<\/embed>/is', '', $body);

    // Solo permitir tags HTML seguros básicos
    $allowedTags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a>';
    $body = strip_tags($body, $allowedTags);

    // Verificar URLs sospechosas en enlaces
    if (preg_match_all('/href=["\']([^"\']+)["\']/i', $body, $matches)) {
      foreach ($matches[1] as $url) {
        if (preg_match('/(?:javascript|data|vbscript):/i', $url)) {
          EmailSecurityAuditor::logSuspiciousActivity($userId, 'SUSPICIOUS_URL_IN_EMAIL', [
            'url' => $url,
            'body_preview' => substr($body, 0, 100)
          ]);
          return null;
        }
      }
    }
  } else {
    // Para texto plano, remover caracteres de control
    $body = preg_replace('/[\x00-\x1F\x7F]/', '', $body);

    // Verificar URLs sospechosas en texto plano
    if (preg_match('/(?:javascript|data|vbscript):/i', $body)) {
      EmailSecurityAuditor::logSuspiciousActivity($userId, 'SUSPICIOUS_URL_IN_PLAIN_TEXT', [
        'body_preview' => substr($body, 0, 100)
      ]);
      return null;
    }
  }

  return $body;
}

/**
 * Validar permisos para envío de email
 */
function validateEmailPermissions(array $userPayload): bool
{
  $allowedRoles = ['admin', 'hr', 'recruiter', 'staff'];

  if (!isset($userPayload['role']) || !in_array($userPayload['role'], $allowedRoles)) {
    return false;
  }

  return true;
}

try {
  // Verificar permisos de usuario
  if (!validateEmailPermissions($userPayload)) {
    http_response_code(403);
    Res::error('No tienes permisos para enviar emails', 403);
    exit;
  }

  // Rate limiting por usuario
  $userId = (string)$userPayload['user_id'];

  if (!EmailRateLimiter::canSendEmail($userId)) {
    $remaining = EmailRateLimiter::getRemainingEmails($userId);

    // Auditoría de seguridad - rate limit excedido
    EmailSecurityAuditor::logRateLimitExceeded($userId, 'email_hourly_or_daily');

    // Log intento de rate limit
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('email_rate_limited', [
        'user_id' => $userId,
        'email' => $userPayload['email'] ?? 'unknown',
        'remaining_hour' => $remaining['hour'],
        'remaining_day' => $remaining['day']
      ]);
    }

    http_response_code(429);
    Res::error('Límite de envío de emails excedido. Intenta más tarde.', 429);
    exit;
  }

  // Obtener y validar entrada
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    http_response_code(400);
    Res::error('JSON inválido', 400);
    exit;
  }

  // Validar campos requeridos
  $requiredFields = ['to', 'subject', 'body'];
  foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
      http_response_code(400);
      Res::error("Campo requerido faltante: {$field}", 400);
      exit;
    }
  }

  // Validar y sanitizar email destinatario
  $to = validateAndSanitizeEmail($input['to'], $userId);
  if (!$to) {
    http_response_code(400);
    Res::error('Dirección de email inválida', 400);
    exit;
  }

  // Validar y sanitizar asunto
  $subject = validateAndSanitizeSubject($input['subject'], $userId);
  if (!$subject) {
    http_response_code(400);
    Res::error('Asunto inválido o demasiado largo', 400);
    exit;
  }

  // Validar y sanitizar contenido
  $isHtml = isset($input['isHtml']) ? (bool)$input['isHtml'] : false;
  $body = validateAndSanitizeBody($input['body'], $userId, $isHtml);
  if ($body === null) {
    http_response_code(400);
    Res::error('Contenido del email inválido o demasiado largo', 400);
    exit;
  }

  // Verificar configuración de email
  if (empty($_ENV['MAIL_HOST']) || empty($_ENV['MAIL_USERNAME'])) {
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::error('Email configuration missing', [
        'user_id' => $userId,
        'recipient' => $to
      ]);
    }

    http_response_code(500);
    Res::error('Configuración de email no disponible', 500);
    exit;
  }

  // Crear instancia de PHPMailer
  $mail = new PHPMailer(true);

  // Configuración segura del servidor
  $mail->isSMTP();
  $mail->Host = $_ENV['MAIL_HOST'];
  $mail->SMTPAuth = true;
  $mail->Username = $_ENV['MAIL_USERNAME'];
  $mail->Password = $_ENV['MAIL_PASSWORD'];
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = intval($_ENV['MAIL_PORT'] ?? 587);

  // Configuración adicional de seguridad
  $mail->SMTPAutoTLS = true;
  $mail->SMTPOptions = [
    'ssl' => [
      'verify_peer' => true,
      'verify_peer_name' => true,
      'allow_self_signed' => false
    ]
  ];

  // Configurar remitente
  $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@bubble-talents.com';
  $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Bubble of Talents';

  $mail->setFrom($fromAddress, $fromName);
  $mail->addAddress($to);
  $mail->addReplyTo($fromAddress, $fromName);

  // Configurar contenido
  $mail->isHTML($isHtml);
  $mail->Subject = $subject;
  $mail->Body = $body;

  if ($isHtml) {
    // Crear versión texto plano del HTML
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $body));
  }

  // Enviar email
  $mail->send();

  // Registrar envío exitoso
  EmailRateLimiter::recordEmailSent($userId);

  // Auditoría de seguridad - envío exitoso
  EmailSecurityAuditor::logEmailAttempt($userId, $to, $subject, true);

  // Log envío exitoso (sin contenido sensible)
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::info('Email sent successfully', [
      'user_id' => $userId,
      'sender_email' => $userPayload['email'] ?? 'unknown',
      'recipient' => $to,
      'subject_length' => strlen($subject),
      'is_html' => $isHtml
    ]);
  }

  Res::success('Email enviado exitosamente', [
    'recipient' => $to,
    'sent_at' => date('Y-m-d H:i:s'),
    'remaining_emails' => EmailRateLimiter::getRemainingEmails($userId)
  ]);
} catch (PHPMailerException $e) {
  // Auditoría de seguridad - envío fallido
  EmailSecurityAuditor::logEmailAttempt($userId, $to ?? 'unknown', $subject ?? 'unknown', false);

  // Log error de PHPMailer
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::error('Email sending failed', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'recipient' => $to ?? 'unknown',
      'error' => $e->getMessage()
    ]);
  }

  http_response_code(500);
  Res::error('Error al enviar email: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
  // Auditoría de seguridad - error general
  EmailSecurityAuditor::logEmailAttempt($userId, $to ?? 'unknown', $subject ?? 'unknown', false);

  // Log error general
  if (class_exists('\Utils\Logger')) {
    \Utils\Logger::error('Email endpoint error', [
      'user_id' => $userPayload['user_id'] ?? 'unknown',
      'error' => $e->getMessage(),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
  }

  http_response_code(500);
  Res::error('Error interno del servidor', 500);
}
