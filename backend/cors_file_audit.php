<?php

/**
 * Auditoría simplificada de CORS - Verificación de archivos
 */

declare(strict_types=1);

echo "🔍 AUDITORÍA CORS - VERIFICACIÓN DE ARCHIVOS\n";
echo "=" . str_repeat("=", 45) . "\n\n";

$issues = [];
$successes = [];

echo "📋 1. VERIFICANDO AUSENCIA DE WILDCARD ORIGINS...\n";

// Lista de archivos a verificar (excluir tests y docs)
$directories = [
  __DIR__ . '/api',
  __DIR__ . '/auth',
  __DIR__ . '/public',
  __DIR__ . '/config'
];

$wildcard_found = false;
foreach ($directories as $dir) {
  if (!is_dir($dir)) continue;

  $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
  foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;

    $content = file_get_contents($file->getPathname());
    if (preg_match('/Access-Control-Allow-Origin.*\*/', $content)) {
      echo "  ❌ Wildcard encontrado en: " . $file->getPathname() . "\n";
      $wildcard_found = true;
      $issues[] = "Wildcard origin en " . basename($file->getPathname());
    }
  }
}

if (!$wildcard_found) {
  echo "  ✅ No se encontraron wildcards en Access-Control-Allow-Origin\n";
  $successes[] = "No wildcards en origins";
}

echo "\n📋 2. VERIFICANDO CONFIGURACIÓN CENTRALIZADA...\n";

$config_files = [
  'cors.php' => 'Sistema principal CORS',
  'cors-granular.php' => 'Configuración granular',
  'cors-utils.php' => 'Utilidades CORS',
  'config/cors-granular.php' => 'Config granular',
  'config/cors-utils.php' => 'Config utils'
];

foreach ($config_files as $file => $desc) {
  if (file_exists(__DIR__ . '/' . $file)) {
    echo "  ✅ $desc\n";
    $successes[] = "$desc existe";
  } else {
    echo "  ❌ $desc NO encontrado\n";
    $issues[] = "$desc faltante";
  }
}

echo "\n📋 3. VERIFICANDO BOOTSTRAP EN API FILES...\n";

$api_files = glob(__DIR__ . '/api/*.php');
$bootstrap_count = 0;
$total_api = 0;

foreach ($api_files as $file) {
  if (basename($file) === 'bootstrap.php') continue;

  $content = file_get_contents($file);
  $total_api++;

  if (strpos($content, 'bootstrap.php') !== false) {
    $bootstrap_count++;
  } else {
    echo "  ⚠️ " . basename($file) . " no carga bootstrap\n";
  }
}

if ($bootstrap_count === $total_api) {
  echo "  ✅ Todos los archivos API cargan bootstrap ($bootstrap_count/$total_api)\n";
  $successes[] = "Todos los API cargan bootstrap";
} else {
  echo "  ⚠️ $bootstrap_count/$total_api archivos API cargan bootstrap\n";
}

echo "\n📋 4. VERIFICANDO FUNCIONES DEPRECATED...\n";

$deprecated_patterns = [
  'sendCors()' => 'Función sendCors inexistente',
  'preflight()' => 'Función preflight inexistente'
];

foreach ($deprecated_patterns as $pattern => $desc) {
  $found = false;
  foreach ($api_files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, $pattern) !== false && strpos($content, '//' . $pattern) === false) {
      echo "  ❌ $pattern encontrado en " . basename($file) . "\n";
      $found = true;
      $issues[] = "$desc en " . basename($file);
    }
  }

  if (!$found) {
    echo "  ✅ No se encontraron llamadas a $pattern\n";
    $successes[] = "No hay llamadas a $pattern";
  }
}

echo "\n📋 5. VERIFICANDO HEADERS MANUALES...\n";

$manual_headers = false;
foreach ($api_files as $file) {
  $content = file_get_contents($file);
  if (preg_match('/header\s*\(\s*[\'"]Access-Control/', $content)) {
    echo "  ❌ Headers manuales en " . basename($file) . "\n";
    $manual_headers = true;
    $issues[] = "Headers manuales en " . basename($file);
  }
}

if (!$manual_headers) {
  echo "  ✅ No se encontraron headers CORS manuales en API\n";
  $successes[] = "No headers manuales en API";
}

// RESUMEN
echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 RESUMEN DE AUDITORÍA\n";
echo str_repeat("=", 50) . "\n\n";

echo "✅ ÉXITOS (" . count($successes) . "):\n";
foreach ($successes as $success) {
  echo "  • $success\n";
}

if (!empty($issues)) {
  echo "\n❌ PROBLEMAS (" . count($issues) . "):\n";
  foreach ($issues as $issue) {
    echo "  • $issue\n";
  }
} else {
  echo "\n🎉 NO SE ENCONTRARON PROBLEMAS CRÍTICOS\n";
}

$total = count($successes) + count($issues);
$success_rate = $total > 0 ? round((count($successes) / $total) * 100, 1) : 0;

echo "\n📊 TASA DE ÉXITO: $success_rate%\n";

if ($success_rate >= 95) {
  echo "🟢 ESTADO: EXCELENTE\n";
} elseif ($success_rate >= 80) {
  echo "🟡 ESTADO: BUENO\n";
} else {
  echo "🔴 ESTADO: REQUIERE MEJORAS\n";
}

echo "\n";
