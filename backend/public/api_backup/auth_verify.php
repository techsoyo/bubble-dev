<?php
/**
 * Endpoint: /api/auth/verify y /api/auth/session
 * Verifica sesión y token JWT
 */
require_once __DIR__ . '/_bootstrap.php';

require_once __DIR__ . '/../../../src/Utils/ResponseHelper.php';
require_once __DIR__ . '/../../../src/Utils/JWTHelper.php';
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
    
    // También verificar en cookies
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
        Res::error('Token inválido o expirado', 401);
        exit;
    }
    
    $userModel = new User();
    $user = $userModel->findById($payload['user_id']);
    
    if (!$user) {
        http_response_code(401);
        Res::error('Usuario no encontrado', 401);
        exit;
    }
    
    Res::success('Sesión válida', [
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
    Res::error('Error en verificación de sesión', 500);
}
?>