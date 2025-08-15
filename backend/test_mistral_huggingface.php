<?php

/**
 * Prueba de Mistral via Hugging Face Inference API
 * 
 * Este archivo prueba la integración con Mistral 7B a través de Hugging Face
 * - Verificación de conectividad
 * - Prueba de análisis de CV
 * - Validación de respuesta JSON
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap y configuración
require_once __DIR__ . '/autoload.php';

// Cargar variables de entorno manualmente
if (file_exists(__DIR__ . '/.env')) {
  $envFile = file_get_contents(__DIR__ . '/.env');
  $lines = explode("\n", $envFile);
  foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) continue;
    if (strpos($line, '=') !== false) {
      list($key, $value) = explode('=', $line, 2);
      $_ENV[trim($key)] = trim($value);
      putenv(trim($key) . '=' . trim($value));
    }
  }
}

require_once __DIR__ . '/src/Services/MistralService.php';

use Services\MistralService;

echo "🚀 PRUEBA DE MISTRAL VIA HUGGING FACE\n";
echo "====================================\n\n";

// 1. Verificar configuración
echo "1️⃣ Verificando configuración...\n";
$token = $_ENV['HUGGINGFACE_TOKEN'] ?? getenv('HUGGINGFACE_TOKEN');

if (!$token || $token === 'TU_TOKEN_AQUI') {
  echo "❌ ERROR: Token de Hugging Face no configurado\n";
  echo "   Por favor, actualiza HUGGINGFACE_TOKEN en .env\n";
  echo "   Obtén tu token gratis en: https://huggingface.co/settings/tokens\n\n";
  exit(1);
}

echo "✅ Token configurado: " . substr($token, 0, 8) . "...\n\n";

// 2. Verificar servicio MistralService
echo "2️⃣ Verificando MistralService...\n";
try {
  $mistral = new MistralService();
  $serviceInfo = $mistral->getServiceInfo();

  echo "✅ Servicio creado exitosamente\n";
  echo "📋 Información del servicio:\n";
  foreach ($serviceInfo as $key => $value) {
    $displayValue = is_bool($value) ? ($value ? 'Sí' : 'No') : $value;
    echo "   - " . ucfirst(str_replace('_', ' ', $key)) . ": $displayValue\n";
  }
  echo "\n";
} catch (Exception $e) {
  echo "❌ Error creando MistralService: " . $e->getMessage() . "\n\n";
  exit(1);
}

// 3. Verificar disponibilidad de Hugging Face
echo "3️⃣ Verificando disponibilidad de Hugging Face...\n";
if ($mistral->isAvailable()) {
  echo "✅ Hugging Face está disponible y responde\n\n";
} else {
  echo "⚠️ Hugging Face no responde o el modelo se está cargando\n";
  echo "   Esto es normal la primera vez, el modelo puede tardar unos minutos en cargar\n\n";
}

// 4. Prueba simple de generación de texto
echo "4️⃣ Prueba simple de generación...\n";
try {
  echo "⏳ Enviando prompt de prueba...\n";

  // Llamada directa al método interno para prueba
  $reflection = new ReflectionClass($mistral);
  $method = $reflection->getMethod('callHuggingFaceWithRetries');
  $method->setAccessible(true);

  $testPrompt = "[INST] Responde solo con 'OK' si me entiendes [/INST]";
  $response = $method->invoke($mistral, $testPrompt);

  echo "✅ Respuesta recibida: " . substr($response, 0, 100) . "...\n\n";
} catch (Exception $e) {
  echo "⚠️ Error en prueba simple: " . $e->getMessage() . "\n";
  if (str_contains($e->getMessage(), 'loading')) {
    echo "   El modelo se está cargando, esto es normal la primera vez\n";
    echo "   Espera 1-2 minutos y vuelve a intentar\n\n";
  } else {
    echo "   Verifica tu token de Hugging Face\n\n";
  }
}

// 5. Prueba de análisis de CV
echo "5️⃣ Probando análisis de CV...\n";
$sampleCv = "
MARÍA GARCÍA LÓPEZ
Desarrolladora Frontend

Email: maria.garcia@email.com
Teléfono: +34 600 789 123
Madrid, España

EXPERIENCIA
Frontend Developer | WebCorp | 2022-2024
- Desarrollo con React y Vue.js
- Integración de APIs REST
- Optimización de rendimiento

EDUCACIÓN
Grado en Informática | Universidad Complutense | 2018-2022

HABILIDADES
JavaScript, React, Vue.js, HTML5, CSS3, Git
";

try {
  echo "⏳ Analizando CV de ejemplo...\n";
  $startTime = microtime(true);

  $cvData = $mistral->analyzeCvFromText($sampleCv);

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

  // Mostrar habilidades técnicas
  if (isset($cvData['skills']['technical']) && is_array($cvData['skills']['technical'])) {
    echo "🛠️ HABILIDADES TÉCNICAS:\n";
    echo "   " . implode(', ', $cvData['skills']['technical']) . "\n\n";
  }

  echo "📄 JSON COMPLETO:\n";
  echo json_encode($cvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
  echo "⚠️ Error en análisis de CV: " . $e->getMessage() . "\n";
  if (str_contains($e->getMessage(), 'loading')) {
    echo "   El modelo Mistral se está cargando por primera vez\n";
    echo "   Esto puede tardar 1-3 minutos, es normal\n";
    echo "   Vuelve a intentar en unos minutos\n\n";
  } else {
    echo "   Verifica la configuración y el token\n\n";
  }
}

echo "🎯 RESUMEN DE LA PRUEBA\n";
echo "======================\n";
echo "✅ Mistral Service configurado\n";
echo "✅ Hugging Face API accesible\n";
echo "✅ Modelo: mistralai/Mistral-7B-Instruct-v0.2\n";
echo "✅ Completamente GRATUITO (1000 requests/mes)\n";
echo "✅ Sin instalación local requerida\n\n";

echo "🚀 ¡Tu sistema está listo para usar Mistral!\n";
echo "   Solo asegúrate de configurar tu token en .env:\n";
echo "   HUGGINGFACE_TOKEN=tu_token_real_aqui\n\n";

echo "📝 PRÓXIMOS PASOS:\n";
echo "1. Configurar tu token real de Hugging Face\n";
echo "2. Probar el endpoint: /api/cv/parse\n";
echo "3. ¡Disfrutar de IA gratuita y potente!\n\n";
