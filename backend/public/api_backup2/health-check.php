<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once $BOOT;

// Simple health check endpoint for testing
jsonResponse(200, ['status' => 'ok', 'message' => 'API is running']);

