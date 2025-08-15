<?php

/**
 * Test específico para endpoints que estaban fallando en frontend
 */

declare(strict_types=1);

echo "🔍 TESTING ENDPOINTS ESPECÍFICOS CON PROBLEMAS CORS\n";
echo "=" . str_repeat("=", 55) . "\n\n";

$problematic_endpoints = [
  'http://localhost:8000/api/endpoints/chatbot_decision_tree.php' => 'Chatbot Decision Tree',
  'http://localhost:8000/api/endpoints/culture.php' => 'Culture Content',
  'http://localhost:8000/auth/verify-session.php' => 'Session Verification'
];

$origin = 'http://localhost:3002';
$passed = 0;
$total = 0;

foreach ($problematic_endpoints as $url => $description) {
  $total++;
  echo "📋 Testing: $description\n";
  echo "  URL: $url\n";

  // Test con cURL
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
    CURLOPT_HTTPHEADER => [
      "Origin: $origin",
      'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 10
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($response === false) {
    echo "  ❌ Error: No se pudo conectar\n";
    continue;
  }

  // Verificar headers CORS
  $corsHeaders = [
    'Access-Control-Allow-Origin' => false,
    'Access-Control-Allow-Methods' => false,
    'Access-Control-Allow-Headers' => false,
    'Access-Control-Allow-Credentials' => false
  ];

  foreach ($corsHeaders as $header => $found) {
    if (stripos($response, $header) !== false) {
      $corsHeaders[$header] = true;
    }
  }

  $corsPresent = array_sum($corsHeaders) >= 3; // Al menos 3 headers CORS presentes

  if ($corsPresent && ($httpCode === 200 || $httpCode === 405)) {
    echo "  ✅ CORS Headers presentes (HTTP $httpCode)\n";
    if (stripos($response, "Access-Control-Allow-Origin: $origin") !== false) {
      echo "  ✅ Origin correcto permitido\n";
    } else if (stripos($response, "Access-Control-Allow-Origin: *") !== false) {
      echo "  ⚠️ Wildcard origin (no ideal pero funciona)\n";
    } else {
      echo "  ⚠️ Origin encontrado pero puede no coincidir\n";
    }
    $passed++;
  } else {
    echo "  ❌ CORS Headers faltantes o error HTTP ($httpCode)\n";
    echo "  📄 Headers encontrados:\n";
    foreach ($corsHeaders as $header => $found) {
      $status = $found ? '✅' : '❌';
      echo "    $status $header\n";
    }
  }

  echo "\n";
}

echo str_repeat("=", 55) . "\n";
echo "📊 RESULTADO FINAL\n";
echo str_repeat("=", 55) . "\n";
echo "Tests pasados: $passed/$total\n";

if ($passed === $total) {
  echo "🎉 TODOS LOS ENDPOINTS PROBLEMÁTICOS AHORA FUNCIONAN\n";
  echo "✅ Los errores CORS en el frontend deberían estar resueltos\n";
} else {
  echo "⚠️ Algunos endpoints aún tienen problemas\n";
  echo "🔧 Requieren investigación adicional\n";
}

echo "\n📋 Próximos pasos:\n";
echo "1. Refrescar el frontend (Ctrl+F5)\n";
echo "2. Verificar que los errores CORS hayan desaparecido\n";
echo "3. Si persisten errores, verificar configuración de proxy en vite.config.ts\n";

echo "\n";
