#!/usr/bin/env php
<?php

/**
 * Test CORS completo - verifica todos los endpoints principales
 */

echo "=== TEST CORS COMPLETO - TODOS LOS ENDPOINTS ===\n\n";

// Lista de endpoints principales para probar
$endpoints = [
  // Endpoints básicos
  '/api/health.php',
  '/api/index.php',

  // Autenticación
  '/api/auth.php',
  //'/api/auth/login.php',
  '/api/auth/register.php',

  // Candidatos
  '/api/candidates.php',
  '/api/candidates/profile.php',
  '/api/candidate-applications.php',
  '/api/candidate-experiences.php',
  '/api/candidate-notifications.php',

  // Trabajos
  '/api/jobs.php',
  '/api/applications.php',

  // Análisis CV
  '/api/analyze_cv.php',
  '/api/cv/extract.php',

  // IA y Matching
  '/api/ai_assignment.php',
  '/api/calculate_matching.php',
  '/api/match.php',

  // Otros
  '/api/chatbot.php',
  '/api/departments.php',
  '/api/news.php',
  '/api/notifications.php',
  '/api/statistics.php',
];

$baseUrl = 'http://localhost:8000';
$testOrigins = [
  'http://localhost:3002' => true,  // Permitido
  'http://localhost:3000' => true,  // Permitido  
  'http://localhost:5173' => true,  // Permitido (Vite default)
  'http://malicious-site.com' => false, // NO permitido
];

// Función para hacer petición OPTIONS preflight
function testCorsEndpoint($url, $origin)
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
    CURLOPT_TIMEOUT => 3,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_FOLLOWLOCATION => false
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    return ['response' => '', 'code' => 0, 'error' => $error];
  }

  return ['response' => $response, 'code' => $httpCode, 'error' => null];
}

// Función para extraer headers CORS de la respuesta
function extractCorsHeaders($response)
{
  $headers = [];
  $lines = explode("\n", $response);

  foreach ($lines as $line) {
    $line = trim($line);
    if (
      stripos($line, 'access-control') !== false ||
      stripos($line, 'vary') !== false
    ) {
      $headers[] = $line;
    }
  }

  return $headers;
}

$totalTests = 0;
$passedTests = 0;
$failedEndpoints = [];

echo "🌐 Servidor base: $baseUrl\n";
echo "🧪 Probando " . count($endpoints) . " endpoints con " . count($testOrigins) . " orígenes\n\n";

foreach ($endpoints as $endpoint) {
  echo "📍 Endpoint: $endpoint\n";

  foreach ($testOrigins as $origin => $shouldBeAllowed) {
    $totalTests++;
    $url = $baseUrl . $endpoint;
    $result = testCorsEndpoint($url, $origin);

    $status = "❓";
    $message = "";

    if ($result['error']) {
      $status = "❌";
      $message = "Error: {$result['error']}";
      $failedEndpoints[] = $endpoint;
    } else if ($result['code'] === 404) {
      $status = "⚠️";
      $message = "404 (endpoint no existe, pero CORS debería aplicarse)";
    } else if ($result['code'] === 204 || $result['code'] === 200) {
      // Verificar headers CORS
      $corsHeaders = extractCorsHeaders($result['response']);
      $hasAllowOrigin = false;
      $hasVaryOrigin = false;
      $allowOriginValue = '';

      foreach ($corsHeaders as $header) {
        if (stripos($header, 'access-control-allow-origin:') !== false) {
          $hasAllowOrigin = true;
          $allowOriginValue = trim(substr($header, strpos($header, ':') + 1));
        }
        if (stripos($header, 'vary:') !== false && stripos($header, 'origin') !== false) {
          $hasVaryOrigin = true;
        }
      }

      if ($shouldBeAllowed) {
        if ($hasAllowOrigin && $allowOriginValue === $origin && $hasVaryOrigin) {
          $status = "✅";
          $message = "CORS correcto";
          $passedTests++;
        } else {
          $status = "❌";
          $message = "CORS incorrecto - Allow-Origin: '$allowOriginValue', Vary: " . ($hasVaryOrigin ? "✅" : "❌");
          $failedEndpoints[] = $endpoint;
        }
      } else {
        // Origin no debería estar permitido
        if (!$hasAllowOrigin && $hasVaryOrigin) {
          $status = "✅";
          $message = "CORS correcto (origin rechazado)";
          $passedTests++;
        } else if ($hasAllowOrigin) {
          $status = "❌";
          $message = "PROBLEMA: Allow-Origin presente para origin no permitido";
          $failedEndpoints[] = $endpoint;
        } else {
          $status = "⚠️";
          $message = "Sin Vary: Origin header";
        }
      }
    } else {
      $status = "❌";
      $message = "HTTP {$result['code']} inesperado";
      $failedEndpoints[] = $endpoint;
    }

    echo "  $status $origin: $message\n";
  }
  echo "\n";
}

// Resumen final
echo "=== RESUMEN FINAL ===\n";
echo "📊 Tests totales: $totalTests\n";
echo "✅ Tests pasados: $passedTests\n";
echo "❌ Tests fallidos: " . ($totalTests - $passedTests) . "\n";
echo "📈 Tasa de éxito: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

if (!empty($failedEndpoints)) {
  $failedEndpoints = array_unique($failedEndpoints);
  echo "🚨 Endpoints con problemas CORS:\n";
  foreach ($failedEndpoints as $endpoint) {
    echo "  - $endpoint\n";
  }
  echo "\n";
}

if ($passedTests === $totalTests) {
  echo "🎉 ¡TODOS LOS TESTS PASARON! CORS configurado correctamente.\n";
} else {
  echo "⚠️  Hay endpoints que necesitan corrección.\n";
}

echo "\n=== TEST COMPLETADO ===\n";
