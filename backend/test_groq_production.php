<?php

/**
 * Test de producción: GroqApiService con CV real
 * 
 * Prueba el servicio GroqApiService usando la API GRATUITA de Groq
 * con el CV real "Curriculum Vitae - javier-rodriguez-mkt.pdf"
 *
 * Requisitos:
 * 1. Variable de entorno GROQ_API_KEY configurada (GRATIS)
 * 2. Conexión a internet
 * 3. Groq API Key desde https://console.groq.com/keys
 */

require_once __DIR__ . '/autoload.php';

use Services\GroqApiService;
use Services\Exceptions\AiUnavailableException;

echo "\n=== TEST PRODUCCIÓN: GroqApiService (GRATIS) ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Verificar API Key
$apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');
if (empty($apiKey)) {
  echo "❌ ERROR: Variable GROQ_API_KEY no configurada\n";
  echo "💡 Obtener API Key GRATIS en: https://console.groq.com/keys\n";
  echo "💡 Configurar: \$env:GROQ_API_KEY=\"gsk_xxxxx\" (PowerShell)\n";
  echo "💡 O ejecutar: .\\setup_groq.ps1\n\n";
  exit(1);
}

echo "✅ API Key configurada: " . substr($apiKey, 0, 12) . "...\n";

// Ruta al CV real
$cvPath = __DIR__ . '/../Curriculum Vitae - javier-rodriguez-mkt.pdf';

if (!file_exists($cvPath)) {
  echo "❌ ERROR: CV no encontrado en: $cvPath\n";
  exit(1);
}

echo "✅ CV encontrado: " . basename($cvPath) . "\n";
echo "📁 Tamaño: " . round(filesize($cvPath) / 1024, 2) . " KB\n\n";

try {
  // Inicializar servicio
  echo "🚀 Inicializando GroqApiService...\n";
  $groqService = new GroqApiService();

  // Mostrar configuración
  $config = $groqService->getUsageInfo();
  echo "📋 Configuración:\n";
  foreach ($config as $key => $value) {
    if (is_array($value)) {
      echo "   • $key:\n";
      foreach ($value as $subKey => $subValue) {
        $displayValue = is_bool($subValue) ? ($subValue ? 'true' : 'false') : $subValue;
        echo "     - $subKey: $displayValue\n";
      }
    } else {
      echo "   • $key: $value\n";
    }
  }
  echo "\n";

  // Verificar disponibilidad de la API
  echo "🔍 Verificando disponibilidad de Groq API...\n";
  if ($groqService->isAvailable()) {
    echo "✅ Groq API disponible y respondiendo\n";

    // Mostrar modelos disponibles
    echo "🤖 Obteniendo modelos disponibles...\n";
    $models = $groqService->listAvailableModels();
    if (!empty($models)) {
      echo "📋 Modelos disponibles en Groq:\n";
      foreach (array_slice($models, 0, 5) as $model) {
        echo "   • " . ($model['id'] ?? 'unknown') . "\n";
      }
      if (count($models) > 5) {
        echo "   ... y " . (count($models) - 5) . " más\n";
      }
    }
  } else {
    echo "⚠️  Groq API no responde (continuando con prueba)\n";
  }
  echo "\n";

  // Procesar CV real
  echo "🤖 Analizando CV con Groq API (Llama3)...\n";
  echo "⏱️  Iniciando procesamiento (ultra rápido con LPU chips)...\n\n";

  $startTime = microtime(true);
  $cvData = $groqService->analyzeCvFromPdf($cvPath);
  $duration = round((microtime(true) - $startTime) * 1000);

  echo "🚀 ¡ÉXITO! CV analizado en {$duration}ms (¡Groq es súper rápido!)\n\n";

  // Mostrar resultados
  echo "📊 DATOS EXTRAÍDOS:\n";
  echo "==================\n\n";

  $fieldsToShow = [
    'nombre' => 'Nombre',
    'email' => 'Email',
    'telefono' => 'Teléfono',
    'ubicacion_actual' => 'Ubicación',
    'resumen_profesional' => 'Resumen Profesional',
    'hard_skills' => 'Habilidades Técnicas',
    'soft_skills' => 'Habilidades Blandas',
    'puestos_anteriores' => 'Experiencia Laboral',
    'educacion' => 'Educación',
    'idiomas' => 'Idiomas',
    'certificaciones' => 'Certificaciones'
  ];

  foreach ($fieldsToShow as $field => $label) {
    if (isset($cvData[$field])) {
      $value = $cvData[$field];

      if (is_array($value)) {
        if (empty($value)) {
          echo "• $label: (vacío)\n";
        } else {
          echo "• $label: " . count($value) . " elemento(s)\n";
          foreach (array_slice($value, 0, 3) as $item) {
            if (is_string($item)) {
              echo "  - $item\n";
            } elseif (is_array($item)) {
              $preview = json_encode($item, JSON_UNESCAPED_UNICODE);
              echo "  - " . substr($preview, 0, 80) . "...\n";
            }
          }
          if (count($value) > 3) {
            echo "  ... y " . (count($value) - 3) . " más\n";
          }
        }
      } else {
        $displayValue = strlen($value) > 100 ? substr($value, 0, 100) . "..." : $value;
        echo "• $label: $displayValue\n";
      }
      echo "\n";
    }
  }

  // Estadísticas finales
  echo "📈 ESTADÍSTICAS:\n";
  echo "================\n";
  echo "• Tiempo total: {$duration}ms (¡Ultra rápido!)\n";
  echo "• Campos extraídos: " . count($cvData) . "\n";
  echo "• Servicio: Groq API (GRATUITO)\n";
  echo "• Modelo usado: " . ($config['model'] ?? 'llama3-8b-8192') . "\n";
  echo "• Procesador: LPU (Language Processing Unit)\n";
  echo "• Fecha: " . date('Y-m-d H:i:s') . "\n\n";

  // Guardar resultado para inspección
  $resultFile = __DIR__ . '/groq_test_result_' . date('Y-m-d_H-i-s') . '.json';
  file_put_contents($resultFile, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  echo "💾 Resultado completo guardado en: " . basename($resultFile) . "\n";

  echo "\n🎉 ¡TEST COMPLETADO EXITOSAMENTE CON GROQ!\n";
  echo "💡 Groq es GRATUITO y súper rápido gracias a sus chips LPU especializados\n";
} catch (AiUnavailableException $e) {
  echo "\n❌ ERROR AI: " . $e->getMessage() . "\n";
  echo "💡 Verifica:\n";
  echo "   - API Key válida de https://console.groq.com/keys\n";
  echo "   - Conexión a internet\n";
  echo "   - Límites de rate (Groq es generoso pero tiene límites)\n\n";
  exit(1);
} catch (\Exception $e) {
  echo "\n❌ ERROR GENERAL: " . $e->getMessage() . "\n";
  echo "📍 Línea: " . $e->getLine() . " en " . $e->getFile() . "\n\n";
  exit(1);
}
