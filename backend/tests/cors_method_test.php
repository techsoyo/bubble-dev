<?php

/**
 * Test de sistema CORS sin restricciones CLI
 */

declare(strict_types=1);

// Simular entorno web
if (!defined('CORS_APPLIED')) {
  define('CORS_APPLIED', true);
}
define('CORS_APPLIED_AT', microtime(true));

// Cargar dependencias mínimas
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../cors-utils.php';

// Simular request
$_SERVER['REQUEST_METHOD'] = 'PUT';
$_SERVER['REQUEST_URI'] = '/auth/candidate-login.php';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3002';

echo "🧪 Testing CORS method restrictions...\n";

// Obtener configuración
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$endpointType = detectEndpointType($currentUri);
$corsConfig = getOptimizedCorsConfig($endpointType);

echo "Endpoint Type: $endpointType\n";
echo "Allowed Methods: " . implode(', ', $corsConfig['allowed_methods']) . "\n";

// Verificar método
$currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$methodAllowed = isMethodAllowed($currentMethod, $corsConfig);

echo "Current Method: $currentMethod\n";
echo "Method Allowed: " . ($methodAllowed ? 'Yes' : 'No') . "\n";

if (!$methodAllowed) {
  echo "✅ Method restriction working - PUT not allowed for auth endpoints\n";
} else {
  echo "❌ Method restriction NOT working - PUT should not be allowed\n";
}
