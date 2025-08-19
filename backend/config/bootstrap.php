<?php

declare(strict_types=1);

/**
 * Archivo de inicialización de la aplicación
 * 
 * Configura el autoloader, carga la configuración y establece parámetros iniciales
 */


// Definir la ruta base de la aplicación
define('BASE_PATH', realpath(__DIR__ . '/..'));

// Definir la ruta de subida de archivos (UPLOAD_DIR)
define('UPLOAD_DIR', BASE_PATH . '/uploads');
// Cargar el autoloader de Composer con guard explícito
$__autoload = BASE_PATH . '/vendor/autoload.php';
if (!is_file($__autoload)) {
  http_response_code(500);
  echo 'Autoloader no encontrado: ' . $__autoload;
  exit;
}
require_once $__autoload;
// Cargar la configuración
require_once BASE_PATH . '/config/config.php';
// CORS integrado directamente - no archivo externo
require_once BASE_PATH . '/config/security-headers.php'; // headers de seguridad centralizados

require_once BASE_PATH . '/config/database.php';

// === CONFIGURACIÓN CORS INTEGRADA ===
// Solo se ejecuta para peticiones HTTP (no CLI)
if (PHP_SAPI !== 'cli' && !defined('CORS_APPLIED')) {
  define('CORS_APPLIED', true);

  // Obtener configuración CORS desde variables de entorno
  $corsOrigins = getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:3002';
  $corsCredentials = getenv('CORS_ALLOW_CREDENTIALS') === 'true';
  $corsMethods = getenv('CORS_ALLOWED_METHODS') ?: 'GET,POST,PUT,PATCH,DELETE,OPTIONS';
  $corsHeaders = getenv('CORS_ALLOWED_HEADERS') ?: 'Content-Type,Authorization,X-Requested-With';
  $corsMaxAge = (int)(getenv('CORS_MAX_AGE') ?: '86400');

  // Convertir orígenes a array
  $allowedOrigins = array_map('trim', explode(',', $corsOrigins));

  // Obtener origen de la petición
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

  // Verificar si el origen está permitido
  $isAllowedOrigin = in_array($origin, $allowedOrigins, true);

  // Aplicar headers CORS
  if ($isAllowedOrigin && $origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
  }

  if ($corsCredentials) {
    header('Access-Control-Allow-Credentials: true');
  }

  header('Access-Control-Allow-Methods: ' . $corsMethods);
  header('Access-Control-Allow-Headers: ' . $corsHeaders);
  header('Access-Control-Max-Age: ' . $corsMaxAge);

  // Manejar preflight OPTIONS
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}

// Autoloader manual para clases (en un proyecto real sería mejor usar Composer)
spl_autoload_register(function ($class) {
  // Convertir namespace separados por \ a rutas de directorio
  $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

  // Rutas posibles para buscar la clase (ajustar según la estructura del proyecto)
  $possiblePaths = [
    BASE_PATH . '/src/' . $class . '.php',
    BASE_PATH . '/' . $class . '.php',
  ];

  // Buscar el archivo en las rutas posibles
  foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
      require_once $path;
      return;
    }
  }
});

$envPath = BASE_PATH . '/.env';
if (is_file($envPath)) {
  foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || strpos($line, '=') === false) continue;
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    $v = trim($v, " \t\n\r\0\x0B\"'");
    putenv("$k=$v");
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
  }
}


// (C) X-Request-Id unificado (usar Utils\RequestId si existe)
if (class_exists('Utils\\RequestId')) {
  Utils\RequestId::init();
} else {
  $reqId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(16));
  header('X-Request-Id: ' . $reqId);
  $_SERVER['REQ_ID'] = $reqId;
}

// (D) Helper uniforme para respuestas JSON
if (!function_exists('jsonResponse')) {
  function jsonResponse(int $code, array $payload): void
  {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// Configurar zonas horarias
date_default_timezone_set('Europe/Madrid');

// REMOVED: header('Content-Type: application/json; charset=UTF-8'); // No Content-Type global

// Función para manejo de errores críticos
function handleFatalError()
{
  $error = error_get_last();
  if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
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

    echo json_encode($response);
  }
}

// Registrar función para errores fatales
register_shutdown_function('handleFatalError');

// Registrar controlador de excepciones no capturadas
set_exception_handler(function ($exception) {
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
      'trace' => $exception->getTraceAsString()
    ];
  }

  echo json_encode($response);
});
