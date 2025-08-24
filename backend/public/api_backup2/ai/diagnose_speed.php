<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../bootstrap.php';
preflightHandle();
sendCorsHeaders();

// Proteger endpoint de diagnÃ³stico en entornos de producciÃ³n.
require_once dirname(__DIR__, 2) . '/config/config.php';
if (function_exists('isProduction') && isProduction()) {
    http_response_code(403);
    echo json_encode(['error' => 'Endpoint disabled in production']);
    exit;
}

// ...lÃ³gica original aquÃ­...
