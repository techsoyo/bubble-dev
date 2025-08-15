<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}
// Prueba directa del endpoint auth.php
echo "=== PROBANDO ENDPOINT AUTH.PHP ===\n";

try {
  // Forzar la solicitud POST
  $_SERVER['REQUEST_METHOD'] = 'POST';
  $_GET['action'] = 'login';

  // Datos JSON de entrada
  $testData = json_encode([
    'email' => 'user@local',
    'password' => 'passA!2025'
  ]);

  // Crear un stream temporal para php://input
  file_put_contents('php://temp', $testData);

  echo "Datos de prueba preparados\n";
  echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
  echo "Action: " . $_GET['action'] . "\n";
  echo "Datos JSON: " . $testData . "\n\n";

  // Incluir el archivo auth.php
  ob_start(); // Capturar output
  include __DIR__ . '/api/auth.php';
  $output = ob_get_clean();

  echo "RESULTADO:\n";
  echo $output;
} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
  echo "Archivo: " . $e->getFile() . "\n";
  echo "Línea: " . $e->getLine() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
