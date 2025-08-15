<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1);             // ajusta salto de nivel según carpeta
$BOOT = $ROOT . '/config/bootstrap.php'; // si estás en /backend/public, sube 1 nivel; si estás en /backend/api, también 1
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Bloquear en producción: endpoint simplificado no debe estar expuesto
if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}

// REMOVED: header('Content-Type: application/json'); // Use jsonResponse() instead

// Función para responder con JSON (legacy wrapper)
function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// Obtener el método y la ruta
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Verificar si es login o verify
$isVerify = strpos($path, '/verify') !== false;

if ($method === 'POST') {
    if ($isVerify) {
        // Verificar token
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            jsonResponse(['success' => false, 'message' => 'No token provided'], 401);
        }

        $token = $matches[1];

        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);

            if (count($parts) !== 3) {
                throw new Exception('Invalid token format');
            }

            $userId = $parts[0];
            $role = $parts[1];
            $timestamp = $parts[2];

            // Verificar que el token no haya expirado (24 horas)
            $currentTime = time();
            $tokenAge = $currentTime - $timestamp;
            $maxAge = 24 * 60 * 60; // 24 horas

            if ($tokenAge > $maxAge) {
                throw new Exception('Token expired');
            }

            jsonResponse([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'userId' => $userId,
                    'role' => $role
                ]
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => 'Invalid token: ' . $e->getMessage()], 401);
        }
    } else {
        // Login
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['email']) || !isset($input['password'])) {
            jsonResponse(['success' => false, 'message' => 'Email and password are required'], 400);
        }

        $email = $input['email'];
        $password = $input['password'];

        // Credenciales de prueba
        $users = [
            'admin@bubble.com' => [
                'id' => '1',
                'name' => 'Administrator',
                'email' => 'admin@bubble.com',
                'password' => 'admin123',
                'role' => 'admin',
                'avatar' => null
            ],
            'recruiter@bubble.com' => [
                'id' => '2',
                'name' => 'Recruiter',
                'email' => 'recruiter@bubble.com',
                'password' => 'recruiter123',
                'role' => 'recruiter',
                'avatar' => null
            ],
            'candidate@bubble.com' => [
                'id' => '3',
                'name' => 'Candidate',
                'email' => 'candidate@bubble.com',
                'password' => 'candidate123',
                'role' => 'candidate',
                'avatar' => null
            ]
        ];

        if (isset($users[$email]) && $users[$email]['password'] === $password) {
            $user = $users[$email];
            unset($user['password']);

            // Generar token simple
            $token = base64_encode($user['id'] . ':' . $user['role'] . ':' . time());

            jsonResponse([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
    }
} else {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
