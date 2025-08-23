#!/usr/bin/env php
<?php

/**
 * Script para corregir automáticamente includes de bootstrap en endpoints API
 */

echo "=== CORRECCIÓN AUTOMÁTICA DE BOOTSTRAP INCLUDES ===\n\n";

$apiDir = __DIR__ . '/public/api';
$files = glob($apiDir . '/*.php');
$correctedFiles = 0;
$errors = [];

// Define el include correcto que debe usar cada endpoint
$correctInclude = "require_once __DIR__ . '/bootstrap.php';";

foreach ($files as $file) {
  $filename = basename($file);

  // Saltar el archivo bootstrap.php principal
  if ($filename === 'bootstrap.php') {
    continue;
  }

  echo "🔧 Corrigiendo: $filename\n";

  $content = file_get_contents($file);
  $lines = explode("\n", $content);
  $modified = false;

  // Buscar y reemplazar includes problemáticos en las primeras 20 líneas
  for ($i = 0; $i < min(20, count($lines)); $i++) {
    $line = $lines[$i];
    $trimmedLine = trim($line);

    // Si encontramos una línea con require/include de bootstrap problemática
    if (
      preg_match('/require.*bootstrap\.php/', $trimmedLine) ||
      preg_match('/include.*bootstrap\.php/', $trimmedLine)
    ) {

      // Si no es el include correcto, reemplazarlo
      if (strpos($trimmedLine, "__DIR__ . '/bootstrap.php'") === false) {
        // Preservar la indentación de la línea original
        $indent = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
        $lines[$i] = $indent . $correctInclude;
        $modified = true;
        echo "  ✅ Reemplazado include en línea " . ($i + 1) . "\n";
      }
    }

    // Si es una línea declare(strict_types=1); y no hemos encontrado un include correcto aún
    elseif (strpos($trimmedLine, 'declare(strict_types=1);') !== false) {
      // Verificar si ya existe un include correcto en las siguientes líneas
      $hasCorrectInclude = false;
      for ($j = $i + 1; $j < min($i + 5, count($lines)); $j++) {
        if (strpos($lines[$j], "__DIR__ . '/bootstrap.php'") !== false) {
          $hasCorrectInclude = true;
          break;
        }
      }

      // Si no hay include correcto, agregarlo después del declare
      if (!$hasCorrectInclude) {
        // Insertar línea vacía y luego el include
        array_splice($lines, $i + 1, 0, ['', $correctInclude]);
        $modified = true;
        echo "  ✅ Agregado include después de declare en línea " . ($i + 2) . "\n";
      }
    }
  }

  // Si no encontramos declare y no hay include, agregar al principio después del <?php
  if (!$modified) {
    $foundPhpTag = false;
    for ($i = 0; $i < min(10, count($lines)); $i++) {
      if (strpos($lines[$i], '<?php') !== false) {
        // Insertar después del tag <?php y declare si existe
        $insertIndex = $i + 1;

        // Si la siguiente línea es declare, insertar después
        if ($insertIndex < count($lines) && strpos($lines[$insertIndex], 'declare(strict_types=1);') !== false) {
          $insertIndex++;
        }

        array_splice($lines, $insertIndex, 0, ['', $correctInclude]);
        $modified = true;
        echo "  ✅ Agregado include al principio del archivo\n";
        break;
      }
    }
  }

  // Guardar archivo si fue modificado
  if ($modified) {
    $newContent = implode("\n", $lines);
    if (file_put_contents($file, $newContent)) {
      $correctedFiles++;
      echo "  💾 Archivo guardado correctamente\n";
    } else {
      $errors[] = $filename;
      echo "  ❌ Error al guardar archivo\n";
    }
  } else {
    echo "  ➡️  No se necesitaron cambios\n";
  }

  echo "\n";
}

echo "\n=== RESUMEN FINAL ===\n";
echo "📊 Archivos procesados: " . count($files) . "\n";
echo "✅ Archivos corregidos: $correctedFiles\n";
echo "❌ Errores: " . count($errors) . "\n";

if (!empty($errors)) {
  echo "\n🚨 Archivos con errores:\n";
  foreach ($errors as $error) {
    echo "  - $error\n";
  }
}

if ($correctedFiles > 0) {
  echo "\n🎉 ¡Corrección completada! Ahora todos los endpoints deberían tener CORS funcionando.\n";
  echo "💡 Para verificar: php test_cors_all_endpoints.php\n";
}

echo "\n=== CORRECCIÓN COMPLETADA ===\n";
