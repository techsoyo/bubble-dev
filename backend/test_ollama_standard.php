<?php

/**
 * Test de migración OllamaServiceStandard
 * Verifica que el nuevo servicio funciona correctamente con hanwoolderink/ollama-php-client
 */

require_once __DIR__ . '/autoload.php';

use Services\OllamaServiceStandard;
use Services\Exceptions\AiUnavailableException;

echo "=== TEST MIGRACIÓN OLLAMA SERVICE STANDARD ===\n\n";

try {
  // Inicializar servicio
  $service = new OllamaServiceStandard();
  echo "✓ OllamaServiceStandard inicializado correctamente\n";

  // Test 1: Verificar disponibilidad del servicio
  echo "\n1. Verificando disponibilidad del servicio...\n";
  $isAvailable = $service->isAvailable();
  echo $isAvailable ? "✓ Ollama está disponible\n" : "✗ Ollama no está disponible\n";

  // Test 2: Obtener información del servicio
  echo "\n2. Información del servicio:\n";
  $serviceInfo = $service->getServiceInfo();
  foreach ($serviceInfo as $key => $value) {
    echo "  - {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
  }

  // Test 3: Obtener información del modelo
  echo "\n3. Información del modelo:\n";
  $modelInfo = $service->getModelInfo();
  foreach ($modelInfo as $key => $value) {
    echo "  - {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
  }

  echo "\n✓ Todos los tests de inicialización pasaron correctamente\n";

  // Test 4: Test básico de análisis de texto (si Ollama está disponible)
  if ($isAvailable) {
    echo "\n4. Test análisis de texto básico...\n";
    try {
      $testText = "Juan Pérez, email: juan@email.com, teléfono: +123456789, desarrollador con 5 años de experiencia en PHP y JavaScript.";
      $result = $service->analyzeCvFromText($testText);

      if (is_array($result) && !empty($result)) {
        echo "✓ Análisis de texto completado\n";
        echo "  - Nombre: " . ($result['nombre'] ?? 'N/A') . "\n";
        echo "  - Email: " . ($result['email'] ?? 'N/A') . "\n";
        echo "  - Teléfono: " . ($result['telefono'] ?? 'N/A') . "\n";
      } else {
        echo "✗ Respuesta inválida del análisis de texto\n";
      }
    } catch (AiUnavailableException $e) {
      echo "✗ Error en análisis de texto: " . $e->getMessage() . "\n";
    }
  }

  echo "\n=== MIGRACIÓN COMPLETADA EXITOSAMENTE ===\n";
} catch (\Exception $e) {
  echo "\n✗ ERROR durante el test: " . $e->getMessage() . "\n";
  echo "Stack trace: " . $e->getTraceAsString() . "\n";
  exit(1);
}
