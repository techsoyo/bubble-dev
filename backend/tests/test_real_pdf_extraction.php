<?php

/**
 * Test REAL extrayendo texto del PDF usando smalot/pdfparser
 */

require_once __DIR__ . '/autoload.php';

use Services\OllamaServiceStandard;
use Services\Exceptions\AiUnavailableException;
use Smalot\PdfParser\Parser;

echo "=== TEST REAL - EXTRACCIÓN Y ANÁLISIS DE PDF ===\n";
echo "Archivo: Curriculum Vitae - javier-rodriguez-mkt.pdf\n\n";

try {
  // Path al PDF real
  $pdfPath = __DIR__ . '/../Curriculum Vitae - javier-rodriguez-mkt.pdf';

  if (!file_exists($pdfPath)) {
    throw new \Exception("PDF no encontrado: {$pdfPath}");
  }

  echo "✓ PDF encontrado: " . basename($pdfPath) . "\n";
  echo "✓ Tamaño: " . number_format(filesize($pdfPath)) . " bytes\n\n";

  // PASO 1: Extraer texto REAL del PDF
  echo "1. Extrayendo texto del PDF con smalot/pdfparser...\n";
  $parser = new Parser();
  $pdf = $parser->parseFile($pdfPath);
  $extractedText = $pdf->getText();

  if (empty($extractedText)) {
    throw new \Exception("No se pudo extraer texto del PDF");
  }

  // Limpiar texto
  $cleanText = preg_replace('/\s+/', ' ', trim($extractedText));

  echo "✓ Texto extraído: " . number_format(strlen($extractedText)) . " caracteres raw\n";
  echo "✓ Texto limpio: " . number_format(strlen($cleanText)) . " caracteres\n";
  echo "✓ Primeros 300 caracteres:\n";
  echo "---\n" . substr($cleanText, 0, 300) . "...\n---\n\n";

  // PASO 2: Analizar con Ollama
  $service = new OllamaServiceStandard();
  echo "✓ OllamaServiceStandard inicializado\n";

  echo "\n2. Verificando disponibilidad de Ollama...\n";
  $isAvailable = $service->isAvailable();

  if (!$isAvailable) {
    throw new \Exception("Ollama no está disponible");
  }

  echo "✓ Ollama disponible\n";

  // PASO 3: Análisis del texto extraído
  echo "\n3. *** ANÁLISIS DEL TEXTO REAL ***\n";
  echo "Procesando con Ollama...\n";

  $startTime = microtime(true);

  try {
    $cvData = $service->analyzeCvFromText($cleanText);

    $duration = round((microtime(true) - $startTime) * 1000);

    echo "✓ ANÁLISIS COMPLETADO en {$duration}ms\n\n";

    // Mostrar resultados detallados
    echo "=== DATOS EXTRAÍDOS DEL CV REAL ===\n";
    echo "Nombre: " . ($cvData['nombre'] ?? 'N/A') . "\n";
    echo "Email: " . ($cvData['email'] ?? 'N/A') . "\n";
    echo "Teléfono: " . ($cvData['telefono'] ?? 'N/A') . "\n";
    echo "Ubicación: " . ($cvData['ubicacion_actual'] ?? 'N/A') . "\n";

    if (!empty($cvData['resumen_profesional'])) {
      echo "\n--- RESUMEN PROFESIONAL ---\n";
      echo wordwrap($cvData['resumen_profesional'], 70) . "\n";
    }

    if (!empty($cvData['hard_skills'])) {
      echo "\n--- HABILIDADES TÉCNICAS ---\n";
      echo implode(', ', $cvData['hard_skills']) . "\n";
    }

    if (!empty($cvData['soft_skills'])) {
      echo "\n--- HABILIDADES BLANDAS ---\n";
      echo implode(', ', $cvData['soft_skills']) . "\n";
    }

    if (!empty($cvData['puestos_anteriores'])) {
      echo "\n--- EXPERIENCIA LABORAL ---\n";
      foreach ($cvData['puestos_anteriores'] as $i => $puesto) {
        echo ($i + 1) . ". " . ($puesto['puesto'] ?? 'N/A') . " en " . ($puesto['empresa'] ?? 'N/A') . "\n";
        if (!empty($puesto['descripcion'])) {
          echo "   " . substr($puesto['descripcion'], 0, 100) . "...\n";
        }
      }
    }

    if (!empty($cvData['educacion'])) {
      echo "\n--- EDUCACIÓN ---\n";
      foreach ($cvData['educacion'] as $i => $edu) {
        echo ($i + 1) . ". " . ($edu['titulo'] ?? 'N/A') . " - " . ($edu['institucion'] ?? 'N/A') . "\n";
      }
    }

    // Estadísticas
    echo "\n=== ESTADÍSTICAS ===\n";
    echo "✓ Total campos extraídos: " . count($cvData) . "\n";
    echo "✓ Campos con datos: " . count(array_filter($cvData, function ($v) {
      return !empty($v);
    })) . "\n";
    echo "✓ Fuente: " . ($cvData['data_source'] ?? 'N/A') . "\n";

    // Guardar resultado completo
    $outputFile = __DIR__ . '/cv_real_analysis.json';
    file_put_contents($outputFile, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✓ Resultado completo guardado en: " . basename($outputFile) . "\n";

    echo "\n=== TEST REAL COMPLETAMENTE EXITOSO ===\n";
    echo "✅ PDF → Texto → IA → Datos estructurados\n";
  } catch (AiUnavailableException $e) {
    echo "✗ Error de IA: " . $e->getMessage() . "\n";
    throw $e;
  }
} catch (\Exception $e) {
  echo "\n✗ ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
