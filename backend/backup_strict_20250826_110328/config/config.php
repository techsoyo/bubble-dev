<?php declare(strict_types=1);


/**
 * config.php
 * GestiÃ³n de configuraciÃ³n segura y robusta para producciÃ³n
 * @version 2.1.0
 */

declare(strict_types=1);

// Zona horaria
date_default_timezone_set('UTC');

/**
 * Carga variables de entorno desde .env
 */
function loadEnvironmentVars(): bool
{
  $envPath = __DIR__ . '/../.env';
  if (!file_exists($envPath) || !is_readable($envPath)) {
    error_log('ADVERTENCIA: .env no encontrado o no legible');
    return false;
  }

  $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$name, $value] = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value, "\"'");
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) continue;
    putenv("$name=$value");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
  }
  return true;
}

loadEnvironmentVars();

/**
 * Obtiene valor de configuraciÃ³n
 */
function config(string $key, $default = null)
{
  if (!preg_match('/^[A-Za-z0-9_]+$/', $key)) return $default;
  $val = getenv($key);
  return $val !== false ? $val : $default;
}

function isDevelopment(): bool
{
  return in_array(strtolower(config('APP_ENV', 'production')), ['dev', 'development', 'local', 'test'], true);
}

function isProduction(): bool
{
  return in_array(strtolower(config('APP_ENV', 'production')), ['prod', 'production'], true);
}

function isDebug(): bool
{
  return in_array(strtolower(config('APP_DEBUG', 'false')), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Manejo de errores
 */
if (isDevelopment() && isDebug()) {
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
} else {
  error_reporting(E_ALL);
  ini_set('display_errors', '0');
  ini_set('log_errors', '1');
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) mkdir($logDir, 0755, true);
  ini_set('error_log', "$logDir/error.log");
}

/**
 * ValidaciÃ³n de configuraciones crÃ­ticas
 */
foreach (['JWT_SECRET', 'DB_HOST', 'DB_NAME'] as $key) {
  if (empty(config($key))) error_log("ERROR CRÃTICO: ConfiguraciÃ³n faltante: $key");
}
if ($jwt = config('JWT_SECRET')) {
  if (strlen($jwt) < 32) error_log('ADVERTENCIA: JWT_SECRET < 32 caracteres');
}

/**
 * CORS seguro
 */
function corsAllowedOrigins(): array
{
  $raw = config('CORS_ALLOWED_ORIGINS', 'http://localhost:3002,http://127.0.0.1:3002');
  $origins = array_filter(array_map('trim', explode(',', $raw)));
  if (in_array(strtolower(config('CORS_ALLOW_CREDENTIALS', 'true')), ['1', 'true', 'yes', 'on'], true)) {
    $origins = array_filter($origins, fn($o) => $o !== '*');
  }
  return array_values(array_unique($origins));
}
