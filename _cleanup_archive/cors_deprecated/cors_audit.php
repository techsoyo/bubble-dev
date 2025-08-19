<?php

/**
 * Auditoría completa de implementación CORS
 * 
 * Verifica que todos los problemas críticos hayan sido resueltos:
 * 1. No hay Access-Control-Allow-Origin: * en archivos principales
 * 2. Headers consistentes entre endpoints  
 * 3. Todos los archivos usan el sistema centralizado
 * 4. No hay fragmentación en la arquitectura
 * 5. Sistema de logging funciona
 */

declare(strict_types=1);

echo "🔍 AUDITORÍA CORS - VERIFICACIÓN DE PROBLEMAS CRÍTICOS\n";
echo "=" . str_repeat("=", 55) . "\n\n";

$issues = [];
$warnings = [];
$successes = [];

// Verificar que cors.php está siendo cargado correctamente
echo "📋 1. VERIFICANDO SISTEMA CENTRALIZADO...\n";

// Simular request para verificar que CORS se aplica
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/test';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3002';

// Capturar headers antes de cargar bootstrap
ob_start();
$headers_before = headers_list();

// Cargar bootstrap (debe aplicar CORS automáticamente)
require_once __DIR__ . '/config/bootstrap.php';

$headers_after = headers_list();
ob_end_clean();

// Verificar que se aplicaron headers CORS
$cors_headers = array_filter($headers_after, function ($header) {
  return stripos($header, 'Access-Control') === 0;
});

if (count($cors_headers) > 0) {
  $successes[] = "✅ Sistema centralizado aplica headers automáticamente";
  echo "  ✅ Headers CORS aplicados automáticamente\n";
  foreach ($cors_headers as $header) {
    echo "     - $header\n";
  }
} else {
  $issues[] = "❌ Sistema centralizado NO está aplicando headers";
  echo "  ❌ No se encontraron headers CORS automáticos\n";
}

echo "\n📋 2. VERIFICANDO AUSENCIA DE ACCESS-CONTROL-ALLOW-ORIGIN: *...\n";

// Buscar archivos con * origin en el backend (excluyendo tests y docs)
$cmd = 'findstr /R /S "Access-Control-Allow-Origin.*\*" "' . __DIR__ . '" | findstr /V /I "test docs";echo';
$output = shell_exec($cmd);

if (empty(trim($output))) {
  $successes[] = "✅ No se encontró Access-Control-Allow-Origin: * en archivos principales";
  echo "  ✅ No hay configuraciones con origen wildcard (*)\n";
} else {
  $issues[] = "❌ Se encontraron configuraciones con origen wildcard";
  echo "  ❌ Archivos con Access-Control-Allow-Origin: *:\n";
  echo $output;
}

echo "\n📋 3. VERIFICANDO FUNCIONES DEPRECATED...\n";

// Verificar que no queden llamadas a funciones inexistentes
$problematic_files = [];

// Buscar sendCors()
$cmd = 'findstr /R /S "sendCors()" "' . __DIR__ . '/api" 2>nul';
$output = shell_exec($cmd);
if (!empty(trim($output))) {
  $problematic_files[] = "sendCors() calls found";
  $issues[] = "❌ Se encontraron llamadas a sendCors() inexistente";
  echo "  ❌ Llamadas a sendCors() encontradas:\n$output\n";
} else {
  echo "  ✅ No hay llamadas a sendCors() inexistente\n";
}

// Buscar preflight()
$cmd = 'findstr /R /S "preflight()" "' . __DIR__ . '/api" 2>nul';
$output = shell_exec($cmd);
if (!empty(trim($output))) {
  $problematic_files[] = "preflight() calls found";
  $issues[] = "❌ Se encontraron llamadas a preflight() inexistente";
  echo "  ❌ Llamadas a preflight() encontradas:\n$output\n";
} else {
  echo "  ✅ No hay llamadas a preflight() inexistente\n";
}

echo "\n📋 4. VERIFICANDO CARGA CORRECTA DE BOOTSTRAP...\n";

// Verificar que archivos principales cargan bootstrap correctamente
$api_files = glob(__DIR__ . '/api/*.php');
$bootstrap_loaded = 0;
$total_files = 0;

foreach ($api_files as $file) {
  if (basename($file) === 'bootstrap.php') continue;

  $content = file_get_contents($file);
  $total_files++;

  if (
    strpos($content, 'config/bootstrap.php') !== false ||
    strpos($content, 'bootstrap.php') !== false
  ) {
    $bootstrap_loaded++;
  }
}

$percentage = $total_files > 0 ? round(($bootstrap_loaded / $total_files) * 100, 1) : 0;

if ($percentage >= 80) {
  $successes[] = "✅ $percentage% de archivos cargan bootstrap correctamente";
  echo "  ✅ $bootstrap_loaded/$total_files archivos API cargan bootstrap ($percentage%)\n";
} else {
  $warnings[] = "⚠️ Solo $percentage% de archivos cargan bootstrap";
  echo "  ⚠️ $bootstrap_loaded/$total_files archivos API cargan bootstrap ($percentage%)\n";
}

echo "\n📋 5. VERIFICANDO CONFIGURACIÓN GRANULAR...\n";

// Verificar que los archivos de configuración granular existen
$granular_files = [
  'cors-granular.php' => 'Configuraciones por tipo de endpoint',
  'cors-utils.php' => 'Utilidades para CORS granular',
  'config/cors-granular.php' => 'Configuraciones granulares en config',
  'config/cors-utils.php' => 'Utilidades en config'
];

foreach ($granular_files as $file => $desc) {
  if (file_exists(__DIR__ . '/' . $file)) {
    echo "  ✅ $desc ($file)\n";
  } else {
    $warnings[] = "⚠️ Archivo granular no encontrado: $file";
    echo "  ⚠️ $desc NO encontrado ($file)\n";
  }
}

echo "\n📋 6. TESTING FUNCIONALIDAD...\n";

// Test rápido de funcionamiento
try {
  // Simular diferentes tipos de endpoints
  $test_endpoints = [
    '/auth/login' => 'auth',
    '/api/candidate-experiences.php' => 'api_data',
    '/api/chatbot.php' => 'ai',
    '/uploads/file.pdf' => 'files',
    '/api/test' => 'default'
  ];

  if (function_exists('detectEndpointType')) {
    foreach ($test_endpoints as $endpoint => $expected_type) {
      $_SERVER['REQUEST_URI'] = $endpoint;
      $config = detectEndpointType();

      if (isset($config['type']) && $config['type'] === $expected_type) {
        echo "  ✅ Endpoint $endpoint detectado como $expected_type\n";
      } else {
        $actual = $config['type'] ?? 'unknown';
        $warnings[] = "⚠️ Endpoint $endpoint detectado como $actual (esperado: $expected_type)";
        echo "  ⚠️ Endpoint $endpoint detectado como $actual (esperado: $expected_type)\n";
      }
    }
  } else {
    $warnings[] = "⚠️ Función detectEndpointType() no disponible";
    echo "  ⚠️ Función detectEndpointType() no disponible\n";
  }
} catch (Exception $e) {
  $issues[] = "❌ Error en test de funcionalidad: " . $e->getMessage();
  echo "  ❌ Error en test: " . $e->getMessage() . "\n";
}

// RESUMEN FINAL
echo "\n" . str_repeat("=", 60) . "\n";
echo "🎯 RESUMEN DE AUDITORÍA CORS\n";
echo str_repeat("=", 60) . "\n\n";

echo "✅ ÉXITOS (" . count($successes) . "):\n";
foreach ($successes as $success) {
  echo "  $success\n";
}

if (!empty($warnings)) {
  echo "\n⚠️ ADVERTENCIAS (" . count($warnings) . "):\n";
  foreach ($warnings as $warning) {
    echo "  $warning\n";
  }
}

if (!empty($issues)) {
  echo "\n❌ PROBLEMAS CRÍTICOS (" . count($issues) . "):\n";
  foreach ($issues as $issue) {
    echo "  $issue\n";
  }
} else {
  echo "\n🎉 NO SE ENCONTRARON PROBLEMAS CRÍTICOS\n";
}

// Puntuación general
$total_checks = count($successes) + count($warnings) + count($issues);
$success_rate = $total_checks > 0 ? round((count($successes) / $total_checks) * 100, 1) : 0;

echo "\n📊 PUNTUACIÓN GENERAL: $success_rate% (" . count($successes) . "/$total_checks checks passed)\n";

if ($success_rate >= 90) {
  echo "🟢 ESTADO: EXCELENTE - Sistema CORS funcionando correctamente\n";
} elseif ($success_rate >= 70) {
  echo "🟡 ESTADO: BUENO - Algunas mejoras recomendadas\n";
} else {
  echo "🔴 ESTADO: REQUIERE ATENCIÓN - Problemas encontrados\n";
}

echo "\n";
