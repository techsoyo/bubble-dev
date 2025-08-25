<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Bloquear en producciÃƒÂ³n si este endpoint no estÃƒÂ¡ completamente implementado
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

// ...existing code...

// Sanitiza entrada
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$pass  = (string)($input['password'] ?? '');

// Validaciones mÃƒÂ­nimas
if (!$email || $pass === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'ParÃƒÂ¡metros invÃƒÂ¡lidos']);
  exit;
}

// TODO: autenticar contra tu fuente real (hash verificado, etc.)
// if (!auth_ok($email, $pass)) { ... }

// Marca de sesiÃƒÂ³n mÃƒÂ­nima (solo dev)
$_SESSION['user_id'] = 123;

http_response_code(200);
echo json_encode(['ok' => true, 'message' => 'Login correcto']);

