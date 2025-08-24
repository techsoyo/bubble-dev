<?php
/**
 * Endpoint: /api/auth/register
 * Maneja login y registro de candidatos
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../src/Models/User.php';
require_once __DIR__ . '/../../../src/Utils/JWTHelper.php';
require_once __DIR__ . '/../../../src/Utils/RateLimiter.php';
require_once __DIR__ . '/../../../src/Utils/InputValidator.php';

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
    
    // Obtener y validar input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password']) || !isset($input['action'])) {
        http_response_code(400);
        Res::error('Email, password y action son requeridos', 400);
        exit;
    }
    
    $email = InputValidator::sanitizeEmail($input['email']);
    $password = $input['password'];
    $action = $input['action']; // 'login' o 'register'
    
    if (!$email) {
        http_response_code(400);
        Res::error('Email inválido', 400);
        exit;
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        Res::error('La contraseña debe tener al menos 6 caracteres', 400);
        exit;
    }
    
    $userModel = new User();
    
    if ($action === 'login') {
        // LOGIN DE CANDIDATO
        $user = $userModel->findByEmail($email);
        
        if (!$user || $user['role'] !== 'candidate') {
            http_response_code(401);
            Res::error('Credenciales incorrectas o usuario no es candidato', 401);
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
            'type' => 'candidate'
        ]);
        
        Res::success('Login de candidato exitoso', [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ],
            'token' => $tokenData['token'],
            'expires_in' => $tokenData['expires_in'],
            'refresh_token' => $tokenData['refresh_token']
        ]);
        
    } elseif ($action === 'register') {
        // REGISTRO DE CANDIDATO
        $firstName = isset($input['first_name']) ? InputValidator::sanitizeName($input['first_name']) : '';
        $lastName = isset($input['last_name']) ? InputValidator::sanitizeName($input['last_name']) : '';
        
        if (!$firstName || !$lastName) {
            http_response_code(400);
            Res::error('Nombre y apellido son requeridos para el registro', 400);
            exit;
        }
        
        // Verificar si el usuario ya existe
        $existingUser = $userModel->findByEmail($email);
        if ($existingUser) {
            http_response_code(409);
            Res::error('El usuario ya existe. Usa la opción de login.', 409);
            exit;
        }
        
        // Crear nuevo candidato
        $userData = [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $firstName . ' ' . $lastName,
            'role' => 'candidate',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $userId = $userModel->create($userData);
        
        if (!$userId) {
            http_response_code(500);
            Res::error('Error al crear el usuario', 500);
            exit;
        }
        
        // Generar JWT para el nuevo usuario
        $tokenData = JWTHelper::generateToken([
            'user_id' => $userId,
            'email' => $email,
            'role' => 'candidate',
            'type' => 'candidate'
        ]);
        
        Res::success('Registro de candidato exitoso', [
            'user' => [
                'id' => $userId,
                'email' => $email,
                'name' => $firstName . ' ' . $lastName,
                'role' => 'candidate',
                'first_name' => $firstName,
                'last_name' => $lastName
            ],
            'token' => $tokenData['token'],
            'expires_in' => $tokenData['expires_in'],
            'refresh_token' => $tokenData['refresh_token']
        ]);
        
    } else {
        http_response_code(400);
        Res::error('Action debe ser "login" o "register"', 400);
    }
    
} catch (Exception $e) {
    error_log("Candidate Auth Error: " . $e->getMessage());
    http_response_code(500);
    Res::error('Error en autenticación de candidato', 500);
}
?>