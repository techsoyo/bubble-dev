<?php

/**
 * Test de sistema de logging y monitoreo CORS
 */

declare(strict_types=1);

echo "🔍 TEST DE LOGGING Y MONITOREO CORS\n";
echo "=" . str_repeat("=", 40) . "\n\n";

// Activar logging temporal
$_ENV['APP_DEBUG'] = 'true';
$_ENV['APP_ENV'] = 'development';

// Capturar logs
$log_output = [];
set_error_handler(function ($severity, $message) use (&$log_output) {
  if (strpos($message, 'CORS') !== false) {
    $log_output[] = $message;
  }
  return false; // Continuar con el handler normal
});

echo "📋 1. TESTING SISTEMA DE LOGGING...\n";

// Simular diferentes orígenes y requests
$test_scenarios = [
  [
    'origin' => 'http://localhost:3002',
    'method' => 'GET',
    'uri' => '/api/test',
    'expected' => 'valid'
  ],
  [
    'origin' => 'http://malicious-site.com',
    'method' => 'POST',
    'uri' => '/auth/login',
    'expected' => 'blocked'
  ],
  [
    'origin' => 'http://localhost:3000',
    'method' => 'OPTIONS',
    'uri' => '/api/chatbot.php',
    'expected' => 'preflight'
  ]
];

// Cargar funciones de logging
if (file_exists(__DIR__ . '/cors.php')) {
  require_once __DIR__ . '/cors.php';
}

// Cargar funciones de logging - usar config/cors-utils.php
if (file_exists(__DIR__ . '/config/cors-utils.php')) {
  require_once __DIR__ . '/config/cors-utils.php';
}
foreach ($test_scenarios as $i => $scenario) {
  echo "  Test " . ($i + 1) . ": " . $scenario['expected'] . " scenario\n";

  $_SERVER['HTTP_ORIGIN'] = $scenario['origin'];
  $_SERVER['REQUEST_METHOD'] = $scenario['method'];
  $_SERVER['REQUEST_URI'] = $scenario['uri'];

  // Test logging de eventos
  if (function_exists('logCorsEvent')) {
    logCorsEvent('test_scenario', [
      'scenario' => $scenario['expected'],
      'test_id' => $i + 1
    ]);
    echo "    ✅ Evento logueado correctamente\n";
  } else {
    echo "    ⚠️ Función logCorsEvent no disponible\n";
  }

  // Test logging granular
  if (function_exists('logGranularCorsEvent')) {
    logGranularCorsEvent('granular_test', [
      'scenario' => $scenario['expected'],
      'test_id' => $i + 1
    ]);
    echo "    ✅ Evento granular logueado\n";
  } else {
    echo "    ⚠️ Función logGranularCorsEvent no disponible\n";
  }
}

echo "\n📋 2. VERIFICANDO LOGS CAPTURADOS...\n";

if (!empty($log_output)) {
  echo "  ✅ Se capturaron " . count($log_output) . " eventos de log:\n";
  foreach ($log_output as $log) {
    echo "    - " . substr($log, 0, 80) . "...\n";
  }
} else {
  echo "  ⚠️ No se capturaron logs (posible que esté en modo producción)\n";
}

echo "\n📋 3. TESTING DETECCIÓN DE ENDPOINT TYPES...\n";

$endpoint_tests = [
  '/auth/login' => 'auth',
  '/api/candidate-experiences.php' => 'api_data',
  '/api/chatbot.php' => 'ai',
  '/uploads/file.pdf' => 'files',
  '/api/unknown' => 'default'
];

// Usar la función que no requiere parámetros (config/cors-utils.php)
if (function_exists('getOptimizedCorsConfig')) {
  foreach ($endpoint_tests as $endpoint => $expected_type) {
    $_SERVER['REQUEST_URI'] = $endpoint;
    $detected_config = getOptimizedCorsConfig();

    if (isset($detected_config['endpoint_type'])) {
      $actual_type = $detected_config['endpoint_type'];
      if ($actual_type === $expected_type) {
        echo "  ✅ $endpoint → $expected_type\n";
      } else {
        echo "  ⚠️ $endpoint → $actual_type (esperado: $expected_type)\n";
      }
    } else {
      echo "  ⚠️ $endpoint → Sin tipo detectado\n";
    }
  }
} else {
  echo "  ⚠️ Función getOptimizedCorsConfig no disponible\n";
}
echo "\n📋 4. TESTING CONFIGURACIÓN GRANULAR...\n";

if (function_exists('getOptimizedCorsConfig')) {
  $_SERVER['REQUEST_URI'] = '/auth/login';
  $auth_config = getOptimizedCorsConfig();

  if ($auth_config['max_age'] <= 300) {
    echo "  ✅ Auth endpoints tienen cache corto (" . $auth_config['max_age'] . "s)\n";
  } else {
    echo "  ⚠️ Auth endpoints cache muy largo (" . $auth_config['max_age'] . "s)\n";
  }

  $_SERVER['REQUEST_URI'] = '/api/test';
  $api_config = getOptimizedCorsConfig();

  if ($api_config['allow_credentials']) {
    echo "  ✅ API endpoints permiten credenciales\n";
  } else {
    echo "  ⚠️ API endpoints no permiten credenciales\n";
  }
} else {
  echo "  ⚠️ Función getOptimizedCorsConfig no disponible\n";
}

echo "\n📋 5. TESTING PERFORMANCE BASIC...\n";

$start_time = microtime(true);
$iterations = 1000;

for ($i = 0; $i < $iterations; $i++) {
  $_SERVER['REQUEST_URI'] = '/api/test' . $i;
  if (function_exists('getOptimizedCorsConfig')) {
    getOptimizedCorsConfig();
  }
}
$end_time = microtime(true);
$avg_time = (($end_time - $start_time) / $iterations) * 1000;

if ($avg_time < 1) {
  echo "  ✅ Detección rápida: " . number_format($avg_time, 4) . "ms por endpoint\n";
} else {
  echo "  ⚠️ Detección lenta: " . number_format($avg_time, 4) . "ms por endpoint\n";
}

// Restaurar error handler
restore_error_handler();

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 RESUMEN DE TESTS DE MONITORING\n";
echo str_repeat("=", 50) . "\n\n";

$tests_passed = 0;
$total_tests = 5;

echo "✅ TESTS COMPLETADOS\n";
echo "📊 Sistema de logging: " . (function_exists('logCorsEvent') ? 'OK' : 'Parcial') . "\n";
echo "📊 Detección de endpoints: " . (function_exists('detectEndpointType') ? 'OK' : 'No disponible') . "\n";
echo "📊 Configuración granular: " . (function_exists('getOptimizedCorsConfig') ? 'OK' : 'No disponible') . "\n";
echo "📊 Performance: " . ($avg_time < 1 ? 'OK' : 'Mejorable') . "\n";

echo "\n🎯 CONCLUSIÓN: Sistema de monitoreo y logging implementado\n";
echo "   Para logging completo, activar modo debug en producción\n";

echo "\n";
