<?php
// Test del endpoint completo de aplicaciones

require_once __DIR__ . '/config/bootstrap.php';

echo "=== TEST DEL ENDPOINT COMPLETO DE APLICACIONES ===\n\n";

try {
  // Simular una petición GET sin output previo
  $_SERVER['REQUEST_METHOD'] = 'GET';

  // Limpiar cualquier output buffer
  if (ob_get_level()) {
    ob_end_clean();
  }

  // Capturar la salida del endpoint
  ob_start();

  // Incluir el archivo del endpoint
  include __DIR__ . '/api/applications.php';

  $output = ob_get_clean();

  echo "📤 Respuesta del endpoint:\n";
  echo $output;
  echo "\n\n";

  // Intentar decodificar como JSON
  $decoded = json_decode($output, true);
  if ($decoded !== null) {
    echo "✅ JSON válido\n";
    if (isset($decoded['data'])) {
      echo "📊 Número de aplicaciones: " . count($decoded['data']) . "\n";
      if (count($decoded['data']) > 0) {
        echo "🎯 Primera aplicación: " . $decoded['data'][0]['first_name'] . " " . $decoded['data'][0]['last_name'] . "\n";
      }
    }
    if (isset($decoded['success'])) {
      echo "🎯 Estado: " . ($decoded['success'] ? 'Éxito' : 'Error') . "\n";
    }
  } else {
    echo "❌ JSON inválido\n";
    echo "Error JSON: " . json_last_error_msg() . "\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
