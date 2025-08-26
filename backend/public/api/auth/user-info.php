<?php

declare(strict_types=1);

use Security\CsrfMiddleware;

require_once __DIR__ . '/../bootstrap.php';
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

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
  }
}

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

