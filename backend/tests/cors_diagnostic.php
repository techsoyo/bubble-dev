<?php

/**
 * Test de diagnóstico de headers CORS
 */

declare(strict_types=1);

echo "🔍 Debugging CORS headers issue...\n\n";

// Simular el entorno exacto del servidor web
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/auth/candidate-login.php';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3002';
$_SERVER['HTTP_HOST'] = 'localhost:8000';

echo "Environment:\n";
echo "  REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "  REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "  HTTP_ORIGIN: " . $_SERVER['HTTP_ORIGIN'] . "\n\n";

// Test 1: Verificar que las funciones están disponibles
echo "Test 1: Checking functions availability...\n";

try {
  require_once __DIR__ . '/../config/config.php';
  echo "  ✅ config.php loaded\n";

  if (function_exists('isDevelopment')) {
    echo "  ✅ isDevelopment() available: " . (isDevelopment() ? 'true' : 'false') . "\n";
  } else {
    echo "  ❌ isDevelopment() not available\n";
  }

  if (function_exists('config')) {
    echo "  ✅ config() available\n";
  } else {
    echo "  ❌ config() not available\n";
  }
} catch (Exception $e) {
  echo "  ❌ Error loading config: " . $e->getMessage() . "\n";
}

echo "\nTest 2: Checking CORS utilities...\n";

try {
  require_once __DIR__ . '/../cors-utils.php';
  echo "  ✅ cors-utils.php loaded\n";

  if (function_exists('detectEndpointType')) {
    $endpointType = detectEndpointType($_SERVER['REQUEST_URI']);
    echo "  ✅ detectEndpointType() working: $endpointType\n";
  } else {
    echo "  ❌ detectEndpointType() not available\n";
  }

  if (function_exists('getOptimizedCorsConfig')) {
    $config = getOptimizedCorsConfig('auth');
    echo "  ✅ getOptimizedCorsConfig() working\n";
    echo "    Allowed origins: " . implode(', ', $config['allowed_origins']) . "\n";
    echo "    Allowed methods: " . implode(', ', $config['allowed_methods']) . "\n";
  } else {
    echo "  ❌ getOptimizedCorsConfig() not available\n";
  }
} catch (Exception $e) {
  echo "  ❌ Error loading cors-utils: " . $e->getMessage() . "\n";
}

echo "\nTest 3: Manual CORS header test...\n";

try {
  // Simular aplicación manual de headers
  $origin = $_SERVER['HTTP_ORIGIN'];
  $endpointType = detectEndpointType($_SERVER['REQUEST_URI']);
  $config = getOptimizedCorsConfig($endpointType);

  echo "  Endpoint type: $endpointType\n";
  echo "  Origin: $origin\n";
  echo "  Allowed origins: " . implode(', ', $config['allowed_origins']) . "\n";

  if (in_array($origin, $config['allowed_origins'])) {
    echo "  ✅ Origin is allowed\n";
  } else {
    echo "  ❌ Origin is NOT allowed\n";
  }

  $method = $_SERVER['REQUEST_METHOD'];
  if (isMethodAllowed($method, $config)) {
    echo "  ✅ Method $method is allowed\n";
  } else {
    echo "  ❌ Method $method is NOT allowed\n";
  }
} catch (Exception $e) {
  echo "  ❌ Error in manual test: " . $e->getMessage() . "\n";
}

echo "\nTest 4: Checking if CORS constant is defined...\n";
if (defined('CORS_APPLIED')) {
  echo "  ✅ CORS_APPLIED is defined\n";
} else {
  echo "  ❌ CORS_APPLIED not defined\n";
}

echo "\nDiagnostic completed.\n";
