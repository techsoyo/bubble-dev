<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/OpenAIService.php';

// Cargar variables de entorno desde .env
if (file_exists(__DIR__ . '/.env')) {
  $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos($line, '#') === 0) continue; // Skip comments
    if (strpos($line, '=') !== false) {
      [$key, $value] = explode('=', $line, 2);
      $_ENV[trim($key)] = trim($value);
      putenv(trim($key) . '=' . trim($value));
    }
  }
}

use Services\OpenAIService;

try {
  echo "=== PRUEBA DEL SERVICIO OPENAI ===\n\n";

  // Verificar variables de entorno
  echo "API Key configurada: " . (isset($_ENV['OPENAI_API_KEY']) ? "Sí (longitud: " . strlen($_ENV['OPENAI_API_KEY']) . ")" : "No") . "\n";
  echo "Modelo configurado: " . ($_ENV['OPENAI_MODEL'] ?? 'No configurado') . "\n\n";

  $openaiService = new OpenAIService();

  // Test básico de conectividad
  echo "1. Probando conexión básica con OpenAI...\n";

  $testText = "Desarrollador Senior con 5 años de experiencia en PHP, JavaScript y MySQL. Licenciado en Informática.";

  $result = $openaiService->analyzeCVWithOpenAI($testText);

  if ($result) {
    echo "✅ Conexión exitosa con OpenAI\n";
    echo "Resultado del análisis:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
  } else {
    echo "❌ Error en la conexión con OpenAI\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Traza: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
