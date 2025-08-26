<?php

declare(strict_types=1);

use Security\Cookies;
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

// Solo permitir mÃ©todo POST para logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'MÃ©todo no permitido']);
  exit;
}

try {
  // Limpiar toda la sesiÃ³n (para compatibilidad con sistemas legacy)
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();

    // Limpiar cookie de sesiÃ³n
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
      );
    }
  }

  // Limpiar la cookie del JWT usando clase centralizada
  Cookies::unsetJwt();

  // Limpiar tokens CSRF
  CsrfMiddleware::clearToken();

  http_response_code(200);
  echo json_encode([
    'success' => true,
    'message' => 'Logout exitoso'
  ]);
} catch (Exception $e) {
  error_log("Error in logout.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
