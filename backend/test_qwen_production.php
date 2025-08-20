<?php

/**
 * Test de producción: QwenApiService con CV real
 * 
 * Prueba el servicio QwenApiService usando la API de Qwen (Alibaba Cloud)
 * con el CV real "Curriculum Vitae - javier-rodriguez-mkt.pdf"
 *
 * Requisitos:
 * 1. Variable de entorno QWEN_API_KEY configurada
 * 2. Cuenta en Alibaba Cloud con acceso a Model Studio
 * 3. Internet para acceder a la API
 */

require_once __DIR__ . '/autoload.php';

use Services\QwenApiService;
use Services\Exceptions\AiUnavailableException;

echo "\n=== TEST PRODUCCIÓN: QwenApiService ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Verificar API Key
$apiKey = $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY');
if (empty($apiKey)) {
  echo "❌ ERROR: Variable QWEN_API_KEY no configurada\n";
  echo "💡 Obtener API Key en: https://help.aliyun.com/zh/model-studio/developer-reference/get-api-key\n";
  echo "💡 Configurar: \$env:QWEN_API_KEY=\"sk-xxxxx\" (PowerShell)\n";
  echo "💡 O ejecutar: .\\setup_qwen.ps1\n\n";
  exit(1);
}

echo "✅ API Key configurada: " . substr($apiKey, 0, 8) . "...\n";

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
  echo "🚀 Inicializando QwenApiService...\n";
  $qwenService = new QwenApiService();

  // Mostrar configuración
  $config = $qwenService->getUsageInfo();
  echo "📋 Configuración:\n";
  foreach ($config as $key => $value) {
    echo "   • $key: $value\n";
  }
  echo "\n";

  // Verificar disponibilidad de la API
  echo "🔍 Verificando disponibilidad de Qwen API...\n";
  if ($qwenService->isAvailable()) {
    echo "✅ Qwen API disponible\n\n";
  } else {
    echo "⚠️  Qwen API no responde (continuando con prueba)\n\n";
  }

  // Procesar CV real
  echo "🤖 Analizando CV con Qwen API...\n";
  echo "⏱️  Iniciando procesamiento...\n\n";

  $startTime = microtime(true);
  $cvData = $qwenService->analyzeCvFromPdf($cvPath);
  $duration = round((microtime(true) - $startTime) * 1000);

  echo "✅ ÉXITO: CV analizado en {$duration}ms\n\n";

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
  echo "• Tiempo total: {$duration}ms\n";
  echo "• Campos extraídos: " . count($cvData) . "\n";
  echo "• Servicio: Qwen API (Alibaba Cloud)\n";
  echo "• Modelo usado: " . ($config['model'] ?? 'qwen-plus') . "\n";
  echo "• Fecha: " . date('Y-m-d H:i:s') . "\n\n";

  // Guardar resultado para inspección
  $resultFile = __DIR__ . '/qwen_test_result_' . date('Y-m-d_H-i-s') . '.json';
  file_put_contents($resultFile, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  echo "💾 Resultado completo guardado en: " . basename($resultFile) . "\n";

  echo "\n🎉 ¡TEST COMPLETADO EXITOSAMENTE!\n";
} catch (AiUnavailableException $e) {
  echo "\n❌ ERROR AI: " . $e->getMessage() . "\n";
  echo "💡 Verifica:\n";
  echo "   - API Key válida\n";
  echo "   - Conexión a internet\n";
  echo "   - Créditos disponibles en Alibaba Cloud\n\n";
  exit(1);
} catch (\Exception $e) {
  echo "\n❌ ERROR GENERAL: " . $e->getMessage() . "\n";
  echo "📍 Línea: " . $e->getLine() . " en " . $e->getFile() . "\n\n";
  exit(1);
}
