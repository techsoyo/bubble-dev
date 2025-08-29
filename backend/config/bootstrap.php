<?php

declare(strict_types=1);
/**
 * Archivo de inicializaci?n de la aplicaci?n
 *
 * Configura el autoloader, carga la configuraci?n y establece par?metros iniciales
 */

// ===== DEFINICI?N DE CONSTANTES =====
if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__));
}

if (!defined('DS')) {
  define('DS', DIRECTORY_SEPARATOR);
}

// ===== CARGA DE VARIABLES DE ENTORNO =====
$envFile = realpath(BASE_PATH . DS . '..' . DS . '.env');
if ($envFile && file_exists($envFile)) {
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    // Saltar comentarios
    if (strpos(trim($line), '#') === 0) {
      continue;
    }

    // Separar clave y valor
    if (strpos($line, '=') !== false) {
      list($key, $value) = array_map('trim', explode('=', $line, 2));
      $value = trim($value, " \t\n\r\0\x0B\"'");

      // Establecer en el entorno si no existe
      if (!getenv($key)) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
      }
    }
  }
}

// ===== AUTOLOAD DE COMPOSER =====
$autoloadCandidates = [
  BASE_PATH . DS . 'vendor' . DS . 'autoload.php',          // backend/vendor/autoload.php
  dirname(BASE_PATH) . DS . 'vendor' . DS . 'autoload.php', // <repo>/vendor/autoload.php
];

$autoloaderPath = null;
foreach ($autoloadCandidates as $candidate) {
  if (is_file($candidate)) {
    $autoloaderPath = $candidate;
    break;
  }
}

if (!$autoloaderPath) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'Error: No se pudo encontrar el autoloader de Composer.' . PHP_EOL;
  echo 'Rutas probadas: ' . implode(', ', $autoloadCandidates);
  exit;
}

require_once $autoloaderPath;

// ===== CONFIGURACI��N DE ZONA HORARIA =====
date_default_timezone_set('Europe/Madrid');

// ===== FUNCIONES AUXILIARES =====
/**
 * Determina si la aplicación está en modo desarrollo
 * NOTA: Esta funci?n ahora se define en config.php para evitar duplicaci?n
 */

/**
 * Función uniforme para respuestas JSON
 */
function jsonResponse(int $code, array $payload): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

// ===== MANEJO DE ERRORES =====
/**
 * Manejo de errores fatales
 */
function handleFatalError(): void
{
  $error = error_get_last();
  if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    http_response_code(500);

    if (!headers_sent()) {
      header('Content-Type: application/json; charset=UTF-8');
    }

    $response = [
      'success' => false,
      'message' => isDevelopment() ? $error['message'] : 'Ha ocurrido un error interno del servidor',
      'data' => null
    ];

    if (isDevelopment()) {
      $response['error'] = [
        'file' => $error['file'],
        'line' => $error['line'],
        'type' => $error['type']
      ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  }
}

register_shutdown_function('handleFatalError');

/**
 * Manejo de excepciones no capturadas
 */
set_exception_handler(function (Throwable $exception): void {
  http_response_code(500);

  if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
  }

  $response = [
    'success' => false,
    'message' => isDevelopment() ? $exception->getMessage() : 'Ha ocurrido un error interno del servidor',
    'data' => null
  ];

  if (isDevelopment()) {
    $response['error'] = [
      'file' => $exception->getFile(),
      'line' => $exception->getLine(),
      'trace' => $exception->getTrace()
    ];
  }

  echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
});

// ===== X-Request-Id =====
if (class_exists('Utils\RequestId')) {
  Utils\RequestId::init();
} else {
  $reqId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(16));
  header('X-Request-Id: ' . $reqId);
  $_SERVER['REQ_ID'] = $reqId;
}

// ===== CARGA DE CONFIGURACIONES ADICIONALES =====
// Cargar la configuración
require_once BASE_PATH . DS . 'config' . DS . 'config.php';

// Headers de seguridad centralizados
require_once BASE_PATH . DS . 'config' . DS . 'security-headers.php';

// Configuración de base de datos
require_once BASE_PATH . DS . 'config' . DS . 'database.php';

// Middleware CORS - Se maneja en public/api/bootstrap.php
// require_once BASE_PATH . DS . 'config' . DS . 'middlewares' . DS . 'cors.php';
// Nota: El middleware CORS se ejecutará cuando sea llamado desde el bootstrap de API