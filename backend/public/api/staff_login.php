<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/staff/login
 * Maneja login seguro de staff/RRHH/recruiters con rate limiting y validación robusta
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
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

use Utils\ResponseHelper as Res;
use Utils\Logger;

/**
 * Clase para rate limiting básico en memoria
 */
class BasicRateLimiter
{
    private static $attempts = [];
    private static $maxAttempts = 5;
    private static $windowSeconds = 300; // 5 minutos

    public static function isAllowed(string $identifier): bool
    {
        $currentTime = time();

        // Limpiar entradas antiguas
        self::$attempts[$identifier] = array_filter(
            self::$attempts[$identifier] ?? [],
            function ($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < self::$windowSeconds;
            }
        );

        $attemptCount = count(self::$attempts[$identifier] ?? []);

        if ($attemptCount >= self::$maxAttempts) {
            return false;
        }

        self::$attempts[$identifier][] = $currentTime;
        return true;
    }

    public static function getRemainingAttempts(string $identifier): int
    {
        $currentTime = time();

        // Limpiar entradas antiguas
        self::$attempts[$identifier] = array_filter(
            self::$attempts[$identifier] ?? [],
            function ($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < self::$windowSeconds;
            }
        );

        $attemptCount = count(self::$attempts[$identifier] ?? []);
        return max(0, self::$maxAttempts - $attemptCount);
    }
}

/**
 * Validar y sanitizar email
 */
function validateAndSanitizeEmail(string $email): ?string
{
    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    // Validar longitud
    if (strlen($email) > 255) {
        return null;
    }

    return $email;
}

/**
 * Validar contraseña con reglas de complejidad
 */
function validatePassword(string $password): bool
{
    // Mínimo 8 caracteres, máximo 128
    if (strlen($password) < 8 || strlen($password) > 128) {
        return false;
    }

    // Debe contener al menos una letra minúscula, una mayúscula y un número
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        return false;
    }

    return true;
}

try {
    // Rate limiting por IP
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!BasicRateLimiter::isAllowed($clientIp)) {
        // Log intento de rate limit
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::security('staff_login_rate_limited', [
                'ip_address' => $clientIp,
                'remaining_attempts' => BasicRateLimiter::getRemainingAttempts($clientIp)
            ]);
        }

        http_response_code(429);
        Res::error('Demasiados intentos de login. Intenta nuevamente en 5 minutos.', 429);
        exit;
    }

    // Obtener y validar entrada
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        Res::error('Email y contraseña son requeridos', 400);
        exit;
    }

    // Sanitizar y validar email
    $email = validateAndSanitizeEmail($input['email']);
    if (!$email) {
        http_response_code(400);
        Res::error('Email inválido', 400);
        exit;
    }

    // Validar contraseña (solo validación de formato, no verificar contenido)
    $password = trim($input['password']);
    if (empty($password)) {
        http_response_code(400);
        Res::error('Contraseña requerida', 400);
        exit;
    }

    // Obtener conexión a base de datos
    $db = \Utils\Database::getInstance()->getConnection();

    // Buscar usuario por email
    $stmt = $db->prepare('
        SELECT id, email, password_hash, name, role, active
        FROM bt_staff_profiles
        WHERE email = ? AND active = 1
    ');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar credenciales
    if (!$user) {
        // Log intento fallido (sin información sensible)
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::security('staff_login_failed', [
                'email' => $email,
                'ip_address' => $clientIp,
                'reason' => 'user_not_found'
            ]);
        }

        http_response_code(401);
        Res::error('Credenciales incorrectas', 401);
        exit;
    }

    // Verificar rol autorizado
    $allowedRoles = ['admin', 'hr', 'recruiter', 'staff'];
    if (!in_array($user['role'], $allowedRoles)) {
        // Log intento de acceso no autorizado
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::security('staff_login_unauthorized_role', [
                'email' => $email,
                'role' => $user['role'],
                'ip_address' => $clientIp
            ]);
        }

        http_response_code(403);
        Res::error('Acceso no autorizado', 403);
        exit;
    }

    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        // Log intento fallido
        if (class_exists('\Utils\Logger')) {
            \Utils\Logger::security('staff_login_failed', [
                'email' => $email,
                'ip_address' => $clientIp,
                'reason' => 'wrong_password'
            ]);
        }

        http_response_code(401);
        Res::error('Credenciales incorrectas', 401);
        exit;
    }

    // Generar token JWT seguro
    $tokenPayload = [
        'user_id' => (int)$user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'user_type' => 'staff',
        'login_time' => time()
    ];

    $token = \Middleware\JWTMiddleware::generateToken($tokenPayload);

    // Configurar cookie HttpOnly segura
    $secure = ($_ENV['APP_ENV'] ?? 'development') === 'production';
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';

    setcookie('auth_token', $token, [
        'expires' => time() + 3600, // 1 hora
        'path' => '/',
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    // Log login exitoso
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::security('staff_login_successful', [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'ip_address' => $clientIp
        ]);
    }

    // Limpiar rate limiting en login exitoso
    unset(BasicRateLimiter::$attempts[$clientIp]);

    Res::success('Login de staff exitoso', [
        'user' => [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ],
        'expires_in' => 3600,
        'login_time' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    // Log error sin información sensible
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::error('Staff login error', [
            'error' => $e->getMessage(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    http_response_code(500);
    Res::error('Error en autenticación de staff', 500);
}
