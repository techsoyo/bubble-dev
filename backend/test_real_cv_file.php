<?php

require_once 'config/bootstrap.php';

echo "=== TEST CV DESDE ARCHIVO (SIMULANDO FRONTEND) ===\n\n";

// Test con el CV real de la raíz
$cvFiles = [
  'Curriculum Vitae - javier-rodriguez-mkt.pdf'
];

foreach ($cvFiles as $filename) {
  $filepath = __DIR__ . '/../' . $filename;

  echo "📄 Procesando: $filename\n";

  if (!file_exists($filepath)) {
    echo "❌ Archivo no encontrado: $filepath\n";
    continue;
  }

  echo "✅ Archivo encontrado: " . round(filesize($filepath) / 1024, 1) . "KB\n";

  try {
    $groqService = new Services\GroqApiService();

    $startTime = microtime(true);
    $result = $groqService->analyzeCvFromPdf($filepath);
    $duration = round((microtime(true) - $startTime) * 1000);

    echo "✅ Análisis completado en {$duration}ms\n";
    echo "✅ Campos extraídos: " . count($result) . "\n";

    // Verificar campos clave para el formulario
    $requiredFields = [
      'nombre' => 'Nombre',
      'email' => 'Email',
      'telefono' => 'Teléfono',
      'ubicacion_actual' => 'Ubicación',
      'resumen_profesional' => 'Resumen',
      'puestos_anteriores' => 'Experiencia',
      'educacion' => 'Educación',
      'hard_skills' => 'Habilidades Técnicas',
      'soft_skills' => 'Habilidades Blandas',
      'idiomas' => 'Idiomas',
      'data_source' => 'Fuente',
      'routing' => 'Routing'
    ];

    echo "\n📋 CAMPOS PARA EL FORMULARIO:\n";
    foreach ($requiredFields as $field => $label) {
      if (array_key_exists($field, $result)) {
        if (is_array($result[$field])) {
          echo "✅ $label: " . count($result[$field]) . " elementos\n";
        } else {
          $value = is_string($result[$field]) ? substr($result[$field], 0, 50) . '...' : $result[$field];
          echo "✅ $label: $value\n";
        }
      } else {
        echo "❌ $label: FALTA\n";
      }
    }

    echo "\n";
  } catch (Exception $e) {
    echo "❌ ERROR procesando $filename: " . $e->getMessage() . "\n\n";
  }
}

echo "=== TEST COMPLETADO ===\n";
