<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Solo permitir método POST para logout
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

try {
  // Limpiar toda la sesión
  session_unset();
  session_destroy();

  // Limpiar cookie de sesión
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
