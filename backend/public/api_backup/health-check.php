<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$ROOT = dirname(__DIR__, 2);             // health-check -> api -> public -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Simple health check endpoint for testing
jsonResponse(200, ['status' => 'ok', 'message' => 'API is running']);
