<?php

/**
 * Test para verificar PHP_SAPI
 */

echo "PHP_SAPI: " . PHP_SAPI . "\n";
echo "Is CLI?: " . (PHP_SAPI === 'cli' ? 'Yes' : 'No') . "\n";

// Simular una request web
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/test';

echo "\nSimulating web request...\n";
echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'not set') . "\n";

// Probar si cors.php se ejecutaría
if (PHP_SAPI === 'cli') {
  echo "CORS would RETURN early (CLI detected)\n";
} else {
  echo "CORS would EXECUTE (not CLI)\n";
}

echo "\nAttempting to load cors.php...\n";
ob_start();
require_once __DIR__ . '/../cors.php';
$output = ob_get_clean();

echo "Output from cors.php: '$output'\n";

if (defined('CORS_APPLIED')) {
  echo "✅ CORS_APPLIED is now defined\n";
  echo "Applied at: " . CORS_APPLIED_AT . "\n";
} else {
  echo "❌ CORS_APPLIED still not defined\n";
}
