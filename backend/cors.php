<?php

/**
 * Configuración CORS segura y centralizada
 * 
 * ÚNICA FUENTE DE VERDAD para configuración CORS en toda la aplicación.
 * Este archivo se carga automáticamente desde config/bootstrap.php y 
 * NO debe ser incluido directamente desde otros archivos.
 * 
 * @author Bubble of Talents Security Team
 * @version 3.0.0 - Centralizada con logging de seguridad
 */

declare(strict_types=1);

// Prevenir ejecución en CLI y carga múltiple
if (PHP_SAPI === 'cli') {
  return;
}
if (defined('CORS_APPLIED')) {
  return;
}
define('CORS_APPLIED', true);

// Marcar timestamp de aplicación para auditoría
define('CORS_APPLIED_AT', microtime(true));

// Cargar configuración
require_once __DIR__ . '/config/config.php';

// 🎯 Cargar utilidades de configuración granular
require_once __DIR__ . '/cors-utils.php';

// 🛡️ Funciones de logging de seguridad
function logCorsEvent(string $event, array $data = []): void
{
  // Verificación defensiva para funciones de config
  if (!function_exists('isDevelopment') || !function_exists('isDebug')) {
    return; // Solo log si las funciones están disponibles
  }

  if (!isDevelopment() && !isDebug()) {
    return; // Solo log en desarrollo y debug
  }

  $logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'event' => $event,
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
  ] + $data;

  error_log('CORS_SECURITY: ' . json_encode($logData));
}

function logCorsViolation(string $reason, string $origin): void
{
  $violationData = [
    'violation_type' => 'CORS_ACCESS_DENIED',
    'reason' => $reason,
    'blocked_origin' => $origin,
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'none',
    'timestamp' => date('Y-m-d H:i:s')
  ];

  // Siempre log violaciones de seguridad, incluso en producción
  error_log('CORS_VIOLATION: ' . json_encode($violationData));
}

// ⚙️ Configuración CORS granular optimizada
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$endpointType = detectEndpointType($currentUri);
$corsConfig = getOptimizedCorsConfig($endpointType);
$allowedOrigins = $corsConfig['allowed_origins'];
$allowCredentials = $corsConfig['allow_credentials'];
$allowMethods = $corsConfig['allowed_methods'];
$allowHeaders = $corsConfig['allowed_headers'];
$maxAge = $corsConfig['max_age'];

// Detectar origen de la solicitud
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Log del evento de verificación CORS con información granular
logCorsEvent('CORS_CHECK_INITIATED', [
  'allowed_origins' => $allowedOrigins,
  'environment' => config('APP_ENV', 'unknown'),
  'endpoint_type' => $endpointType,
  'endpoint_description' => $corsConfig['description']
]);

// 🔍 Verificación de método permitido para este endpoint
$currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!isMethodAllowed($currentMethod, $corsConfig)) {
  logCorsViolation('METHOD_NOT_ALLOWED_FOR_ENDPOINT', $origin);
  logGranularCorsEvent('CORS_METHOD_BLOCKED', [
    'endpoint_type' => $endpointType,
    'blocked_method' => $currentMethod,
    'allowed_methods' => $allowMethods
  ]);

  // Devolver error 405 Method Not Allowed
  http_response_code(405);
  header('Allow: ' . implode(', ', $allowMethods));
  header('Content-Type: application/json');
  echo json_encode([
    'error' => 'Method Not Allowed',
    'message' => "Method {$currentMethod} is not allowed for this endpoint",
    'allowed_methods' => $allowMethods,
    'endpoint_type' => $endpointType
  ]);
  exit;
}

// Validar origen contra lista permitida
if ($origin && in_array($origin, $allowedOrigins, true)) {
  // Aplicar headers CORS granulares específicos para este endpoint
  applyGranularCorsHeaders($endpointType, $origin, $currentMethod);

  // Log acceso autorizado con información granular
  logCorsEvent('CORS_ACCESS_GRANTED', [
    'granted_origin' => $origin,
    'credentials_allowed' => $allowCredentials,
    'endpoint_type' => $endpointType,
    'max_age' => $maxAge
  ]);

  logGranularCorsEvent('CORS_GRANULAR_APPLIED', [
    'endpoint_type' => $endpointType,
    'methods' => $allowMethods,
    'headers' => $allowHeaders,
    'credentials' => $allowCredentials
  ]);
} else {
  // En desarrollo, permitir localhost con validación
  if (isDevelopment() && $origin && (
    strpos($origin, 'http://localhost:') === 0 ||
    strpos($origin, 'http://127.0.0.1:') === 0
  )) {
    // También aplicar configuración granular en desarrollo
    applyGranularCorsHeaders($endpointType, $origin, $currentMethod);


    // Log acceso de desarrollo con información granular
    logCorsEvent('CORS_DEV_ACCESS_GRANTED', [
      'dev_origin' => $origin,
      'reason' => 'localhost_development_mode',
      'endpoint_type' => $endpointType
    ]);

    logGranularCorsEvent('CORS_DEV_GRANULAR_APPLIED', [
      'endpoint_type' => $endpointType,
      'dev_origin' => $origin
    ]);
  } else {
    // Rechazar orígenes no autorizados
    if ($origin) {
      logCorsViolation('ORIGIN_NOT_ALLOWED', $origin);
      logGranularCorsEvent('CORS_ORIGIN_REJECTED', [
        'endpoint_type' => $endpointType,
        'rejected_origin' => $origin
      ]);
    }

    // En producción, no enviar headers CORS para orígenes no autorizados
    if (!isDevelopment()) {
      logCorsEvent('CORS_ACCESS_DENIED_PROD', [
        'denied_origin' => $origin,
        'environment' => 'production',
        'endpoint_type' => $endpointType
      ]);
    }
  }
}

// ⚠️ NOTA: Los headers principales ya se aplicaron con applyGranularCorsHeaders()
// Solo aplicamos headers adicionales si es necesario

// Log configuración granular aplicada
logCorsEvent('CORS_CONFIG_APPLIED', [
  'methods' => $allowMethods,
  'headers' => $allowHeaders,
  'max_age' => $maxAge,
  'endpoint_type' => $endpointType,
  'credentials' => $allowCredentials,
  'processing_time_ms' => round((microtime(true) - CORS_APPLIED_AT) * 1000, 2)
]);

logGranularCorsEvent('CORS_FINAL_CONFIG', [
  'endpoint_type' => $endpointType,
  'final_config' => $corsConfig,
  'headers' => $allowHeaders,
  'max_age' => $maxAge,
  'processing_time_ms' => round((microtime(true) - CORS_APPLIED_AT) * 1000, 2)
]);

// Respuesta optimizada para preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  logCorsEvent('CORS_PREFLIGHT_HANDLED', [
    'origin' => $origin,
    'method' => 'OPTIONS'
  ]);
  http_response_code(204); // No Content
  exit;
}
