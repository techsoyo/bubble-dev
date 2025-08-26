<?php

declare(strict_types=1);

use Security\CsrfMiddleware;

require_once __DIR__ . '/../bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
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

// CSRF requerido para POST/PUT/DELETE
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
  CsrfMiddleware::validateToken();
}

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Bloquear en producciÃ³n si este endpoint no estÃ¡ completamente implementado
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

// ...existing code...

// Sanitiza entrada
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$pass  = (string)($input['password'] ?? '');

// Validaciones mÃƒÆ’Ã‚Â­nimas
if (!$email || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'ParÃƒÆ’Ã‚Â¡metros invÃƒÆ’Ã‚Â¡lidos']);
  exit;
}

// TODO: autenticar contra tu fuente real (hash verificado, etc.)
// if (!auth_ok($email, $pass)) { ... }

// Marca de sesiÃƒÆ’Ã‚Â³n mÃƒÆ’Ã‚Â­nima (solo dev)
$_SESSION['user_id'] = 123;

http_response_code(200);
echo json_encode(['ok' => true, 'message' => 'Login correcto']);
