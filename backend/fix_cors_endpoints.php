<?php

/**
 * Script para corregir llamadas deprecated CORS en endpoints
 */

$endpointsDir = __DIR__ . '/api/endpoints/';
$files = glob($endpointsDir . '*.php');

$corrections = 0;
$errors = 0;

echo "🔧 Corrigiendo archivos endpoints con funciones CORS deprecated...\n\n";

foreach ($files as $file) {
  $filename = basename($file);
  echo "📁 Procesando: $filename\n";

  $content = file_get_contents($file);
  $originalContent = $content;

  // Reemplazos necesarios
  $patterns = [
    '/^(\s*)sendCorsHeaders\(\);/m' => '$1// sendCorsHeaders(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php',
    '/^(\s*)preflightHandle\(\);/m' => '$1// preflightHandle(); // ELIMINADO: Preflight se maneja automáticamente en bootstrap.php',
    '/^(\s*)sendCors\(\);/m' => '$1// sendCors(); // ELIMINADO: CORS se configura automáticamente en bootstrap.php'
  ];

  $changed = false;
  foreach ($patterns as $pattern => $replacement) {
    if (preg_match($pattern, $content)) {
      $content = preg_replace($pattern, $replacement, $content);
      $changed = true;
      echo "  ✅ Corregido patrón deprecated\n";
    }
  }

  // Verificar que cargue bootstrap correctamente
  if (!preg_match('/require_once.*bootstrap\.php/', $content)) {
    echo "  ⚠️ No carga bootstrap.php - puede necesitar corrección manual\n";
  }

  if ($changed) {
    if (file_put_contents($file, $content)) {
      $corrections++;
      echo "  ✅ Archivo corregido exitosamente\n";
    } else {
      $errors++;
      echo "  ❌ Error escribiendo archivo\n";
    }
  } else {
    echo "  ℹ️ No necesita correcciones\n";
  }

  echo "\n";
}

echo "📊 RESUMEN:\n";
echo "  Archivos procesados: " . count($files) . "\n";
echo "  Archivos corregidos: $corrections\n";
echo "  Errores: $errors\n";

if ($corrections > 0) {
  echo "\n🎉 Correcciones completadas. Los endpoints ahora usan el sistema CORS centralizado.\n";
} else {
  echo "\n✅ Todos los archivos ya estaban correctos.\n";
}

echo "\n";
