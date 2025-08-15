<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

/**
 * Test directo del procesamiento de CV sin cURL
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/src/Services/OpenAIService.php';

use Services\OpenAIService;

echo "=== PRUEBA DIRECTA DEL PROCESAMIENTO DE CV ===\n\n";

try {
  // Crear un archivo de prueba
  $uploadsDir = __DIR__ . '/uploads';
  $cvsDir = $uploadsDir . '/cvs';
  $textsDir = $uploadsDir . '/textos';
  $jsonDir = $uploadsDir . '/json';

  // Crear directorios si no existen
  foreach ([$uploadsDir, $cvsDir, $textsDir, $jsonDir] as $dir) {
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
  }

  // Contenido de CV de prueba
  $cvContent = "CURRICULUM VITAE

Nombre: María García Pérez
Email: maria.garcia@email.com
Teléfono: +34 612 345 678
LinkedIn: linkedin.com/in/mariagarcia
Ubicación: Madrid, España

EXPERIENCIA PROFESIONAL

Desarrolladora Full Stack Senior
TechCorp Solutions | Enero 2021 - Presente
- Desarrollo de aplicaciones web con React, Node.js y TypeScript
- Liderazgo de equipo de 4 desarrolladores
- Implementación de arquitecturas escalables en AWS
- Trabajo con bases de datos PostgreSQL y MongoDB

Desarrolladora Frontend
Digital Agency Madrid | Marzo 2019 - Diciembre 2020
- Desarrollo de interfaces responsivas con Vue.js y Angular
- Optimización de rendimiento web (Core Web Vitals)
- Colaboración con equipos de UX/UI
- Integración de APIs REST y GraphQL

EDUCACIÓN

Ingeniería Informática
Universidad Politécnica de Madrid | 2015 - 2019
Proyecto Final: Sistema de recomendación con Machine Learning

HABILIDADES TÉCNICAS

Frontend: JavaScript, TypeScript, React, Vue.js, Angular, HTML5, CSS3, SASS
Backend: Node.js, Express, PHP, Python, RESTful APIs, GraphQL
Bases de Datos: PostgreSQL, MySQL, MongoDB, Redis
DevOps: Docker, AWS, CI/CD, Git, Jenkins
Testing: Jest, Cypress, PHPUnit

IDIOMAS

Español: Nativo
Inglés: Avanzado (C1) - Cambridge Certificate
Francés: Intermedio (B2)

PROYECTOS DESTACADOS

E-commerce Platform (2022)
- Plataforma completa con React + Node.js
- Microservicios con Docker
- Procesamiento de pagos con Stripe

Task Management App (2021)
- Progressive Web App con Vue.js
- Real-time con WebSockets
- Base de datos MongoDB";

  $testFile = $cvsDir . '/test_cv_' . date('Y-m-d_H-i-s') . '.txt';
  file_put_contents($testFile, $cvContent);
  echo "✅ Archivo de prueba creado: $testFile\n\n";

  // Cargar $_FILES de prueba
  $_FILES = [
    'cv_file' => [
      'name' => 'test_cv.txt',
      'type' => 'text/plain',
      'size' => strlen($cvContent),
      'tmp_name' => $testFile,
      'error' => UPLOAD_ERR_OK
    ]
  ];

  $_POST = [
    'user_email' => 'maria.garcia@email.com',
    'use_openai' => 'true'
  ];

  echo "1. Inicializando OpenAI Service...\n";
  $openAIService = new OpenAIService();

  echo "2. Extrayendo texto del archivo...\n";
  $extractedText = $openAIService->extractTextFromPDF($testFile);

  if ($extractedText) {
    echo "✅ Texto extraído exitosamente (longitud: " . strlen($extractedText) . " caracteres)\n";
    echo "Primeros 200 caracteres:\n" . substr($extractedText, 0, 200) . "...\n\n";
  } else {
    echo "❌ Error extrayendo texto\n";
    exit(1);
  }

  echo "3. Procesando con OpenAI...\n";
  $cvData = $openAIService->analyzeCVWithOpenAI($extractedText);

  if ($cvData) {
    echo "✅ CV procesado exitosamente\n";
    echo "Campos extraídos:\n";

    // Mostrar datos estructurados
    foreach ($cvData as $key => $value) {
      if (is_array($value)) {
        echo "- $key: " . (count($value) > 0 ? implode(', ', array_slice($value, 0, 3)) . (count($value) > 3 ? '...' : '') : 'vacío') . "\n";
      } else {
        echo "- $key: " . (strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value) . "\n";
      }
    }

    // Guardar JSON procesado
    $jsonFile = $jsonDir . '/test_cv_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($jsonFile, json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\n✅ JSON guardado en: $jsonFile\n";

    echo "\n🎯 RESULTADO: El sistema de IA ";
    if (isset($cvData['processed_with']) && strpos($cvData['processed_with'], 'OpenAI') !== false) {
      echo "SÍ está procesando con OpenAI correctamente\n";
    } else {
      echo "está usando el parser de respaldo (fallback)\n";
    }
  } else {
    echo "❌ Error procesando CV con IA\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Traza: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
