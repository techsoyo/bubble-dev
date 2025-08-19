<?php

/**
 * Configuración CORS para producción - Pipeline Release
 * Gestiona CORS mediante variables de entorno definidas en .env
 */

declare(strict_types=1);

// Prevenir ejecución múltiple
if (defined('CORS_APPLIED')) {
    return;
}
define('CORS_APPLIED', true);

// Obtener configuración desde variables de entorno
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
} else {
    // Log de origen rechazado en desarrollo
    if (getenv('APP_ENV') === 'development') {
        error_log("CORS: Origen rechazado: $origin. Permitidos: " . implode(', ', $allowedOrigins));
    }
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
