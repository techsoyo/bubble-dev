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
 * Endpoint: /api/auth/logout y /api/staff/logout
 * Maneja logout universal
 */
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
    
    if ($token) {
        // Invalidar token (implementar blacklist si es necesario)
        JWTHelper::invalidateToken($token);
    }
    
    // Limpiar cookies de sesiÃƒÆ’Ã‚Â³n
    if (isset($_COOKIE['auth_token'])) {
        setcookie('auth_token', '', time() - 3600, '/', '', true, true);
    }
    
    Res::success('Logout exitoso', []);
    
} catch (Exception $e) {
    error_log("Logout Error: " . $e->getMessage());
    http_response_code(500);
    Res::error('Error en logout', 500);
}
?>

