<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/auth/logout y /api/staff/logout
 * Maneja logout universal de forma segura
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

use Utils\ResponseHelper as Res;

try {
    // Obtener el token actual para invalidarlo
    $token = \Security\Cookies::getJwt();

    if ($token) {
        // Invalidar token agregándolo a blacklist
        $payload = \Middleware\JWTMiddleware::validateToken($token);
        if ($payload && isset($payload['jti'], $payload['exp'])) {
            // Agregar token a blacklist usando reflexión para acceder al método privado
            $reflection = new ReflectionClass('\Middleware\JWTMiddleware');
            $method = $reflection->getMethod('addToBlacklist');
            $method->setAccessible(true);
            $method->invoke(null, $payload['jti'], $payload['exp']);
        }
    }

    // Limpiar TODAS las cookies relacionadas con la sesión
    $cookiesToClear = [
        'auth_token',
        'csrf_token',
        'session_id',
        'user_session',
        'remember_token'
    ];

    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $secure = ($_ENV['APP_ENV'] ?? 'development') === 'production';
    $path = '/';

    foreach ($cookiesToClear as $cookieName) {
        if (isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, '', time() - 3600, $path, $domain, $secure, true);
            unset($_COOKIE[$cookieName]);
        }
    }

    // Log del evento de logout por seguridad
    if (class_exists('\Utils\Logger')) {
        \Utils\Logger::security('user_logout', [
            'user_id' => $userId,
            'user_role' => $userRole,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    Res::success('Logout exitoso', [
        'message' => 'Sesión cerrada correctamente',
        'redirect' => '/login'
    ]);
} catch (Exception $e) {
    error_log("Logout Error: " . $e->getMessage());
    http_response_code(500);
    Res::error('Error en logout', 500);
}
