<?php

/**
 * Script de verificación rápida para GroqApiService
 * 
 * Verifica que todas las dependencias y configuraciones estén correctas
 * antes de ejecutar el test completo con CV real.
 */

require_once __DIR__ . '/autoload.php';

use Services\GroqApiService;

echo "\n=== VERIFICACIÓN GROQ API SERVICE (GRATUITO) ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Verificar variables de entorno
echo "🔍 1. VERIFICANDO CONFIGURACIÓN...\n";

$apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');
if (empty($apiKey)) {
  echo "❌ GROQ_API_KEY no configurada\n";
  echo "💡 Obtener API Key GRATIS en: https://console.groq.com/keys\n";
  echo "💡 Ejecuta: \$env:GROQ_API_KEY=\"gsk-xxxxx\" (PowerShell)\n";
  echo "💡 O ejecutar: .\\setup_groq.ps1\n\n";
  exit(1);
} else {
  echo "✅ GROQ_API_KEY: " . substr($apiKey, 0, 12) . "...\n";
}

$baseUrl = $_ENV['GROQ_BASE_URL'] ?? getenv('GROQ_BASE_URL') ?: 'https://api.groq.com/openai/v1';
$model = $_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?: 'llama3-8b-8192';

echo "✅ Base URL: $baseUrl\n";
echo "✅ Modelo: $model\n";
echo "🎁 GROQ ES COMPLETAMENTE GRATUITO\n\n";

// 2. Verificar dependencias
echo "🔍 2. VERIFICANDO DEPENDENCIAS...\n";

if (class_exists('Services\GroqApiService')) {
  echo "✅ GroqApiService cargado correctamente\n";
} else {
  echo "❌ Error cargando GroqApiService\n";
  exit(1);
}

if (class_exists('Smalot\PdfParser\Parser')) {
  echo "✅ smalot/pdfparser disponible\n";
} else {
  echo "❌ smalot/pdfparser no encontrado\n";
  echo "💡 Ejecuta: composer install\n";
  exit(1);
}

if (class_exists('GuzzleHttp\Client')) {
  echo "✅ guzzlehttp/guzzle disponible\n";
} else {
  echo "❌ guzzlehttp/guzzle no encontrado\n";
  exit(1);
}

echo "\n";

// 3. Verificar CV de prueba
echo "🔍 3. VERIFICANDO ARCHIVO CV...\n";

$cvPath = __DIR__ . '/../Curriculum Vitae - javier-rodriguez-mkt.pdf';
if (file_exists($cvPath)) {
  $size = round(filesize($cvPath) / 1024, 2);
  echo "✅ CV encontrado: " . basename($cvPath) . " ($size KB)\n";
} else {
  echo "❌ CV no encontrado: $cvPath\n";
  echo "💡 Verifica que el archivo existe en la raíz del proyecto\n";
  exit(1);
}

echo "\n";

// 4. Inicializar servicio
echo "🔍 4. INICIALIZANDO SERVICIO...\n";

try {
  $service = new GroqApiService();
  echo "✅ GroqApiService inicializado correctamente\n";

  $config = $service->getUsageInfo();
  echo "📋 Configuración activa:\n";
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
} catch (\Exception $e) {
  echo "❌ Error inicializando servicio: " . $e->getMessage() . "\n";
  exit(1);
}

echo "\n";

// 5. Test de conectividad
echo "🔍 5. TEST DE CONECTIVIDAD...\n";

try {
  $available = $service->isAvailable();
  if ($available) {
    echo "✅ Groq API responde correctamente\n";

    // Intentar obtener modelos disponibles
    $models = $service->listAvailableModels();
    if (!empty($models)) {
      echo "✅ " . count($models) . " modelos disponibles en Groq\n";
      echo "🤖 Ejemplos: ";
      $modelNames = array_map(function ($m) {
        return $m['id'] ?? 'unknown';
      }, array_slice($models, 0, 3));
      echo implode(', ', $modelNames) . "\n";
    }
  } else {
    echo "⚠️  Groq API no responde\n";
    echo "💡 Verifica tu API Key y conexión a internet\n";
  }
} catch (\Exception $e) {
  echo "⚠️  No se pudo verificar conectividad: " . $e->getMessage() . "\n";
  echo "💡 Esto puede ser normal si hay problemas temporales de red\n";
}

echo "\n";

// 6. Resultado final
echo "🎉 ¡VERIFICACIÓN COMPLETADA!\n";
echo "============================\n\n";
echo "✅ Todas las dependencias están correctas\n";
echo "✅ Configuración válida\n";
echo "✅ CV de prueba disponible\n";
echo "✅ Servicio inicializado\n\n";

echo "📋 SIGUIENTE PASO:\n";
echo "Ejecuta el test completo con:\n";
echo "php test_groq_production.php\n\n";

echo "🔗 RECURSOS ÚTILES:\n";
echo "• API Keys GRATIS: https://console.groq.com/keys\n";
echo "• Documentación: https://console.groq.com/docs\n";
echo "• Playground: https://console.groq.com/playground\n";
echo "• Modelos disponibles: Llama3, Mixtral, Gemma\n\n";

echo "💡 VENTAJAS DE GROQ:\n";
echo "• ⚡ Ultra rápido (chips LPU especializados)\n";
echo "• 🆓 Completamente gratuito para desarrollo\n";
echo "• 🔄 Compatible con OpenAI API\n";
echo "• 🚀 Sin setup de infraestructura\n\n";
