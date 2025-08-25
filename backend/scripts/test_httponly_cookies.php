<?php

/**
 * Script de prueba para verificar el funcionamiento de las cookies httpOnly
 * 
 * Este script puede usarse para probar:
 * 1. Login con cookies httpOnly
 * 2. Acceso a endpoints protegidos usando cookies
 * 3. Logout limpiando las cookies
 */

echo "=== TEST DE COOKIES HTTPONLY ===\n\n";

// URL base - ajustar según tu configuración
$baseUrl = 'http://localhost/bubble_of_talents_1.0/backend/public/api';

// Función para hacer requests con cookies
function makeRequest($url, $data = null, $cookies = '')
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_COOKIE, $cookies);

  if ($data) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json'
    ]);
  }

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  // Separar headers y body
  $parts = explode("\r\n\r\n", $response, 2);
  $headers = $parts[0] ?? '';
  $body = $parts[1] ?? $response;

  return [
    'code' => $httpCode,
    'headers' => $headers,
    'body' => $body
  ];
}

// Función para extraer cookies de headers
function extractCookie($headers, $cookieName)
{
  if (preg_match("/Set-Cookie: {$cookieName}=([^;]+)/", $headers, $matches)) {
    return $matches[1];
  }
  return null;
}

echo "1. Probando login de staff...\n";
$loginData = [
  'email' => 'admin@bubblegum.agency',
  'password' => 'admin123'
];

$response = makeRequest("$baseUrl/auth/staff-login.php", $loginData);
echo "Status: " . $response['code'] . "\n";
echo "Body: " . $response['body'] . "\n";

$accessToken = extractCookie($response['headers'], 'access_token');
if ($accessToken) {
  echo "✓ Cookie access_token recibida: " . substr($accessToken, 0, 50) . "...\n\n";

  echo "2. Probando acceso a endpoint protegido con cookie...\n";
  $protectedResponse = makeRequest("$baseUrl/applications.php?candidate_id=1", null, "access_token=$accessToken");
  echo "Status: " . $protectedResponse['code'] . "\n";
  echo "Body: " . substr($protectedResponse['body'], 0, 200) . "...\n\n";

  echo "3. Probando logout...\n";
  $logoutResponse = makeRequest("$baseUrl/auth/logout.php", [], "access_token=$accessToken");
  echo "Status: " . $logoutResponse['code'] . "\n";
  echo "Body: " . $logoutResponse['body'] . "\n";

  // Verificar que la cookie se eliminó
  if (strpos($logoutResponse['headers'], 'Set-Cookie: access_token=;') !== false) {
    echo "✓ Cookie eliminada correctamente\n";
  }
} else {
  echo "✗ No se recibió la cookie access_token\n";
}

echo "\n=== FIN DEL TEST ===\n";
