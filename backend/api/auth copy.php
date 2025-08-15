<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// CORS ya configurado en bootstrap.php - NO cargar nuevamente

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/input-sanitizer.php';
require_once __DIR__ . '/../src/Utils/Logger.php';
require_once __DIR__ . '/../src/Models/BaseModel.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Utils/JWT.php';

try {

    $db = getDbConnection();

    $method = $_SERVER['REQUEST_METHOD'];

    $action = $_GET['action'] ?? '';
    if ($method === 'POST') {
        if ($action === 'login') {
            login($db);
        } elseif ($action === 'verify') {
            verifyToken($db);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing or invalid action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function login($db)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $sanitized = InputSanitizer::sanitizeLoginData($input ?? []);
    if (!empty($sanitized['errors'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => implode('; ', $sanitized['errors'])]);
        return;
    }
    $email = $sanitized['email'];
    $password = $sanitized['password'];

    try {
        $userModel = new \Models\User();
        $user = $userModel->verifyCredentials($email, $password);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
            return;
        }
        // Preparar payload JWT
        $payload = [
            'user_id' => $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
            'name' => $user['name'] ?? null,
            'avatar' => $user['avatar'] ?? null
        ];
        $token = \Utils\JWT::generate($payload);
        // Limpiar datos sensibles
        unset($user['password']);
        unset($user['reset_token']);
        unset($user['reset_token_expiry']);
        unset($user['api_token']);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => $user
        ]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function verifyToken($db)
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No token provided']);
        return;
    }
    $token = trim($matches[1]);
    try {
        $payload = \Utils\JWT::verify($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
            return;
        }
        // Opcional: buscar usuario y devolver info básica
        $userModel = new \Models\User();
        $user = $userModel->findById($payload['user_id']);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        unset($user['password']);
        unset($user['reset_token']);
        unset($user['reset_token_expiry']);
        unset($user['api_token']);
        echo json_encode([
            'success' => true,
            'valid' => true,
            'user' => $user
        ]);
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token: ' . $e->getMessage()]);
    }
}
