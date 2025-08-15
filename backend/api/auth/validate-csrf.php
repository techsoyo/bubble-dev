<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 2);             // auth -> api -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['token'] ?? '';

            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // Validate CSRF token
            $sessionToken = $_SESSION['csrf_token'] ?? '';
            $isValid = hash_equals($sessionToken, $token);

            $response = [
                'success' => $isValid,
                'message' => $isValid ? 'CSRF token is valid' : 'Invalid CSRF token'
            ];

            if (!$isValid) {
                http_response_code(403);
            }
            break;

        default:
            $response = [
                'success' => false,
                'message' => 'Method not allowed',
                'error' => 'Only POST method is allowed'
            ];
            http_response_code(405);
            break;
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ];
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
