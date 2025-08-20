<?php

/**
 * Test de PRODUCCIÓN - OllamaServiceStandard con PDF Real
 * Analiza CV real: Curriculum Vitae - javier-rodriguez-mkt.pdf
 */

require_once __DIR__ . '/autoload.php';

use Services\OllamaServiceStandard;
use Services\Exceptions\AiUnavailableException;

echo "=== TEST PRODUCCIÓN - OLLAMA SERVICE STANDARD ===\n";
echo "Archivo: Curriculum Vitae - javier-rodriguez-mkt.pdf\n\n";

try {
  // Path al PDF real
  $pdfPath = __DIR__ . '/../Curriculum Vitae - javier-rodriguez-mkt.pdf';

  if (!file_exists($pdfPath)) {
    throw new \Exception("PDF no encontrado: {$pdfPath}");
  }

  echo "✓ PDF encontrado: " . basename($pdfPath) . "\n";
  echo "✓ Tamaño del archivo: " . number_format(filesize($pdfPath)) . " bytes\n\n";

  // Inicializar servicio
  $service = new OllamaServiceStandard();
  echo "✓ OllamaServiceStandard inicializado\n";

  // Verificar disponibilidad
  echo "\n1. Verificando disponibilidad de Ollama...\n";
  $isAvailable = $service->isAvailable();

  if (!$isAvailable) {
    throw new \Exception("Ollama no está disponible. Verificar que esté ejecutándose.");
  }

  echo "✓ Ollama está disponible y funcional\n";

  // Mostrar información del servicio
  echo "\n2. Información del servicio:\n";
  $serviceInfo = $service->getServiceInfo();
  foreach ($serviceInfo as $key => $value) {
    echo "  - {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
  }

  // PRUEBA REAL: Analizar PDF completo
  echo "\n3. *** ANÁLISIS REAL DEL PDF ***\n";
  echo "Iniciando análisis de CV con Ollama + hanwoolderink/ollama-php-client...\n";

  $startTime = microtime(true);

  try {
    $cvData = $service->analyzeCvFromPdf($pdfPath);

    $duration = round((microtime(true) - $startTime) * 1000);

    echo "✓ ANÁLISIS COMPLETADO en {$duration}ms\n\n";

    // Mostrar resultados estructurados
    echo "=== DATOS EXTRAÍDOS DEL CV ===\n";
    echo "Nombre: " . ($cvData['nombre'] ?? 'N/A') . "\n";
    echo "Email: " . ($cvData['email'] ?? 'N/A') . "\n";
    echo "Teléfono: " . ($cvData['telefono'] ?? 'N/A') . "\n";
    echo "Ubicación: " . ($cvData['ubicacion_actual'] ?? 'N/A') . "\n";
    echo "LinkedIn: " . ($cvData['linkedin'] ?? 'N/A') . "\n";

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
        if (!empty($puesto['fecha_inicio']) || !empty($puesto['fecha_fin'])) {
          echo "     Período: " . ($puesto['fecha_inicio'] ?? '?') . " - " . ($puesto['fecha_fin'] ?? 'Actual') . "\n";
        }
      }
    }

    if (!empty($cvData['educacion'])) {
      echo "\nEducación:\n";
      foreach ($cvData['educacion'] as $index => $edu) {
        echo "  " . ($index + 1) . ". " . ($edu['titulo'] ?? 'N/A') . " - " . ($edu['institucion'] ?? 'N/A') . "\n";
      }
    }

    // Verificar estructura completa
    echo "\n=== VERIFICACIÓN ESTRUCTURA JSON ===\n";
    $requiredFields = [
      'nombre',
      'email',
      'telefono',
      'ubicacion_actual',
      'resumen_profesional',
      'hard_skills',
      'soft_skills',
      'puestos_anteriores',
      'educacion',
      'data_source'
    ];

    $missingFields = [];
    foreach ($requiredFields as $field) {
      if (!isset($cvData[$field])) {
        $missingFields[] = $field;
      }
    }

    if (empty($missingFields)) {
      echo "✓ Todos los campos requeridos están presentes\n";
    } else {
      echo "✗ Campos faltantes: " . implode(', ', $missingFields) . "\n";
    }

    echo "\n✓ Total de campos en respuesta: " . count($cvData) . "\n";
    echo "✓ Fuente de datos: " . ($cvData['data_source'] ?? 'N/A') . "\n";

    // Guardar resultado para inspección
    $outputFile = __DIR__ . '/cv_analysis_result.json';
    file_put_contents($outputFile, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✓ Resultado guardado en: " . basename($outputFile) . "\n";

    echo "\n=== TEST DE PRODUCCIÓN EXITOSO ===\n";
  } catch (AiUnavailableException $e) {
    echo "✗ Error de IA: " . $e->getMessage() . "\n";
    throw $e;
  }
} catch (\Exception $e) {
  echo "\n✗ ERROR EN TEST DE PRODUCCIÓN: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
