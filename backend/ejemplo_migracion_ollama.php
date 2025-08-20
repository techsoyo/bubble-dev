<?php

/**
 * Ejemplo de uso de OllamaServiceStandard
 * 
 * Demuestra cómo migrar de cURL a la librería estándar hanwoolderink/ollama-php-client
 */

require_once __DIR__ . '/autoload.php';

use Services\OllamaServiceStandard;
use Services\Exceptions\AiUnavailableException;

try {
  // Crear instancia del servicio (inyección automática de dependencias)
  $ollamaService = new OllamaServiceStandard();

  // Verificar disponibilidad
  if (!$ollamaService->isAvailable()) {
    echo "❌ Ollama no está disponible\n";
    exit(1);
  }

  echo "✅ Ollama está disponible\n";
  echo "📊 Información del servicio:\n";
  print_r($ollamaService->getServiceInfo());

  // Ejemplo 1: Análisis de PDF (método principal)
  $pdfPath = __DIR__ . '/uploads/ejemplo_cv.pdf';

  if (file_exists($pdfPath)) {
    echo "\n🔍 Analizando PDF: {$pdfPath}\n";

    $cvData = $ollamaService->analyzeCvFromPdf($pdfPath);

    echo "✅ Análisis completado\n";
    echo "👤 Nombre encontrado: " . ($cvData['nombre'] ?? 'No detectado') . "\n";
    echo "📧 Email encontrado: " . ($cvData['email'] ?? 'No detectado') . "\n";
    echo "🏢 Experiencias encontradas: " . count($cvData['puestos_anteriores'] ?? []) . "\n";

    // Guardar resultado completo
    file_put_contents(
      __DIR__ . '/storage/cv_analysis_result.json',
      json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    echo "💾 Resultado guardado en storage/cv_analysis_result.json\n";
  } else {
    echo "⚠️  PDF de ejemplo no encontrado en: {$pdfPath}\n";
  }

  // Ejemplo 2: Generación de descripción de trabajo
  echo "\n📝 Generando descripción de trabajo...\n";

  $jobParams = [
    'titulo' => 'Desarrollador Full Stack',
    'experiencia' => '3+ años',
    'tecnologias' => 'React, Node.js, PHP, MySQL',
    'modalidad' => 'Remoto',
    'ubicacion' => 'España'
  ];

  $jobDescription = $ollamaService->generateJobDescription($jobParams);
  echo "✅ Descripción generada:\n";
  echo substr($jobDescription, 0, 200) . "...\n";
} catch (AiUnavailableException $e) {
  echo "❌ Error de IA: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
  echo "❌ Error general: " . $e->getMessage() . "\n";
}

echo "\n🎯 Migración a librería estándar completada exitosamente\n";
echo "📚 Usando: hanwoolderink/ollama-php-client\n";
echo "🔄 Endpoint: /api/generate\n";
echo "🖼️  Soporte para imágenes: ✅ (base64)\n";
echo "📄 Formato JSON: ✅\n";
