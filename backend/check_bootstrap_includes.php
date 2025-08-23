#!/usr/bin/env php
<?php

/**
 * Script para verificar y corregir includes de bootstrap en endpoints API
 */

echo "=== VERIFICACIÓN Y CORRECCIÓN DE BOOTSTRAP INCLUDES ===\n\n";

$apiDir = __DIR__ . '/public/api';
$files = glob($apiDir . '/*.php');
$correctedFiles = 0;
$problematicFiles = [];

foreach ($files as $file) {
  $filename = basename($file);

  // Saltar el archivo bootstrap.php principal
  if ($filename === 'bootstrap.php') {
    continue;
  }

  echo "📄 Revisando: $filename\n";

  $content = file_get_contents($file);
  $lines = explode("\n", $content);

  // Verificar las primeras 15 líneas buscando includes de bootstrap
  $hasCorrectInclude = false;
  $hasProblematicInclude = false;
  $lineNumber = 0;

  foreach (array_slice($lines, 0, 15) as $line) {
    $lineNumber++;
    $trimmedLine = trim($line);

    // Buscar require/include de bootstrap
    if (
      preg_match('/require.*bootstrap\.php/', $trimmedLine) ||
      preg_match('/include.*bootstrap\.php/', $trimmedLine)
    ) {

      if (strpos($trimmedLine, "__DIR__ . '/bootstrap.php'") !== false) {
        $hasCorrectInclude = true;
        echo "  ✅ Include correcto encontrado en línea $lineNumber\n";
      } else {
        $hasProblematicInclude = true;
        echo "  ⚠️  Include problemático en línea $lineNumber: $trimmedLine\n";
        $problematicFiles[] = ['file' => $file, 'line' => $lineNumber, 'content' => $trimmedLine];
      }
    }
  }

  if (!$hasCorrectInclude && !$hasProblematicInclude) {
    echo "  ❌ No se encontró include de bootstrap\n";
    $problematicFiles[] = ['file' => $file, 'line' => 0, 'content' => 'NO_INCLUDE'];
  }

  echo "\n";
}

echo "\n=== RESUMEN ===\n";
echo "📊 Archivos revisados: " . count($files) . "\n";
echo "⚠️  Archivos con problemas: " . count($problematicFiles) . "\n\n";

if (!empty($problematicFiles)) {
  echo "🔧 Archivos que necesitan corrección:\n";
  foreach ($problematicFiles as $problem) {
    $filename = basename($problem['file']);
    echo "  - $filename";
    if ($problem['line'] > 0) {
      echo " (línea {$problem['line']})";
    }
    echo "\n";
  }

  echo "\n💡 Para corregir, cada endpoint debe incluir:\n";
  echo "require_once __DIR__ . '/bootstrap.php';\n\n";
}

echo "=== VERIFICACIÓN COMPLETADA ===\n";
