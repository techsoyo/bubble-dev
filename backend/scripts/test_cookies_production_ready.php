<?php

/**
 * Script de prueba completo para cookies httpOnly production-ready
 * 
 * Prueba:
 * 1. Detección de HTTPS detrás de proxies
 * 2. Configuración de cookies desde .env
 * 3. Login con cookies httpOnly
 * 4. Acceso a endpoints protegidos
 * 5. Logout limpiando cookies
 */

require_once __DIR__ . '/../src/Security/Cookies.php';

use Security\Cookies;

echo "=== TEST COMPLETO DE COOKIES HTTPONLY (PRODUCTION-READY) ===\n\n";

// Simular diferentes escenarios de HTTPS
echo "1. Testing detección de HTTPS...\n";

// Simular diferentes headers de proxy
$httpsScenarios = [
  'Sin HTTPS' => [],
  'HTTPS directo' => ['HTTPS' => 'on'],
  'Detrás de Cloudflare' => ['HTTP_X_FORWARDED_PROTO' => 'https'],
  'Detrás de AWS ALB' => ['HTTP_X_FORWARDED_SSL' => 'on'],
  'Puerto 443' => ['SERVER_PORT' => '443'],
];

foreach ($httpsScenarios as $scenario => $serverVars) {
  // Backup y restaurar $_SERVER
  $originalServer = $_SERVER;

  foreach ($serverVars as $key => $value) {
    $_SERVER[$key] = $value;
  }

  $isHttps = Cookies::isHttps();
  echo "   $scenario: " . ($isHttps ? '✓ HTTPS detectado' : '✗ HTTP detectado') . "\n";

  // Restaurar $_SERVER
  $_SERVER = $originalServer;
}

echo "\n2. Testing configuración de cookies...\n";

// Test de opciones de cookie por defecto
$options = Cookies::options();
echo "   Opciones por defecto:\n";
foreach ($options as $key => $value) {
  $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
  echo "     $key: $displayValue\n";
}

// Test de opciones personalizadas
echo "\n   Opciones personalizadas (expires en 1 hora):\n";
$customOptions = Cookies::options(['expires' => time() + 3600]);
foreach ($customOptions as $key => $value) {
  $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
  echo "     $key: $displayValue\n";
}

echo "\n3. Testing funciones de cookie JWT...\n";

// Simular cookie existente
$_COOKIE['access_token'] = 'test_jwt_token_123';

echo "   hasJwt(): " . (Cookies::hasJwt() ? '✓ Detecta JWT' : '✗ No detecta JWT') . "\n";
echo "   getJwt(): '" . (Cookies::getJwt() ?? 'null') . "'\n";

// Limpiar cookie de prueba
unset($_COOKIE['access_token']);

echo "   hasJwt() después de limpiar: " . (Cookies::hasJwt() ? '✗ Detecta JWT' : '✓ No detecta JWT') . "\n";

echo "\n4. Testing endpoints reales...\n";

// URL base - ajustar según configuración
$baseUrl = 'http://localhost/bubble_of_talents_1.0/backend/public/api';

// Función para hacer requests con cookies
function makeRequest($url, $data = null, $cookies = '')
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_COOKIE, $cookies);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);

  if ($data) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json'
    ]);
  }

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    return ['error' => $error, 'code' => 0];
  }

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

echo "   Probando login de staff...\n";
$loginData = [
  'email' => 'admin@bubblegum.agency',
  'password' => 'admin123'
];

$response = makeRequest("$baseUrl/auth/staff-login.php", $loginData);

if (isset($response['error'])) {
  echo "   ✗ Error de conexión: " . $response['error'] . "\n";
} else {
  echo "   Status: " . $response['code'] . "\n";

  if ($response['code'] === 200) {
    $accessToken = extractCookie($response['headers'], 'access_token');
    if ($accessToken) {
      echo "   ✓ Cookie access_token recibida: " . substr($accessToken, 0, 50) . "...\n";

      echo "\n   Probando endpoint protegido con cookie...\n";
      $protectedResponse = makeRequest("$baseUrl/applications.php?candidate_id=1", null, "access_token=$accessToken");
      echo "   Status endpoint protegido: " . $protectedResponse['code'] . "\n";

      if ($protectedResponse['code'] === 200) {
        echo "   ✓ Acceso autorizado con cookie\n";
      } else {
        echo "   ✗ Acceso denegado o error\n";
      }

      echo "\n   Probando logout...\n";
      $logoutResponse = makeRequest("$baseUrl/auth/logout.php", [], "access_token=$accessToken");
      echo "   Status logout: " . $logoutResponse['code'] . "\n";

      if (strpos($logoutResponse['headers'], 'Set-Cookie: access_token=;') !== false) {
        echo "   ✓ Cookie eliminada correctamente\n";
      } else {
        echo "   ✗ Cookie no eliminada correctamente\n";
      }
    } else {
      echo "   ✗ No se recibió la cookie access_token\n";
    }
  } else {
    echo "   ✗ Login falló: " . $response['body'] . "\n";
  }
}

echo "\n=== RESUMEN DE CONFIGURACIÓN RECOMENDADA ===\n";
echo "Variables .env para DESARROLLO:\n";
echo "COOKIE_DOMAIN=                    # Vacío para localhost\n";
echo "COOKIE_SAMESITE=Lax              # Lax para desarrollo\n";
echo "COOKIE_SECURE=false              # false para HTTP\n\n";

echo "Variables .env para PRODUCCIÓN:\n";
echo "COOKIE_DOMAIN=.tudominio.com     # Tu dominio principal\n";
echo "COOKIE_SAMESITE=Strict           # Strict para máxima seguridad\n";
echo "COOKIE_SECURE=true               # true para HTTPS obligatorio\n";
echo "HTTPS=true                       # Forzar detección HTTPS\n\n";

echo "=== FIN DEL TEST COMPLETO ===\n";
