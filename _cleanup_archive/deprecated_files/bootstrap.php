<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 1);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;



// CORS Middleware Bootstrap
// Lee configuración desde .env y expone sendCorsHeaders() y preflightHandle()

// Cargar variables de entorno
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [null, null]);
        if ($key && $value !== null && getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

function getEnvOrDefault($key, $default)
{
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

function parseAllowedOrigins($origins)
{
    return array_map('trim', explode(',', $origins));
}

function isLocalhost($origin)
{
    return preg_match('#^https?://(localhost|127\\.0\\.1)(:\\d+)?$#i', $origin);
}

function sendCorsHeaders(): void
{
    // CORS ya configurado en config/bootstrap.php - función mantenida por compatibilidad
    if (function_exists('error_log')) {
        error_log('DEPRECATION WARNING: sendCorsHeaders() ya no es necesario. CORS se configura automáticamente en config/bootstrap.php');
    }
}

function preflightHandle()
{
    // CORS ya configurado en config/bootstrap.php - función mantenida por compatibilidad
    if (function_exists('error_log')) {
        error_log('DEPRECATION WARNING: preflightHandle() ya no es necesario. CORS se configura automáticamente en config/bootstrap.php');
    }
}
