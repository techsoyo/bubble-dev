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

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
/**
 * Endpoint: /api/staff/login
 * Maneja login de staff/RRHH/recruiters
 */

require_once __DIR__ . '/../../../src/Models/User.php';

use Utils\ResponseHelper as Res;

try {
    // Rate limiting
    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'];
    
    if (!$rateLimiter->isAllowed($clientIp, 5, 300)) {
        http_response_code(429);
        Res::error('Demasiados intentos. Espera antes de intentar de nuevo.', 429);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        Res::error('Email y password son requeridos', 400);
        exit;
    }
    
    $email = InputValidator::sanitizeEmail($input['email']);
    $password = $input['password'];
    
    if (!$email) {
        http_response_code(400);
        Res::error('Email invÃƒÆ’Ã‚Â¡lido', 400);
        exit;
    }
    
    $userModel = new User();
    $user = $userModel->findByEmail($email);
    
    if (!$user || !in_array($user['role'], ['staff', 'recruiter', 'admin', 'rrhh'])) {
        http_response_code(401);
        Res::error('Credenciales incorrectas o acceso no autorizado', 401);
        exit;
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        Res::error('Credenciales incorrectas', 401);
        exit;
    }
    
    // Generar JWT
    $tokenData = JWTHelper::generateToken([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'type' => 'staff'
    ]);
    
    // Log successful login
    error_log("Staff login successful: {$user['email']} (role: {$user['role']})");
    
    Res::success('Login de staff exitoso', [
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ],
        'token' => $tokenData['token'],
        'expires_in' => $tokenData['expires_in'],
        'refresh_token' => $tokenData['refresh_token']
    ]);
    
} catch (Exception $e) {
    error_log("Staff Auth Error: " . $e->getMessage());
    http_response_code(500);
    Res::error('Error en autenticaciÃƒÆ’Ã‚Â³n de staff', 500);
}
?>

