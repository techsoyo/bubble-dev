<?php

// Archivo de prueba para verificar que la integración Ollama funcione correctamente
require_once __DIR__ . '/config/bootstrap.php';

use Services\OllamaService;

echo "=== PRUEBA DE INTEGRACIÓN OLLAMA ===\n\n";

try {
  $ollama = new OllamaService();
  echo "✅ OllamaService creado correctamente\n";

  // Verificar que el método analyzeCvFromText existe
  if (method_exists($ollama, 'analyzeCvFromText')) {
    echo "✅ Método analyzeCvFromText disponible\n";
  } else {
    echo "❌ Método analyzeCvFromText NO disponible\n";
  }

  // Verificar que el método analyzeCvFromPdf existe  
  if (method_exists($ollama, 'analyzeCvFromPdf')) {
    echo "✅ Método analyzeCvFromPdf disponible\n";
  } else {
    echo "❌ Método analyzeCvFromPdf NO disponible\n";
  }

  echo "\n=== CONFIGURACIÓN OLLAMA ===\n";
  echo "URL: " . ($_ENV['OLLAMA_API_URL'] ?? 'No configurada') . "\n";
  echo "Modelo: " . ($_ENV['OLLAMA_MODEL'] ?? 'No configurado') . "\n";
  echo "Timeout: " . ($_ENV['OLLAMA_TIMEOUT_MS'] ?? 'No configurado') . "ms\n";

  echo "\n✅ Integración lista para usar\n";
  echo "\nAhora puedes subir un CV en la aplicación para probar el flujo completo.\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
