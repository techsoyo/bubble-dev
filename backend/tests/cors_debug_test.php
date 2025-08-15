<?php

/**
 * Test simple de CORS directo
 */

echo "Testing CORS system...\n";

// Simular servidor web environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/auth/candidate-login.php';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3002';

// Capture output
ob_start();

try {
  require_once __DIR__ . '/../config/bootstrap.php';
  echo "✅ Bootstrap loaded successfully\n";
} catch (Exception $e) {
  echo "❌ Bootstrap error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
echo $output;

// Check headers
$headers = headers_list();
echo "\nHeaders sent:\n";
foreach ($headers as $header) {
  echo "  $header\n";
}

echo "\nTest completed.\n";
