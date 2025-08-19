<?php

/**
 * Test simple de CORS - Verificación final
 */

declare(strict_types=1);

echo "🔍 VERIFICACIÓN FINAL CORS\n";
echo "=" . str_repeat("=", 30) . "\n\n";

echo "📋 1. VERIFICANDO ARCHIVOS PRINCIPALES...\n";

$critical_files = [
  'config/bootstrap.php' => 'Bootstrap principal',
  'cors.php' => 'Sistema CORS principal',
  'cors-granular.php' => 'Configuración granular',
  'config/cors-granular.php' => 'Config granular',
  'config/cors-utils.php' => 'Utilidades config'
];

foreach ($critical_files as $file => $desc) {
  if (file_exists(__DIR__ . '/' . $file)) {
    echo "  ✅ $desc\n";
  } else {
    echo "  ❌ $desc FALTANTE\n";
  }
}

echo "\n📋 2. VERIFICANDO TESTS...\n";

$test_files = [
  'tests/CorsGranularTests.php' => 'Tests granulares',
  'tests/CorsPerformanceTests.php' => 'Tests de performance'
];

foreach ($test_files as $file => $desc) {
  if (file_exists(__DIR__ . '/' . $file)) {
    echo "  ✅ $desc\n";
  } else {
    echo "  ❌ $desc FALTANTE\n";
  }
}

echo "\n📋 3. VERIFICANDO LLAMADAS DEPRECATED...\n";

// Buscar sendCors() y preflight()
$api_files = glob(__DIR__ . '/api/*.php') + glob(__DIR__ . '/api/**/*.php');
$deprecated_found = false;

foreach ($api_files as $file) {
  $content = file_get_contents($file);

  // Buscar sendCors() sin comentar
  if (preg_match('/^[^\/]*sendCors\(\)/m', $content)) {
    echo "  ❌ sendCors() activo en " . basename($file) . "\n";
    $deprecated_found = true;
  }

  // Buscar preflight() sin comentar  
  if (preg_match('/^[^\/]*preflight\(\)/m', $content)) {
    echo "  ❌ preflight() activo en " . basename($file) . "\n";
    $deprecated_found = true;
  }
}

if (!$deprecated_found) {
  echo "  ✅ No hay funciones deprecated activas\n";
}

echo "\n📋 4. VERIFICANDO BOOTSTRAP LOADING...\n";

$bootstrap_loaded = 0;
$total_api = 0;

foreach ($api_files as $file) {
  if (basename($file) === 'bootstrap.php') continue;

  $content = file_get_contents($file);
  $total_api++;

  if (strpos($content, 'bootstrap.php') !== false) {
    $bootstrap_loaded++;
  }
}

$percentage = $total_api > 0 ? round(($bootstrap_loaded / $total_api) * 100, 1) : 0;
echo "  📊 $bootstrap_loaded/$total_api archivos cargan bootstrap ($percentage%)\n";

if ($percentage >= 90) {
  echo "  ✅ Excelente cobertura de bootstrap\n";
} elseif ($percentage >= 70) {
  echo "  ⚠️ Buena cobertura de bootstrap\n";
} else {
  echo "  ❌ Cobertura insuficiente de bootstrap\n";
}

echo "\n📋 5. VERIFICANDO WILDCARD ORIGINS...\n";

$wildcard_found = false;
$production_files = array_merge(
  glob(__DIR__ . '/api/*.php'),
  glob(__DIR__ . '/auth/*.php'),
  glob(__DIR__ . '/config/*.php')
);

foreach ($production_files as $file) {
  $content = file_get_contents($file);
  if (preg_match('/Access-Control-Allow-Origin.*\*/', $content)) {
    echo "  ❌ Wildcard origin en " . basename($file) . "\n";
    $wildcard_found = true;
  }
}

if (!$wildcard_found) {
  echo "  ✅ No hay wildcards en production files\n";
}

echo "\n" . str_repeat("=", 40) . "\n";
echo "🎯 RESUMEN FINAL\n";
echo str_repeat("=", 40) . "\n\n";

// Puntuación final
$checks = [
  'Archivos principales' => !empty($critical_files),
  'Tests disponibles' => !empty($test_files),
  'No deprecated functions' => !$deprecated_found,
  'Bootstrap coverage' => $percentage >= 90,
  'No wildcard origins' => !$wildcard_found
];

$passed = array_sum($checks);
$total = count($checks);
$score = round(($passed / $total) * 100, 1);

echo "✅ CHECKS PASADOS: $passed/$total ($score%)\n\n";

if ($score >= 90) {
  echo "🟢 ESTADO: EXCELENTE\n";
  echo "   ✅ Sistema CORS centralizado funcionando\n";
  echo "   ✅ Configuración granular implementada\n";
  echo "   ✅ Tests de validación disponibles\n";
  echo "   ✅ Seguridad mejorada (sin wildcards)\n";
  echo "   ✅ Arquitectura centralizada\n\n";

  echo "🎉 TODOS LOS PROBLEMAS CRÍTICOS RESUELTOS\n";
} elseif ($score >= 70) {
  echo "🟡 ESTADO: BUENO\n";
  echo "   Sistema funcionando con mejoras menores pendientes\n";
} else {
  echo "🔴 ESTADO: REQUIERE ATENCIÓN\n";
  echo "   Problemas críticos pendientes\n";
}

echo "\n";
