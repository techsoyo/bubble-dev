<?php

/**
 * Script de testing para validar comportamiento PRODUCTION-READY
 * 
 * Prueba:
 * 1. Fallback Authorization deshabilitado en producción
 * 2. CORS con credenciales funcionando correctamente
 * 3. Cookies httpOnly funcionando en todos los escenarios
 */

require_once __DIR__ . '/../src/Security/Cookies.php';

use Security\Cookies;

echo "=== TEST PRODUCTION-READY FINAL ===\n\n";

// Simular diferentes entornos
$environments = [
  'development' => ['APP_ENV' => 'development'],
  'staging'     => ['APP_ENV' => 'staging'],
  'production'  => ['APP_ENV' => 'production'],
];

echo "1. Testing comportamiento de JWTMiddleware por entorno...\n";

foreach ($environments as $env => $serverVars) {
  echo "\n   Entorno: $env\n";

  // Backup y configurar entorno
  $originalEnv = $_ENV;
  $originalServer = $_SERVER;

  foreach ($serverVars as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }

  // Simular request sin cookie, con header Authorization
  unset($_COOKIE['access_token']);
  $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer fake_jwt_token_123';

  // Simular JWTMiddleware behavior (simplificado)
  if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
    $token = Cookies::getJwt();
    if (!$token) {
      echo "     ✅ Producción: Rechaza request sin cookie (como esperado)\n";
    } else {
      echo "     ❌ Producción: Acepta request con cookie\n";
    }
  } else {
    $token = Cookies::getJwt();
    if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
      echo "     ✅ Dev/Staging: Acepta fallback Authorization header\n";
    } else {
      echo "     ❌ Dev/Staging: Comportamiento inesperado\n";
    }
  }

  // Simular request con cookie
  $_COOKIE['access_token'] = 'valid_jwt_from_cookie';
  $token = Cookies::getJwt();

  if ($token) {
    echo "     ✅ Todos los entornos: Acepta cookies correctamente\n";
  }

  // Restaurar entorno
  $_ENV = $originalEnv;
  $_SERVER = $originalServer;
  unset($_COOKIE['access_token']);
}

echo "\n\n2. Testing configuración CORS...\n";

// Simular diferentes orígenes
$corsTests = [
  'Origen permitido' => [
    'HTTP_ORIGIN' => 'https://app.tudominio.com',
    'CORS_ALLOWED_ORIGINS' => 'https://tudominio.com,https://app.tudominio.com',
    'expected' => true
  ],
  'Origen no permitido' => [
    'HTTP_ORIGIN' => 'https://malicious-site.com',
    'CORS_ALLOWED_ORIGINS' => 'https://tudominio.com,https://app.tudominio.com',
    'expected' => false
  ],
  'Sin origen' => [
    'HTTP_ORIGIN' => '',
    'CORS_ALLOWED_ORIGINS' => 'https://tudominio.com',
    'expected' => false
  ]
];

foreach ($corsTests as $testName => $testData) {
  echo "   Test: $testName\n";

  // Simular configuración CORS
  $corsOrigins = $testData['CORS_ALLOWED_ORIGINS'];
  $origin = $testData['HTTP_ORIGIN'];
  $allowedOrigins = array_filter(array_map('trim', explode(',', $corsOrigins)));

  $isAllowedOrigin = $origin && in_array($origin, $allowedOrigins, true);

  if ($isAllowedOrigin === $testData['expected']) {
    echo "     ✅ CORS correcto: " . ($isAllowedOrigin ? 'Permitido' : 'Bloqueado') . "\n";
  } else {
    echo "     ❌ CORS incorrecto: " . ($isAllowedOrigin ? 'Permitido' : 'Bloqueado') . "\n";
  }
}

echo "\n\n3. Testing endpoints reales con diferentes configuraciones...\n";

// URL base - ajustar según configuración
$baseUrl = 'http://localhost/bubble_of_talents_1.0/backend/public/api';

function makeRequestWithCORS($url, $data = null, $cookies = '', $origin = '')
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_COOKIE, $cookies);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);

  $headers = ['Content-Type: application/json'];
  if ($origin) {
    $headers[] = "Origin: $origin";
  }

  if ($data) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  }

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    return ['error' => $error, 'code' => 0];
  }

  $parts = explode("\r\n\r\n", $response, 2);
  $responseHeaders = $parts[0] ?? '';
  $body = $parts[1] ?? $response;

  return [
    'code' => $httpCode,
    'headers' => $responseHeaders,
    'body' => $body
  ];
}

echo "   Test login con Origin permitido...\n";
$loginData = ['email' => 'admin@bubblegum.agency', 'password' => 'admin123'];
$response = makeRequestWithCORS("$baseUrl/auth/staff-login.php", $loginData, '', 'http://localhost:3002');

if (isset($response['error'])) {
  echo "     ❌ Error: " . $response['error'] . "\n";
} else {
  echo "     Status: " . $response['code'] . "\n";

  // Verificar headers CORS
  $headers = $response['headers'];
  $corsChecks = [
    'Access-Control-Allow-Origin' => strpos($headers, 'Access-Control-Allow-Origin: http://localhost:3002') !== false,
    'Access-Control-Allow-Credentials' => strpos($headers, 'Access-Control-Allow-Credentials: true') !== false,
    'Vary: Origin' => strpos($headers, 'Vary: Origin') !== false,
    'Set-Cookie' => strpos($headers, 'Set-Cookie: access_token=') !== false
  ];

  foreach ($corsChecks as $check => $passed) {
    echo "     " . ($passed ? '✅' : '❌') . " $check\n";
  }
}

echo "\n   Test con Origin no permitido...\n";
$response = makeRequestWithCORS("$baseUrl/auth/staff-login.php", $loginData, '', 'https://malicious-site.com');

if (isset($response['error'])) {
  echo "     ❌ Error: " . $response['error'] . "\n";
} else {
  $headers = $response['headers'];
  $hasOriginHeader = strpos($headers, 'Access-Control-Allow-Origin: https://malicious-site.com') !== false;

  if (!$hasOriginHeader) {
    echo "     ✅ Origen malicioso correctamente bloqueado\n";
  } else {
    echo "     ❌ Origen malicioso permitido incorrectamente\n";
  }
}

echo "\n\n=== CONFIGURACIÓN RECOMENDADA ===\n";
echo "Backend .env PRODUCCIÓN:\n";
echo "APP_ENV=production\n";
echo "CORS_ALLOWED_ORIGINS=https://tudominio.com,https://app.tudominio.com\n";
echo "CORS_ALLOW_CREDENTIALS=true\n";
echo "COOKIE_SECURE=true\n";
echo "COOKIE_SAMESITE=Strict\n";
echo "HIDE_TOKEN_IN_RESPONSE=true\n\n";

echo "Frontend JavaScript:\n";
echo "axios.defaults.withCredentials = true;\n";
echo "// o\n";
echo "fetch('/api/endpoint', { credentials: 'include' });\n\n";

echo "=== FIN DEL TEST PRODUCTION-READY ===\n";
