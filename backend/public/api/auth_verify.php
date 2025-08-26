<?php


require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

/**
 * Endpoint: /api/auth/verify y /api/auth/session
 * Verifica sesiÃƒÆ’Ã‚Â³n y token JWT
 */
require_once __DIR__ . '/../../../src/Models/User.php';

use Utils\ResponseHelper as Res;

try {
    // Obtener token del header Authorization
    $headers = apache_request_headers();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            $token = $matches[1];
        }
    }
    
    // TambiÃƒÆ’Ã‚Â©n verificar en cookies
    if (!$token && isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }
    
    if (!$token) {
        http_response_code(401);
        Res::error('Token no encontrado', 401);
        exit;
    }
    
    $payload = JWTHelper::validateToken($token);
    
    if (!$payload) {
        http_response_code(401);
        Res::error('Token invÃƒÆ’Ã‚Â¡lido o expirado', 401);
        exit;
    }
    
    $userModel = new User();
    $user = $userModel->findById($payload['user_id']);
    
    if (!$user) {
        http_response_code(401);
        Res::error('Usuario no encontrado', 401);
        exit;
    }
    
    Res::success('SesiÃƒÆ’Ã‚Â³n vÃƒÆ’Ã‚Â¡lida', [
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Session Verification Error: " . $e->getMessage());
    http_response_code(500);
    Res::error('Error en verificaciÃƒÆ’Ã‚Â³n de sesiÃƒÆ’Ã‚Â³n', 500);
}
?>

