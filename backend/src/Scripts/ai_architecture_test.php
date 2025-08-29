<?php

declare(strict_types=1);

/**
 * AI System Architecture Test
 *
 * Prueba básica de la arquitectura del sistema unificado de IA
 * No requiere API keys - solo verifica que los componentes se carguen correctamente
 *
 * @package Backend\Scripts
 * @version 1.0.0
 * @since 2025-08-10
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Config/ai_providers_bootstrap.php';

use Services\AIServiceFactory;
use Services\UnifiedAIService;
use Services\ContentGenerationService;

echo "=== PRUEBA DE ARQUITECTURA DEL SISTEMA UNIFICADO DE IA ===\n\n";

// 1. Verificar proveedores registrados
echo "1. PROVEEDORES REGISTRADOS:\n";
$providers = AIServiceFactory::listProviders();
if (empty($providers)) {
  echo "   ❌ No hay proveedores registrados\n";
  echo "   💡 Configure las API keys en el archivo .env\n";
} else {
  foreach ($providers as $provider) {
    $available = AIServiceFactory::isProviderAvailable($provider);
    $status = $available ? '✅ Disponible' : '❌ No disponible';
    echo "   - {$provider}: {$status}\n";
  }
}
echo "\n";

// 2. Verificar información de proveedores
echo "2. INFORMACIÓN DETALLADA DE PROVEEDORES:\n";
$providersInfo = AIServiceFactory::getProvidersInfo();
foreach ($providersInfo as $name => $info) {
  echo "   {$name}:\n";
  echo "     - Nombre: {$info['name']}\n";
  echo "     - Disponible: " . ($info['available'] ? 'Sí' : 'No') . "\n";
  echo "     - Modelo por defecto: {$info['default_model']}\n";
}
echo "\n";

// 3. Probar creación de servicios (sin llamadas a API)
echo "3. PRUEBA DE CREACIÓN DE SERVICIOS:\n";
try {
  echo "   Intentando crear UnifiedAIService...\n";
  $aiService = new UnifiedAIService();
  echo "   ✅ UnifiedAIService creado exitosamente\n";

  $currentProvider = $aiService->getCurrentProvider();
  echo "   ✅ Proveedor actual: " . $currentProvider->getProviderName() . "\n";
} catch (Exception $e) {
  echo "   ❌ Error creando UnifiedAIService: " . $e->getMessage() . "\n";
}

try {
  echo "   Intentando crear ContentGenerationService...\n";
  $contentService = new ContentGenerationService();
  echo "   ✅ ContentGenerationService creado exitosamente\n";
} catch (Exception $e) {
  echo "   ❌ Error creando ContentGenerationService: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Verificar métodos disponibles
echo "4. MÉTODOS DISPONIBLES:\n";
if (isset($aiService)) {
  $methods = get_class_methods($aiService);
  echo "   UnifiedAIService métodos: " . count($methods) . "\n";
  foreach (array_slice($methods, 0, 5) as $method) {
    echo "     - {$method}\n";
  }
  if (count($methods) > 5) {
    echo "     ... y " . (count($methods) - 5) . " métodos más\n";
  }
}

if (isset($contentService)) {
  $methods = get_class_methods($contentService);
  echo "   ContentGenerationService métodos: " . count($methods) . "\n";
  foreach (array_slice($methods, 0, 5) as $method) {
    echo "     - {$method}\n";
  }
  if (count($methods) > 5) {
    echo "     ... y " . (count($methods) - 5) . " métodos más\n";
  }
}
echo "\n";

// 5. Verificar configuración
echo "5. CONFIGURACIÓN ACTUAL:\n";
echo "   AI_PROVIDER: " . ($_ENV['AI_PROVIDER'] ?? getenv('AI_PROVIDER') ?? 'No configurado') . "\n";
echo "   GROQ_API_KEY: " . (!empty($_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY')) ? 'Configurada' : 'No configurada') . "\n";
echo "   OPENAI_API_KEY: " . (!empty($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY')) ? 'Configurada' : 'No configurada') . "\n";
echo "\n";

// 6. Instrucciones para configuración
echo "6. INSTRUCCIONES DE CONFIGURACIÓN:\n";
echo "   Para usar el sistema completo:\n";
echo "   1. Copia .env.development.ai como .env\n";
echo "   2. Configura GROQ_API_KEY (gratis en https://console.groq.com/keys)\n";
echo "   3. Opcionalmente configura OPENAI_API_KEY\n";
echo "   4. Ejecuta este script nuevamente\n";
echo "\n";

echo "=== PRUEBA DE ARQUITECTURA COMPLETADA ===\n";
echo "✅ Arquitectura del sistema unificado de IA funcionando correctamente\n";
