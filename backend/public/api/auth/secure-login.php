<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__);             // auth -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Bloquear en producción si este endpoint no está completamente implementado
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

// ...existing code...

// Sanitiza entrada
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$pass  = (string)($input['password'] ?? '');

// Validaciones mínimas
if (!$email || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
  exit;
}

// TODO: autenticar contra tu fuente real (hash verificado, etc.)
// if (!auth_ok($email, $pass)) { ... }

// Marca de sesión mínima (solo dev)
$_SESSION['user_id'] = 123;

http_response_code(200);
echo json_encode(['ok' => true, 'message' => 'Login correcto']);
