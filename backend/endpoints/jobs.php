<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1); // endpoints → backend
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Redirect to the actual endpoint
require_once $ROOT . '/api/endpoints/jobs.php';
