<?php

/**
 * Utilidades para el sistema CORS granular
 * 
 * Funciones de ayuda para detectar tipos de endpoint
 * y aplicar configuraciones CORS específicas.
 * 
 * @author Bubble of Talents Security Team
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Detecta el tipo de endpoint basado en patrones de URL
 */
function detectEndpointType(string $requestUri): string
{
  // Cargar configuraciones granulares
  $granularConfigs = require __DIR__ . '/cors-granular.php';

  // Normalizar URI
  $path = parse_url($requestUri, PHP_URL_PATH) ?? $requestUri;
  $path = strtolower($path);

  // Verificar cada tipo de endpoint (excepto default)
  foreach ($granularConfigs as $type => $config) {
    if ($type === 'default') continue;

    foreach ($config['patterns'] as $pattern) {
      if (strpos($path, strtolower($pattern)) !== false) {
        return $type;
      }
    }
  }

  return 'default';
}

/**
 * Obtiene la configuración CORS optimizada para un tipo específico
 */
function getOptimizedCorsConfig(string $endpointType): array
{
  $granularConfigs = require __DIR__ . '/cors-granular.php';

  return $granularConfigs[$endpointType] ?? $granularConfigs['default'];
}

/**
 * Verifica si un método HTTP está permitido para un endpoint
 */
function isMethodAllowed(string $method, array $corsConfig): bool
{
  return in_array(strtoupper($method), $corsConfig['allowed_methods']);
}

/**
 * Verifica si un origen está permitido para un endpoint
 */
function isOriginAllowed(string $origin, array $corsConfig): bool
{
  $allowedOrigins = $corsConfig['allowed_origins'];

  // Si permite todos los orígenes
  if (in_array('*', $allowedOrigins)) {
    return true;
  }

  // Verificar origen exacto
  if (in_array($origin, $allowedOrigins)) {
    return true;
  }

  // Verificar patrones (ej: *.localhost)
  foreach ($allowedOrigins as $allowedOrigin) {
    if (strpos($allowedOrigin, '*') !== false) {
      $pattern = str_replace('*', '.*', preg_quote($allowedOrigin, '/'));
      if (preg_match("/^{$pattern}$/", $origin)) {
        return true;
      }
    }
  }

  return false;
}

/**
 * Aplica headers CORS granulares basados en la configuración
 */
function applyGranularCorsHeaders(string $endpointType, string $origin = '', string $method = 'GET'): bool
{
  $config = getOptimizedCorsConfig($endpointType);

  // Verificar origen
  if (!empty($origin) && !isOriginAllowed($origin, $config)) {
    return false;
  }

  // Verificar método
  if (!isMethodAllowed($method, $config)) {
    return false;
  }

  // Aplicar headers CORS
  $allowedOrigin = in_array('*', $config['allowed_origins']) ? '*' : $origin;

  header('Access-Control-Allow-Origin: ' . $allowedOrigin);
  header('Access-Control-Allow-Methods: ' . implode(', ', $config['allowed_methods']));
  header('Access-Control-Allow-Headers: ' . implode(', ', $config['allowed_headers']));
  header('Access-Control-Max-Age: ' . $config['max_age']);

  if ($config['allow_credentials']) {
    header('Access-Control-Allow-Credentials: true');
  }

  // Header Vary para caching apropiado
  if (!in_array('*', $config['allowed_origins'])) {
    header('Vary: Origin');
  }

  return true;
}

/**
 * Genera estadísticas de uso de configuraciones CORS
 */
function getCorsUsageStats(): array
{
  $granularConfigs = require __DIR__ . '/cors-granular.php';
  $stats = [];

  foreach ($granularConfigs as $type => $config) {
    $stats[$type] = [
      'origins_count' => count($config['allowed_origins']),
      'methods_count' => count($config['allowed_methods']),
      'headers_count' => count($config['allowed_headers']),
      'security_level' => $config['security_level'],
      'max_age' => $config['max_age'],
      'allow_credentials' => $config['allow_credentials']
    ];
  }

  return $stats;
}

/**
 * Registra eventos específicos del sistema CORS granular
 */
function logGranularCorsEvent(string $event, array $data = []): void
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
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'granular_data' => $data
  ];

  error_log('CORS_GRANULAR: ' . json_encode($logData));
}
