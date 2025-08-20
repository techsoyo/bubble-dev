<?php

declare(strict_types=1);
$ROOT = dirname(dirname(dirname(dirname(__DIR__)))); // Corregido: auth -> api -> public -> backend -> raiz
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Bloquear en producción
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

echo "pong AUTH-ALIAS";

