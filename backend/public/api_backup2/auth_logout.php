<?php
/**
 * Endpoint: /api/auth/logout y /api/staff/logout
 * Maneja logout universal
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../src/Utils/ResponseHelper.php';
require_once __DIR__ . '/../../../src/Utils/JWTHelper.php';

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
    
    // TambiÃ©n verificar en cookies
    if (!$token && isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
    }
    
    if ($token) {
        // Invalidar token (implementar blacklist si es necesario)
        JWTHelper::invalidateToken($token);
    }
    
    // Limpiar cookies de sesiÃ³n
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
