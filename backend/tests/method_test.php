<?php

/**
 * Test directo de endpoint para verificar restricciones de método
 */

// Simular request PUT a endpoint de auth (debería retornar 405)
$_SERVER['REQUEST_METHOD'] = 'PUT';
$_SERVER['REQUEST_URI'] = '/auth/candidate-login.php';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3002';

// Capture any output and response code
ob_start();
$errorOccurred = false;

try {
  // Don't include bootstrap to avoid other outputs
  require_once __DIR__ . '/../cors.php';
} catch (Exception $e) {
  $errorOccurred = true;
  echo "Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
$responseCode = http_response_code();

echo "Method: PUT\n";
echo "Endpoint: /auth/candidate-login.php\n";
echo "Response Code: $responseCode\n";
echo "Output: $output\n";
echo "Error Occurred: " . ($errorOccurred ? 'Yes' : 'No') . "\n";

// Check if method restriction worked
if ($responseCode === 405) {
  echo "✅ Method restriction working correctly\n";
} else {
  echo "❌ Method restriction NOT working - expected 405, got $responseCode\n";
}
