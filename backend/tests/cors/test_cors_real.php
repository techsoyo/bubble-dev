#!/usr/bin/env php
<?php

/**
 * Test CORS con petición HTTP real usando curl
 */

echo "=== TEST CORS CON PETICIÓN REAL ===\n\n";

// Función para hacer petición OPTIONS preflight
function testCorsOptions($url, $origin)
{
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_CUSTOMREQUEST => "OPTIONS",
    CURLOPT_HTTPHEADER => [
      "Origin: $origin",
      "Access-Control-Request-Method: POST",
      "Access-Control-Request-Headers: Content-Type,Authorization"
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
    CURLOPT_TIMEOUT => 5
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return ['response' => $response, 'code' => $httpCode];
}

// Test 1: Origin permitido
echo "🧪 Test 1: Origin permitido (http://localhost:3002)\n";
$result1 = testCorsOptions('http://localhost:8000/api/health.php', 'http://localhost:3002');

echo "Status Code: {$result1['code']}\n";
echo "Response Headers:\n";
$headers = explode("\n", $result1['response']);
foreach ($headers as $header) {
  if (stripos($header, 'access-control') !== false || stripos($header, 'vary') !== false) {
    echo "  ✅ $header\n";
  }
}
echo "\n";

// Test 2: Origin NO permitido
echo "🧪 Test 2: Origin NO permitido (http://malicious-site.com)\n";
$result2 = testCorsOptions('http://localhost:8000/api/health.php', 'http://malicious-site.com');

echo "Status Code: {$result2['code']}\n";
echo "Response Headers:\n";
$headers = explode("\n", $result2['response']);
$allowOriginFound = false;
foreach ($headers as $header) {
  if (stripos($header, 'access-control-allow-origin') !== false) {
    $allowOriginFound = true;
    echo "  ❌ PROBLEMA: $header (no debería estar presente)\n";
  } elseif (stripos($header, 'access-control') !== false || stripos($header, 'vary') !== false) {
    echo "  ✅ $header\n";
  }
}

if (!$allowOriginFound) {
  echo "  ✅ Correcto: No Access-Control-Allow-Origin para origin no permitido\n";
}

echo "\n=== TEST COMPLETADO ===\n";
