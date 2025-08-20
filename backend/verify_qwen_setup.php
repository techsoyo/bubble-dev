<?php

/**
 * Script de verificación rápida para QwenApiService
 * 
 * Verifica que todas las dependencias y configuraciones estén correctas
 * antes de ejecutar el test completo con CV real.
 */

require_once __DIR__ . '/autoload.php';

use Services\QwenApiService;

echo "\n=== VERIFICACIÓN QWEN API SERVICE ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Verificar variables de entorno
echo "🔍 1. VERIFICANDO CONFIGURACIÓN...\n";

$apiKey = $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY');
if (empty($apiKey)) {
  echo "❌ QWEN_API_KEY no configurada\n";
  echo "💡 Ejecuta: export QWEN_API_KEY=\"sk-xxxxx\"\n";
  echo "💡 O crea archivo .env con la configuración\n\n";
  exit(1);
} else {
  echo "✅ QWEN_API_KEY: " . substr($apiKey, 0, 8) . "...\n";
}

$baseUrl = $_ENV['QWEN_BASE_URL'] ?? getenv('QWEN_BASE_URL') ?: 'https://dashscope.aliyuncs.com/compatible-mode/v1';
$model = $_ENV['QWEN_MODEL'] ?? getenv('QWEN_MODEL') ?: 'qwen-plus';

echo "✅ Base URL: $baseUrl\n";
echo "✅ Modelo: $model\n\n";

// 2. Verificar dependencias
echo "🔍 2. VERIFICANDO DEPENDENCIAS...\n";

if (class_exists('Services\QwenApiService')) {
  echo "✅ QwenApiService cargado correctamente\n";
} else {
  echo "❌ Error cargando QwenApiService\n";
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
  $service = new QwenApiService();
  echo "✅ QwenApiService inicializado correctamente\n";

  $config = $service->getUsageInfo();
  echo "📋 Configuración activa:\n";
  foreach ($config as $key => $value) {
    echo "   • $key: $value\n";
  }
} catch (\Exception $e) {
  echo "❌ Error inicializando servicio: " . $e->getMessage() . "\n";
  exit(1);
}

echo "\n";

// 5. Test de conectividad (opcional)
echo "🔍 5. TEST DE CONECTIVIDAD (OPCIONAL)...\n";

try {
  $available = $service->isAvailable();
  if ($available) {
    echo "✅ Qwen API responde correctamente\n";
  } else {
    echo "⚠️  Qwen API no responde (podría ser normal)\n";
    echo "💡 Continuarás con el test completo\n";
  }
} catch (\Exception $e) {
  echo "⚠️  No se pudo verificar conectividad: " . $e->getMessage() . "\n";
  echo "💡 Esto puede ser normal, continúa con el test completo\n";
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
echo "php test_qwen_production.php\n\n";

echo "🔗 RECURSOS ÚTILES:\n";
echo "• API Key: https://help.aliyun.com/zh/model-studio/developer-reference/get-api-key\n";
echo "• Documentación: https://help.aliyun.com/zh/model-studio/\n";
echo "• Precios: https://www.alibabacloud.com/product/bailian\n\n";
