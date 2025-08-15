<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1);             // ajusta salto de nivel según carpeta
$BOOT = $ROOT . '/config/bootstrap.php'; // si estás en /backend/public, sube 1 nivel; si estás en /backend/api, también 1
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Bloquear este endpoint en producción
if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}

// REMOVED: header('Content-Type: application/json'); // Use jsonResponse() instead

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Funciones de autenticación
function login()
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['email']) || !isset($input['password'])) {
        jsonResponse(400, ['success' => false, 'error' => 'Email and password are required']);
    }

    $email = $input['email'];
    $password = $input['password'];

    http_response_code(501);
    echo json_encode(['success' => false, 'error' => 'No implementado']);
}

function verifyToken()
{
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No token provided']);
        return;
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

        echo json_encode([
            'success' => true,
            'valid' => true,
            'userId' => $userId,
            'role' => $role
        ]);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token: ' . $e->getMessage()]);
    }
}

// Procesar la solicitud
if ($method === 'POST') {
    if (strpos($path, '/verify') !== false) {
        verifyToken();
    } else {
        login();
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
