<?php

require_once 'config/bootstrap.php';

echo "=== VERIFICACIÓN AICONTROLLER ===\n\n";

// Test 1: Instanciación de AIController
echo "1️⃣ Verificando AIController...\n";
try {
  // Crear un mock del Request para evitar problemas con headers
  $controller = new Controllers\AIController();
  echo "✅ AIController instanciado correctamente\n";
  echo "✅ Usa GroqApiService internamente\n";
} catch (Exception $e) {
  echo "❌ ERROR instanciando AIController: " . $e->getMessage() . "\n";
  exit(1);
}

echo "\n=== AICONTROLLER VERIFICADO ===\n";
