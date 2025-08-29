<?php

declare(strict_types=1);

/**
 * AI System Demo Script
 *
 * Demostración del sistema unificado de IA con soporte multi-proveedor
 *
 * @package Backend\Scripts
 * @version 1.0.0
 * @since 2025-08-10
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Config/ai_providers_bootstrap.php';

use Services\UnifiedAIService;
use Services\ContentGenerationService;
use Services\AIServiceFactory;

echo "=== SISTEMA UNIFICADO DE IA - DEMO ===\n\n";

// 1. Mostrar proveedores disponibles
echo "1. PROVEEDORES DISPONIBLES:\n";
$providers = AIServiceFactory::getProvidersInfo();
foreach ($providers as $name => $info) {
  echo "   - {$name}: {$info['name']} (" . ($info['available'] ? 'Disponible' : 'No disponible') . ")\n";
}
echo "\n";

// 2. Probar servicio unificado básico
echo "2. PRUEBA DE SERVICIO UNIFICADO:\n";
try {
  $aiService = new UnifiedAIService();
  $response = $aiService->chatCompletion("Hola, ¿puedes presentarte en español?", [
    'temperature' => 0.7,
    'max_tokens' => 100
  ]);

  echo "   Respuesta: " . ($response ?: 'Sin respuesta') . "\n";
  echo "   Proveedor usado: " . $aiService->getCurrentProvider()->getProviderName() . "\n";
} catch (Exception $e) {
  echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Probar generación de contenido
echo "3. PRUEBA DE GENERACIÓN DE CONTENIDO:\n";
try {
  $contentService = new ContentGenerationService();

  $jobData = [
    'title' => 'Desarrollador Full Stack Senior',
    'company' => 'TechCorp',
    'location' => 'Madrid',
    'type' => 'Tiempo completo',
    'salary' => '50,000 - 70,000€',
    'key_skills' => ['PHP', 'JavaScript', 'React', 'Node.js'],
    'brief_description' => 'Buscamos desarrollador experimentado para proyectos innovadores'
  ];

  $jobDescription = $contentService->generateJobDescription($jobData);

  echo "   Título generado: " . ($jobDescription['title'] ?? 'N/A') . "\n";
  echo "   Generado con: " . ($jobDescription['generated_with'] ?? 'N/A') . "\n";
  echo "   Resumen: " . substr($jobDescription['summary'] ?? '', 0, 100) . "...\n";
} catch (Exception $e) {
  echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Probar cambio de proveedor
echo "4. PRUEBA DE CAMBIO DE PROVEEDOR:\n";
try {
  $aiService = new UnifiedAIService('groq');
  echo "   Proveedor actual: " . $aiService->getCurrentProvider()->getProviderName() . "\n";

  $response = $aiService->chatCompletion("¿Cuál es la capital de España?", [
    'temperature' => 0.1,
    'max_tokens' => 50
  ]);

  echo "   Respuesta: " . ($response ?: 'Sin respuesta') . "\n";
} catch (Exception $e) {
  echo "   Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Mostrar estadísticas
echo "5. ESTADÍSTICAS DE USO:\n";
try {
  $aiService = new UnifiedAIService();
  $stats = $aiService->getProviderStats();

  foreach ($stats as $provider => $stat) {
    echo "   {$provider}:\n";
    echo "     - Solicitudes: {$stat['requests']}\n";
    echo "     - Éxitos: {$stat['successes']}\n";
    echo "     - Fallos: {$stat['failures']}\n";
    echo "     - Tiempo promedio: " . round($stat['average_response_time'], 2) . "ms\n";
  }
} catch (Exception $e) {
  echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== DEMO COMPLETADA ===\n";
