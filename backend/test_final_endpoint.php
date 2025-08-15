<?php
// Test final del endpoint real de aplicaciones

// Simular petición HTTP GET
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/applications';

// Desactivar output de errores para obtener JSON limpio
error_reporting(0);

// Capturar salida
ob_start();

try {
  // Incluir el endpoint
  include __DIR__ . '/api/applications.php';

  $output = ob_get_clean();

  // Mostrar resultado
  echo "=== RESPUESTA FINAL DEL ENDPOINT ===\n\n";
  echo $output;
  echo "\n\n";

  // Validar JSON
  $decoded = json_decode($output, true);
  if ($decoded !== null) {
    echo "✅ JSON válido\n";
    echo "📊 Total aplicaciones: " . (isset($decoded['count']) ? $decoded['count'] : 'N/A') . "\n";
    echo "🎯 Estado: " . ($decoded['success'] ? 'Éxito' : 'Error') . "\n";

    if (isset($decoded['data']) && count($decoded['data']) > 0) {
      $firstApp = $decoded['data'][0];
      echo "👤 Primera aplicación: " . $firstApp['candidate']['name'] . " -> " . $firstApp['job']['title'] . "\n";
      echo "🏢 Candidato Depto: " . ($firstApp['candidate']['department_name'] ?? 'N/A') . "\n";
      echo "🏢 Trabajo Depto: " . ($firstApp['job']['department_name'] ?? 'N/A') . "\n";
      echo "💰 Salario: " . ($firstApp['job']['salary_range'] ?? 'N/A') . "\n";
    }
  } else {
    echo "❌ JSON inválido: " . json_last_error_msg() . "\n";
  }
} catch (Exception $e) {
  ob_end_clean();
  echo "❌ Error: " . $e->getMessage() . "\n";
}
