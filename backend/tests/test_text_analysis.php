<?php

/**
 * Test de PRODUCCIÓN con extracción de texto del PDF
 * Usa el enfoque legacy: PDF -> texto -> análisis de IA
 */

require_once __DIR__ . '/autoload.php';

use Services\OllamaServiceStandard;
use Services\PdfTextService;
use Services\Exceptions\AiUnavailableException;

echo "=== TEST PRODUCCIÓN - ANÁLISIS DE TEXTO EXTRAÍDO ===\n";
echo "Archivo: Curriculum Vitae - javier-rodriguez-mkt.pdf\n\n";

try {
  // Path al PDF real
  $pdfPath = __DIR__ . '/../Curriculum Vitae - javier-rodriguez-mkt.pdf';

  if (!file_exists($pdfPath)) {
    throw new \Exception("PDF no encontrado: {$pdfPath}");
  }

  echo "✓ PDF encontrado: " . basename($pdfPath) . "\n";
  echo "✓ Tamaño del archivo: " . number_format(filesize($pdfPath)) . " bytes\n\n";

  // PASO 1: Extraer texto del PDF primero
  echo "1. Extrayendo texto del PDF...\n";
  $pdfTextService = new PdfTextService();
  $extractedText = $pdfTextService->extractTextFromPdf($pdfPath);

  if (empty($extractedText)) {
    throw new \Exception("No se pudo extraer texto del PDF");
  }

  echo "✓ Texto extraído: " . number_format(strlen($extractedText)) . " caracteres\n";
  echo "✓ Primeras 200 caracteres: " . substr($extractedText, 0, 200) . "...\n\n";

  // PASO 2: Inicializar servicio
  $service = new OllamaServiceStandard();
  echo "✓ OllamaServiceStandard inicializado\n";

  // PASO 3: Verificar disponibilidad
  echo "\n2. Verificando disponibilidad de Ollama...\n";
  $isAvailable = $service->isAvailable();

  if (!$isAvailable) {
    throw new \Exception("Ollama no está disponible. Verificar que esté ejecutándose.");
  }

  echo "✓ Ollama está disponible y funcional\n";

  // PASO 4: Análizar TEXTO extraído (no PDF directo)
  echo "\n3. *** ANÁLISIS DE TEXTO EXTRAÍDO ***\n";
  echo "Analizando texto con Ollama + hanwoolderink/ollama-php-client...\n";

  $startTime = microtime(true);

  try {
    // Usar el método de análisis de texto en lugar de PDF directo
    $cvData = $service->analyzeCvFromText($extractedText);

    $duration = round((microtime(true) - $startTime) * 1000);

    echo "✓ ANÁLISIS COMPLETADO en {$duration}ms\n\n";

    // Mostrar resultados estructurados
    echo "=== DATOS EXTRAÍDOS DEL CV ===\n";
    echo "Nombre: " . ($cvData['nombre'] ?? 'N/A') . "\n";
    echo "Email: " . ($cvData['email'] ?? 'N/A') . "\n";
    echo "Teléfono: " . ($cvData['telefono'] ?? 'N/A') . "\n";
    echo "Ubicación: " . ($cvData['ubicacion_actual'] ?? 'N/A') . "\n";

    if (!empty($cvData['resumen_profesional'])) {
      echo "\nResumen Profesional:\n" . wordwrap($cvData['resumen_profesional'], 70) . "\n";
    }

    if (!empty($cvData['hard_skills'])) {
      echo "\nHabilidades Técnicas: " . implode(', ', $cvData['hard_skills']) . "\n";
    }

    if (!empty($cvData['soft_skills'])) {
      echo "Habilidades Blandas: " . implode(', ', $cvData['soft_skills']) . "\n";
    }

    if (!empty($cvData['puestos_anteriores'])) {
      echo "\nExperiencia Laboral:\n";
      foreach ($cvData['puestos_anteriores'] as $index => $puesto) {
        echo "  " . ($index + 1) . ". " . ($puesto['puesto'] ?? 'N/A') . " en " . ($puesto['empresa'] ?? 'N/A') . "\n";
      }
    }

    if (!empty($cvData['educacion'])) {
      echo "\nEducación:\n";
      foreach ($cvData['educacion'] as $index => $edu) {
        echo "  " . ($index + 1) . ". " . ($edu['titulo'] ?? 'N/A') . " - " . ($edu['institucion'] ?? 'N/A') . "\n";
      }
    }

    // Guardar resultado para inspección
    $outputFile = __DIR__ . '/cv_analysis_text_result.json';
    file_put_contents($outputFile, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\n✓ Resultado guardado en: " . basename($outputFile) . "\n";

    echo "\n=== TEST DE TEXTO EXITOSO ===\n";
  } catch (AiUnavailableException $e) {
    echo "✗ Error de IA: " . $e->getMessage() . "\n";
    throw $e;
  }
} catch (\Exception $e) {
  echo "\n✗ ERROR EN TEST: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
