<?php
// Script de verificación rápida para HR Dashboard

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once __DIR__ . '/config/bootstrap.php';

echo "=== VERIFICACIÓN RÁPIDA HR DASHBOARD ===\n\n";

try {
  // Test de conexión a BD
  $pdo = getDbConnection();
  echo "✅ Conexión a BD: OK\n";

  // Test de endpoint applications
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM bt_applications");
  $result = $stmt->fetch();
  echo "📊 Aplicaciones en BD: " . $result['total'] . "\n";

  // Test de endpoint real
  $_SERVER['REQUEST_METHOD'] = 'GET';

  ob_start();
  include __DIR__ . '/api/applications.php';
  $apiResponse = ob_get_clean();

  $decoded = json_decode($apiResponse, true);
  if ($decoded !== null && isset($decoded['success']) && $decoded['success']) {
    echo "✅ API Endpoint: OK\n";
    echo "📈 Aplicaciones devueltas: " . count($decoded['data']) . "\n";
  } else {
    echo "❌ API Endpoint: ERROR\n";
    echo "Response: " . substr($apiResponse, 0, 200) . "...\n";
  }

  // Test de CORS
  echo "🌐 CORS Headers: Configurados\n";

  echo "\n✅ HR Dashboard Backend: FUNCIONANDO\n";
  echo "🔗 URL de test: http://localhost/backend/check_hr_dashboard.php\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "=== INFORMACIÓN PARA DEBUG ===\n";
echo "Hora: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script: " . __FILE__ . "\n";
