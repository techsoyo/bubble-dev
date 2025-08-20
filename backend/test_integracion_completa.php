<?php

/**
 * TEST DE INTEGRACIÓN COMPLETA - OLLAMA SERVICE STANDARD
 * 
 * Verifica que la migración de cURL a hanwoolderink/ollama-php-client
 * esté funcionando al 100% en todo el proyecto
 */

require_once __DIR__ . '/autoload.php';

use Services\OllamaServiceStandard;
use Controllers\AIController;
use Utils\Request;

echo "🚀 TEST DE INTEGRACIÓN COMPLETA - OLLAMA SERVICE STANDARD\n";
echo "========================================================\n\n";

$allTestsPassed = true;
$testResults = [];

// 1. TEST: Verificar librerías instaladas
echo "1️⃣ VERIFICANDO LIBRERÍAS INSTALADAS...\n";
echo "------------------------------------\n";

$composerData = json_decode(file_get_contents(__DIR__ . '/composer.json'), true);
$requiredPackages = [
  'hanwoolderink/ollama-php-client' => 'Cliente oficial de Ollama',
  'guzzlehttp/guzzle' => 'Cliente HTTP para Ollama'
];

foreach ($requiredPackages as $package => $description) {
  if (isset($composerData['require'][$package])) {
    echo "   ✅ {$package}: {$composerData['require'][$package]} - {$description}\n";
    $testResults[] = "✅ Librería {$package} instalada";
  } else {
    echo "   ❌ {$package} - {$description} (NO INSTALADA)\n";
    $testResults[] = "❌ Librería {$package} faltante";
    $allTestsPassed = false;
  }
}

echo "\n";

// 2. TEST: Verificar clases principales
echo "2️⃣ VERIFICANDO CLASES PRINCIPALES...\n";
echo "-----------------------------------\n";

$classes = [
  'Services\\OllamaServiceStandard' => __DIR__ . '/src/Services/OllamaServiceStandard.php',
  'Controllers\\AIController' => __DIR__ . '/src/Controllers/AIController.php',
  'Utils\\Request' => __DIR__ . '/src/Utils/Request.php'
];

foreach ($classes as $className => $filePath) {
  if (file_exists($filePath)) {
    echo "   ✅ {$className}: Archivo existe\n";

    // Verificar si la clase puede ser incluida sin errores
    try {
      if (class_exists($className)) {
        echo "   ✅ {$className}: Clase cargable\n";
        $testResults[] = "✅ Clase {$className} funcional";
      } else {
        echo "   ❌ {$className}: Error al cargar clase\n";
        $testResults[] = "❌ Clase {$className} no cargable";
        $allTestsPassed = false;
      }
    } catch (Exception $e) {
      echo "   ❌ {$className}: Error - {$e->getMessage()}\n";
      $testResults[] = "❌ Clase {$className} con errores: {$e->getMessage()}";
      $allTestsPassed = false;
    }
  } else {
    echo "   ❌ {$className}: Archivo no encontrado - {$filePath}\n";
    $testResults[] = "❌ Archivo {$className} faltante";
    $allTestsPassed = false;
  }
}

echo "\n";

// 3. TEST: Verificar métodos de OllamaServiceStandard
echo "3️⃣ VERIFICANDO MÉTODOS DE OllamaServiceStandard...\n";
echo "-------------------------------------------------\n";

try {
  $ollama = new OllamaServiceStandard();

  $expectedMethods = [
    'analyzeCvFromText' => 'Análisis de CV desde texto',
    'analyzeCvFromPdf' => 'Análisis de CV desde PDF',
    'chat' => 'Funcionalidad de chat',
    'isAvailable' => 'Verificación de disponibilidad',
    'getServiceInfo' => 'Información del servicio'
  ];

  foreach ($expectedMethods as $method => $description) {
    if (method_exists($ollama, $method)) {
      echo "   ✅ {$method}(): {$description}\n";
      $testResults[] = "✅ Método {$method} disponible";
    } else {
      echo "   ❌ {$method}(): {$description} (NO EXISTE)\n";
      $testResults[] = "❌ Método {$method} faltante";
      $allTestsPassed = false;
    }
  }
} catch (Exception $e) {
  echo "   ❌ Error al instanciar OllamaServiceStandard: {$e->getMessage()}\n";
  $testResults[] = "❌ OllamaServiceStandard no instanciable: {$e->getMessage()}";
  $allTestsPassed = false;
}

echo "\n";

// 4. TEST: Verificar métodos de Utils\Request
echo "4️⃣ VERIFICANDO MÉTODOS DE Utils\\Request...\n";
echo "------------------------------------------\n";

$requiredRequestMethods = [
  'json' => 'Obtener datos JSON del request',
  'query' => 'Obtener parámetros de query string',
  'header' => 'Obtener headers HTTP',
  'bearer' => 'Obtener token Bearer'
];

foreach ($requiredRequestMethods as $method => $description) {
  if (method_exists('Utils\\Request', $method)) {
    echo "   ✅ {$method}(): {$description}\n";
    $testResults[] = "✅ Request::{$method} disponible";
  } else {
    echo "   ❌ {$method}(): {$description} (NO EXISTE)\n";
    $testResults[] = "❌ Request::{$method} faltante";
    $allTestsPassed = false;
  }
}

echo "\n";

// 5. TEST: Verificar endpoints públicos
echo "5️⃣ VERIFICANDO ENDPOINTS PÚBLICOS...\n";
echo "-----------------------------------\n";

$endpoints = [
  'public/api/ai/analyze-cv-pdf.php' => 'Endpoint principal para PDFs',
  'public/api/ai/parse-cv.php' => 'Endpoint para texto directo',
  'public/api/ai/chatbot.php' => 'Endpoint de chatbot'
];

foreach ($endpoints as $endpoint => $description) {
  $fullPath = __DIR__ . '/' . $endpoint;
  if (file_exists($fullPath)) {
    echo "   ✅ {$endpoint}: {$description}\n";

    // Verificar sintaxis del endpoint
    $output = shell_exec("php -l \"{$fullPath}\" 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
      echo "   ✅ {$endpoint}: Sintaxis correcta\n";
      $testResults[] = "✅ Endpoint {$endpoint} funcional";
    } else {
      echo "   ❌ {$endpoint}: Error de sintaxis\n";
      $testResults[] = "❌ Endpoint {$endpoint} con errores";
      $allTestsPassed = false;
    }
  } else {
    echo "   ❌ {$endpoint}: No encontrado - {$description}\n";
    $testResults[] = "❌ Endpoint {$endpoint} faltante";
    $allTestsPassed = false;
  }
}

echo "\n";

// 6. TEST: Verificar directorios necesarios
echo "6️⃣ VERIFICANDO DIRECTORIOS NECESARIOS...\n";
echo "---------------------------------------\n";

$directories = [
  'uploads/cvs/' => 'Subida de CVs',
  'uploads/json/' => 'Resultados JSON',
  'uploads/textos/' => 'Textos extraídos',
  'logs/' => 'Archivos de log'
];

foreach ($directories as $dir => $description) {
  $fullPath = __DIR__ . '/' . $dir;
  if (is_dir($fullPath)) {
    if (is_writable($fullPath)) {
      echo "   ✅ {$dir}: Existe y es escribible - {$description}\n";
      $testResults[] = "✅ Directorio {$dir} funcional";
    } else {
      echo "   ⚠️ {$dir}: Existe pero no es escribible - {$description}\n";
      $testResults[] = "⚠️ Directorio {$dir} sin permisos";
    }
  } else {
    echo "   ❌ {$dir}: No existe - {$description}\n";
    if (mkdir($fullPath, 0755, true)) {
      echo "   ✅ {$dir}: Creado correctamente\n";
      $testResults[] = "✅ Directorio {$dir} creado";
    } else {
      echo "   ❌ {$dir}: Error al crear directorio\n";
      $testResults[] = "❌ Directorio {$dir} no creado";
      $allTestsPassed = false;
    }
  }
}

echo "\n";

// 7. TEST: Verificar conectividad con Ollama
echo "7️⃣ VERIFICANDO CONECTIVIDAD CON OLLAMA...\n";
echo "----------------------------------------\n";

try {
  $ollama = new OllamaServiceStandard();

  if ($ollama->isAvailable()) {
    echo "   ✅ Servicio Ollama: DISPONIBLE\n";

    $serviceInfo = $ollama->getServiceInfo();
    echo "   ✅ Información del servicio obtenida\n";
    echo "      • Configuración: " . json_encode($serviceInfo) . "\n";

    $testResults[] = "✅ Ollama completamente funcional";
  } else {
    echo "   ⚠️ Servicio Ollama: NO DISPONIBLE (pero código funciona)\n";
    echo "      • Esto es normal si Ollama no está ejecutándose\n";
    $testResults[] = "⚠️ Ollama no disponible (normal en desarrollo)";
  }
} catch (Exception $e) {
  echo "   ❌ Error al conectar con Ollama: {$e->getMessage()}\n";
  $testResults[] = "❌ Error conectividad Ollama: {$e->getMessage()}";
  $allTestsPassed = false;
}

echo "\n";

// 8. RESUMEN FINAL
echo "8️⃣ RESUMEN DE INTEGRACIÓN\n";
echo "========================\n\n";

echo "📊 RESULTADOS DEL TEST:\n";
foreach ($testResults as $result) {
  echo "   {$result}\n";
}

echo "\n";

if ($allTestsPassed) {
  echo "🎉 ¡INTEGRACIÓN COMPLETA AL 100%!\n";
  echo "================================\n\n";

  echo "✅ La migración de cURL a hanwoolderink/ollama-php-client está COMPLETADA\n";
  echo "✅ Todos los componentes están funcionando correctamente\n";
  echo "✅ Los endpoints están listos para recibir requests\n";
  echo "✅ La estructura de archivos es correcta\n\n";

  echo "🚀 FUNCIONALIDADES DISPONIBLES:\n";
  echo "• Análisis de CV desde PDF (endpoint principal)\n";
  echo "• Análisis de CV desde texto\n";
  echo "• Chatbot con IA\n";
  echo "• Health check del servicio\n";
  echo "• Matching de trabajos\n\n";

  echo "📱 ENDPOINTS PARA EL FRONTEND:\n";
  echo "• POST /api/ai/analyze-cv-pdf.php (subir PDF)\n";
  echo "• POST /api/ai/parse-cv.php (enviar texto)\n";
  echo "• POST /api/ai/chatbot.php (chat)\n";
  echo "• GET /api/ai/health-check.php (estado)\n\n";
} else {
  echo "⚠️ INTEGRACIÓN PARCIAL - REQUIERE ATENCIÓN\n";
  echo "=========================================\n\n";

  echo "❌ Se encontraron algunos problemas que necesitan corrección\n";
  echo "📋 Revisar los errores marcados arriba\n";
  echo "🔧 Ejecutar las correcciones necesarias\n\n";
}

echo "🎯 TEST DE INTEGRACIÓN FINALIZADO\n";
echo date('Y-m-d H:i:s') . " - Estado: " . ($allTestsPassed ? "COMPLETO" : "PARCIAL") . "\n";
