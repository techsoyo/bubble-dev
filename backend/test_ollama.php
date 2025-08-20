<?php
// Test rápido para verificar que Ollama funciona con el análisis de CVs
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/src/Services/OllamaService.php';

use Services\OllamaService;

try {
  echo "🧪 Probando OllamaService...\n";

  $ollama = new OllamaService();

  // Texto de prueba de un CV simple
  $testCV = "
    Juan Pérez
    Email: juan.perez@email.com
    Teléfono: +34 600 123 456
    
    Experiencia:
    - Desarrollador Full Stack en TechCorp (2020-2023)
    - Desarrollador Junior en StartupXYZ (2018-2020)
    
    Educación:
    - Ingeniería Informática, Universidad de Madrid (2014-2018)
    
    Skills: JavaScript, PHP, React, Node.js
    ";

  echo "📄 Analizando CV de prueba...\n";
  $resultado = $ollama->analyzeCvFromText($testCV);

  echo "✅ ¡Análisis exitoso!\n";
  echo "📊 Resultado:\n";
  echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "💡 Posibles soluciones:\n";
  echo "   1. Verificar que Ollama esté ejecutándose: ollama serve\n";
  echo "   2. Verificar que el modelo esté disponible: ollama list\n";
  echo "   3. Revisar la configuración en .env\n";
}
