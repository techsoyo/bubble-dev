<?php

require_once 'config/bootstrap.php';

echo "=== VERIFICACIÓN COMPLETA GROQAPISERVICE ===\n\n";

// Test 1: Instanciación
echo "1️⃣ Verificando instanciación de GroqApiService...\n";
try {
  $service = new Services\GroqApiService();
  echo "✅ GroqApiService instanciado correctamente\n";
} catch (Exception $e) {
  echo "❌ ERROR instanciando GroqApiService: " . $e->getMessage() . "\n";
  exit(1);
}

// Test 2: Configuración
echo "\n2️⃣ Verificando configuración...\n";
try {
  $serviceInfo = $service->getServiceInfo();
  echo "✅ API Key configurada: " . (isset($serviceInfo['available']) && $serviceInfo['available'] ? 'SÍ' : 'NO') . "\n";
  echo "✅ Modelo: " . $serviceInfo['model'] . "\n";
  echo "✅ Proveedor: " . $serviceInfo['provider'] . "\n";
} catch (Exception $e) {
  echo "❌ ERROR obteniendo información: " . $e->getMessage() . "\n";
}

// Test 3: Disponibilidad
echo "\n3️⃣ Verificando disponibilidad de Groq API...\n";
try {
  if ($service->isAvailable()) {
    echo "✅ Groq API disponible\n";
  } else {
    echo "❌ Groq API no disponible\n";
  }
} catch (Exception $e) {
  echo "❌ ERROR verificando disponibilidad: " . $e->getMessage() . "\n";
}

// Test 4: Análisis de CV pequeño
echo "\n4️⃣ Probando análisis de CV simple...\n";
try {
  $cvText = "Nombre: Juan Pérez\nEmail: juan@example.com\nTeléfono: 123456789\nExperiencia: 3 años como desarrollador";

  $startTime = microtime(true);
  $result = $service->analyzeCvFromText($cvText);
  $duration = round((microtime(true) - $startTime) * 1000);

  echo "✅ Análisis completado en {$duration}ms\n";
  echo "✅ Campos extraídos: " . count($result) . "\n";

  // Verificar campos clave
  $keysToCheck = ['nombre', 'email', 'telefono', 'data_source', 'routing'];
  foreach ($keysToCheck as $key) {
    if (array_key_exists($key, $result)) {
      echo "✅ Campo '$key': " . (is_array($result[$key]) ? 'ARRAY' : $result[$key]) . "\n";
    } else {
      echo "❌ Campo '$key': NO ENCONTRADO\n";
    }
  }
} catch (Exception $e) {
  echo "❌ ERROR en análisis: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
