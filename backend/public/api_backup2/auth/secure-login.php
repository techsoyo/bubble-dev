<?php

declare(strict_types=1);
require_once $BOOT;

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

// Validaciones mÃ­nimas
if (!$email || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'ParÃ¡metros invÃ¡lidos']);
  exit;
}

// TODO: autenticar contra tu fuente real (hash verificado, etc.)
// if (!auth_ok($email, $pass)) { ... }

// Marca de sesiÃ³n mÃ­nima (solo dev)
$_SESSION['user_id'] = 123;

http_response_code(200);
echo json_encode(['ok' => true, 'message' => 'Login correcto']);

