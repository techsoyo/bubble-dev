<?php

/**
 * Utilidades para configuración granular de CORS
 * 
 * Funciones helper para determinar y aplicar configuraciones
 * específicas de CORS basadas en el tipo de endpoint.
 * 
 * @author Bubble of Talents Security Team
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Detecta el tipo de endpoint basado en la URL actual
 * 
 * @return array Configuración CORS específica para el endpoint
 */
function detectEndpointType(): array
{
  static $corsConfig = null;

  if ($corsConfig === null) {
    $corsConfig = require __DIR__ . '/cors-granular.php';
  }

  $requestUri = $_SERVER['REQUEST_URI'] ?? '';
  $requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '';

  // Buscar configuración específica por patrón
  foreach ($corsConfig as $type => $config) {
    if ($type === 'default') continue; // Dejar default para el final

    $pattern = '#' . $config['pattern'] . '#i';
    if (preg_match($pattern, $requestPath)) {
      return array_merge($config, ['type' => $type]);
    }
  }

  // Si no coincide ningún patrón, usar configuración por defecto
  return array_merge($corsConfig['default'], ['type' => 'default']);
}

/**
 * Obtiene la configuración CORS optimizada para el endpoint actual
 * 
 * @return array Configuración con headers, métodos y opciones específicas
 */
function getOptimizedCorsConfig(): array
{
  $endpointConfig = detectEndpointType();
  $baseOrigins = explode(',', config('CORS_ALLOWED_ORIGINS', 'http://localhost:3002'));

  return [
    'allowed_origins' => $baseOrigins,
    'allow_credentials' => $endpointConfig['credentials'],
    'allowed_methods' => implode(', ', $endpointConfig['methods']),
    'allowed_headers' => implode(', ', $endpointConfig['headers']),
    'max_age' => $endpointConfig['max_age'],
    'endpoint_type' => $endpointConfig['type'],
    'description' => $endpointConfig['description']
  ];
}

/**
 * Verifica si el método HTTP actual está permitido para este endpoint
 * 
 * @param array $config Configuración CORS del endpoint
 * @return bool True si el método está permitido
 */
function isMethodAllowed(array $config): bool
{
  $currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $allowedMethods = explode(', ', $config['allowed_methods']);

  return in_array($currentMethod, $allowedMethods, true);
}

/**
 * Aplica headers CORS específicos para el tipo de endpoint
 * 
 * @param string $origin Origen de la solicitud
 * @param array $config Configuración CORS específica
 */
function applyGranularCorsHeaders(string $origin, array $config): void
{
  // Headers principales
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: {$config['allowed_methods']}");
  header("Access-Control-Allow-Headers: {$config['allowed_headers']}");
  header("Access-Control-Max-Age: {$config['max_age']}");

  // Credenciales solo si están habilitadas para este endpoint
  if ($config['allow_credentials']) {
    header('Access-Control-Allow-Credentials: true');
  }

  // Siempre incluir Vary para cacheo correcto
  header('Vary: Origin');
}

/**
 * Log eventos específicos de configuración granular
 * 
 * @param string $event Tipo de evento
 * @param array $data Datos adicionales del evento
 */
function logGranularCorsEvent(string $event, array $data = []): void
{
  if (!isDevelopment() && !isDebug()) {
    return;
  }

  $logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'event' => $event,
    'endpoint_type' => $data['endpoint_type'] ?? 'unknown',
    'request_path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
  ] + $data;

  error_log('CORS_GRANULAR: ' . json_encode($logData));
}
