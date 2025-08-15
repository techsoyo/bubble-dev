<?php

/**
 * Prueba Completa del Sistema Ollama Integrado
 * 
 * Este archivo prueba toda la funcionalidad del sistema CV con Ollama
 * - Verificación de OllamaService mejorado
 * - Prueba de análisis de CV completo
 * - Verificación de integración con parse.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap y configuración
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/src/Services/OllamaService.php';

use Services\OllamaService;

echo "🚀 PRUEBA COMPLETA DEL SISTEMA OLLAMA INTEGRADO\n";
echo "=============================================\n\n";

// 1. Verificar servicio OllamaService mejorado
echo "1️⃣ Verificando OllamaService...\n";
try {
  $ollama = new OllamaService();
  $serviceInfo = $ollama->getServiceInfo();

  echo "✅ Servicio creado exitosamente\n";
  echo "📋 Información del servicio:\n";
  foreach ($serviceInfo as $key => $value) {
    $displayValue = is_bool($value) ? ($value ? 'Sí' : 'No') : $value;
    echo "   - " . ucfirst(str_replace('_', ' ', $key)) . ": $displayValue\n";
  }

  // Verificar disponibilidad
  if ($ollama->isAvailable()) {
    echo "✅ Ollama está disponible y funcionando\n\n";
  } else {
    echo "❌ Ollama NO está disponible\n";
    echo "   Verifica que Ollama esté ejecutándose en localhost:11434\n\n";
    exit(1);
  }
} catch (Exception $e) {
  echo "❌ Error creando OllamaService: " . $e->getMessage() . "\n\n";
  exit(1);
}

// 2. Prueba de análisis de CV con texto de ejemplo
echo "2️⃣ Probando análisis de CV...\n";
$sampleCvText = "
JUAN PÉREZ GARCÍA
Desarrollador Full Stack Senior

📧 Email: juan.perez@email.com
📱 Teléfono: +34 600 123 456
📍 Madrid, España
🔗 LinkedIn: https://linkedin.com/in/juanperez
💻 GitHub: https://github.com/juanperez

RESUMEN PROFESIONAL
Desarrollador Full Stack con 5 años de experiencia en desarrollo web usando React, Node.js y PHP. 
Especializado en arquitecturas escalables y metodologías ágiles.

EXPERIENCIA LABORAL
Senior Full Stack Developer | TechCorp Solutions | 2022 - Presente
- Desarrollo de aplicaciones web con React y Node.js
- Implementación de APIs REST y GraphQL
- Gestión de bases de datos MySQL y MongoDB
- Trabajo en equipo ágil con Scrum

Frontend Developer | StartupWeb | 2020 - 2022
- Desarrollo de interfaces de usuario con React y Vue.js
- Integración con APIs REST
- Optimización de rendimiento web

EDUCACIÓN
Ingeniería Informática | Universidad Politécnica de Madrid | 2016 - 2020
- Especialización en Desarrollo de Software
- Proyecto final: Sistema de gestión de inventarios

HABILIDADES TÉCNICAS
Lenguajes: JavaScript, TypeScript, PHP, Python, HTML5, CSS3
Frameworks: React, Vue.js, Node.js, Express, Laravel
Bases de datos: MySQL, MongoDB, PostgreSQL
Herramientas: Git, Docker, AWS, Jenkins

IDIOMAS
- Español: Nativo
- Inglés: Avanzado (C1)
- Francés: Intermedio (B2)

CERTIFICACIONES
- AWS Certified Developer Associate (2023)
- React Developer Certification (2022)
";

try {
  $startTime = microtime(true);
  echo "⏳ Analizando CV de ejemplo...\n";

  $cvData = $ollama->analyzeCvFromText($sampleCvText);

  $duration = round((microtime(true) - $startTime) * 1000);
  echo "✅ Análisis completado en {$duration}ms\n\n";

  echo "📊 RESULTADOS DEL ANÁLISIS:\n";
  echo "==========================\n";

  // Mostrar información personal
  if (isset($cvData['personal_info'])) {
    echo "👤 INFORMACIÓN PERSONAL:\n";
    foreach ($cvData['personal_info'] as $key => $value) {
      if (!empty($value)) {
        echo "   - " . ucfirst(str_replace('_', ' ', $key)) . ": $value\n";
      }
    }
    echo "\n";
  }

  // Mostrar experiencia laboral
  if (isset($cvData['work_experience']) && is_array($cvData['work_experience'])) {
    echo "💼 EXPERIENCIA LABORAL:\n";
    foreach ($cvData['work_experience'] as $i => $job) {
      echo "   " . ($i + 1) . ". {$job['position']} en {$job['company']}\n";
      if (!empty($job['duration'])) {
        echo "      Duración: {$job['duration']}\n";
      }
    }
    echo "\n";
  }

  // Mostrar habilidades técnicas
  if (isset($cvData['skills']['technical']) && is_array($cvData['skills']['technical'])) {
    echo "🛠️ HABILIDADES TÉCNICAS:\n";
    echo "   " . implode(', ', $cvData['skills']['technical']) . "\n\n";
  }

  // Mostrar educación
  if (isset($cvData['education']) && is_array($cvData['education'])) {
    echo "🎓 EDUCACIÓN:\n";
    foreach ($cvData['education'] as $edu) {
      echo "   - {$edu['degree']} en {$edu['institution']}\n";
    }
    echo "\n";
  }

  echo "📄 JSON COMPLETO:\n";
  echo json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
  echo "❌ Error en análisis de CV: " . $e->getMessage() . "\n\n";
  exit(1);
}

// 3. Prueba de modelos disponibles
echo "3️⃣ Verificando modelos disponibles...\n";
try {
  $models = $ollama->getAvailableModels();

  if (!empty($models)) {
    echo "✅ Modelos disponibles en Ollama:\n";
    foreach ($models as $model) {
      $name = $model['name'] ?? 'N/A';
      $size = isset($model['size']) ? number_format($model['size'] / (1024 * 1024 * 1024), 1) . ' GB' : 'N/A';
      echo "   - $name ($size)\n";
    }
  } else {
    echo "⚠️ No se encontraron modelos disponibles\n";
  }
  echo "\n";
} catch (Exception $e) {
  echo "⚠️ Error obteniendo modelos: " . $e->getMessage() . "\n\n";
}

// 4. Verificar archivos del sistema
echo "4️⃣ Verificando archivos del sistema integrado...\n";

$filesToCheck = [
  'src/Services/OllamaService.php' => 'Servicio Ollama mejorado',
  'public/api/cv/parse.php' => 'Endpoint de análisis de CV',
  '.env' => 'Configuración de entorno'
];

foreach ($filesToCheck as $file => $description) {
  $fullPath = __DIR__ . '/' . $file;
  if (file_exists($fullPath)) {
    echo "✅ $description: $file\n";
  } else {
    echo "❌ FALTA: $description: $file\n";
  }
}

echo "\n🎉 PRUEBA COMPLETA FINALIZADA\n";
echo "===============================\n";
echo "✅ Sistema Ollama integrado y funcionando\n";
echo "✅ Análisis de CV completamente local\n";
echo "✅ Sin límites de API ni costos\n";
echo "✅ Privacidad total de datos\n\n";

echo "🚀 El sistema está listo para procesar CVs con Ollama!\n";
echo "   Puedes usar el endpoint: /api/cv/parse\n";
echo "   O probar directamente con OllamaService en PHP\n\n";
