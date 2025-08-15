<?php
// Test directo del endpoint de aplicaciones

require_once __DIR__ . '/config/bootstrap.php';

echo "=== TEST DEL ENDPOINT DE APLICACIONES ===\n\n";

try {
  // Simular una petición GET
  $_SERVER['REQUEST_METHOD'] = 'GET';

  // Capturar la salida del endpoint
  ob_start();

  // Incluir el archivo del endpoint
  include __DIR__ . '/api/applications.php';

  $output = ob_get_clean();

  echo "📤 Salida del endpoint:\n";
  echo $output;
  echo "\n\n";

  // Intentar decodificar como JSON para verificar formato
  $decoded = json_decode($output, true);
  if ($decoded !== null) {
    echo "✅ JSON válido\n";
    if (isset($decoded['data'])) {
      echo "📊 Número de aplicaciones: " . count($decoded['data']) . "\n";
    }
    if (isset($decoded['success'])) {
      echo "🎯 Estado: " . ($decoded['success'] ? 'Éxito' : 'Error') . "\n";
    }
  } else {
    echo "❌ JSON inválido o salida no JSON\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
