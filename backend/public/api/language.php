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

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
declare(strict_types=1);

try {
  $method = $_SERVER['REQUEST_METHOD'];

  switch ($method) {
    case 'GET':
      // Return default language
      $response = [
        'success' => true,
        'data' => [
          'language' => 'es'
        ],
        'message' => 'Language retrieved successfully'
      ];
      break;

    case 'POST':
      // Set language (could be saved to session or database)
      $input = json_decode(file_get_contents('php://input'), true);
      $language = $input['language'] ?? 'es';

      // Validate language
      if (!in_array($language, ['es', 'en'])) {
        $language = 'es';
      }

      // Save to session (or database as needed)
      session_start();
      $_SESSION['language'] = $language;

      $response = [
        'success' => true,
        'data' => [
          'language' => $language
        ],
        'message' => 'Language set successfully'
      ];
      break;

    default:
      $response = [
        'success' => false,
        'message' => 'Method not allowed',
        'error' => 'Only GET and POST methods are allowed'
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


