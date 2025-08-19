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
        case 'GET':
            // Get user info from cookies/session
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // Check if user is logged in
            $userId = $_SESSION['user_id'] ?? null;
            $userEmail = $_SESSION['user_email'] ?? null;
            $userRole = $_SESSION['user_role'] ?? null;

            if ($userId && $userEmail) {
                $response = [
                  'success' => true,
                  'user' => [
                    'id' => $userId,
                    'email' => $userEmail,
                    'role' => $userRole,
                    'name' => $_SESSION['user_name'] ?? $userEmail
                  ],
                  'message' => 'User session is valid'
                ];
            } else {
                $response = [
                  'success' => false,
                  'message' => 'No valid session found'
                ];
                http_response_code(401);
            }
            break;

        default:
            $response = [
              'success' => false,
              'message' => 'Method not allowed',
              'error' => 'Only GET method is allowed'
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
