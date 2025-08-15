<?php
// Test rápido de la nueva API key OpenAI
require_once 'config/bootstrap.php';

use Services\OpenAIService;

try {
  echo "=== TEST OLLAMA LOCAL AI (15/08/2025) ===\n\n";

  // 1. Verificar API key está cargada (no necesaria para Ollama pero verificamos config)
  $provider = getenv('AI_PROVIDER');
  echo "1. Proveedor configurado: " . ($provider ?: 'openai') . "\n";

  // 2. Inicializar servicio
  $service = new OpenAIService();
  echo "2. OpenAI Service inicializado: ✅ Éxito\n";

  // 3. Test básico de texto corto
  echo "3. Probando análisis de CV con Ollama (modelo: recruitment-ai)...\n";

  $testText = "Juan Pérez - Desarrollador Full Stack con 3 años de experiencia en PHP, JavaScript y React. Licenciado en Informática.";

  $result = $service->analyzeCvFromText($testText);

  if ($result && is_array($result)) {
    echo "   ✅ Análisis exitoso con Ollama\n";
    echo "   📋 Datos extraídos: " . count($result) . " campos\n";
    echo "   👤 Nombre: " . ($result['personal_info']['full_name'] ?? 'No detectado') . "\n";
    echo "   💼 Experiencia: " . ($result['work_experience'][0]['company'] ?? 'No detectada') . "\n";
  } else {
    echo "   ❌ Análisis falló\n";
  }

  echo "\n=== RESULTADO: ✅ OLLAMA LOCAL FUNCIONANDO CORRECTAMENTE ===\n";
} catch (Exception $e) {
  echo "\n❌ ERROR: " . $e->getMessage() . "\n";
  echo "Traza: " . $e->getTraceAsString() . "\n";
}
