<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Bloquear en producciÃƒÂ³n
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

echo "pong AUTH-ALIAS";

