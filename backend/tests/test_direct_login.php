<?php

// Test directo del método candidateLogin
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php';

use Controllers\AuthController;

// Simular variables de servidor
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/candidate-login';

// Crear mock de php://input
$testData = [
  'email' => 'test@example.com',
  'password' => 'test123'
];

// Simular el input JSON
file_put_contents('php://memory', json_encode($testData));

// Mockear file_get_contents para php://input
function mockFileGetContents($filename)
{
  if ($filename === 'php://input') {
    return '{"email":"test@example.com","password":"test123"}';
  }
  return file_get_contents($filename);
}

try {
  $controller = new AuthController();
  echo "Testing candidateLogin endpoint...\n";

  // Llamar directamente al método
  $result = $controller->candidateLogin([]);

  echo "Test completed successfully!\n";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
  echo "Trace: " . $e->getTraceAsString() . "\n";
}
