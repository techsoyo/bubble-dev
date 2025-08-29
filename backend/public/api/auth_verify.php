<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/auth/verify y /api/auth/session
 * Verifica sesión y token JWT de forma segura
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
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

require_once __DIR__ . '/../../../src/Models/User.php';

use Models\User;
use Utils\ResponseHelper as Res;

try {
    // Verificar que el usuario existe en la base de datos
    $userModel = new User();
    $user = $userModel->findById($userId);

    if (!$user) {
        http_response_code(401);
        Res::error('Usuario no encontrado', 401);
        exit;
    }

    // Verificar que el usuario esté activo
    if (isset($user['status']) && $user['status'] !== 'active') {
        http_response_code(401);
        Res::error('Usuario inactivo', 401);
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
