<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Generate CSRF token
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $token;

            $response = [
                'success' => true,
                'token' => $token,
                'message' => 'CSRF token generated successfully'
            ];
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
