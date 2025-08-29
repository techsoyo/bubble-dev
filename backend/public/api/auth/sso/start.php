<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
// NO \Middleware\JWTMiddleware::requireAuth() aquí - endpoint público (start SSO)
// NO \Middleware\CsrfMiddleware::protect() aquí - inicia flujo SSO (no modifica estado aún)

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Configurar headers seguros
header('Content-Type: application/json; charset=utf-8');

// Generar estado único para prevenir CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['oidc_state'] = $state;

// Configuración SSO (debe venir de variables de entorno)
$client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? 'TU_CLIENT_ID';
$redirect_uri = $_ENV['GOOGLE_REDIRECT_URI'] ?? 'https://localhost/backend/auth/sso/callback.php';
$scope = 'openid email profile';

// Validar configuración
if ($client_id === 'TU_CLIENT_ID') {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Configuración SSO incompleta',
    'message' => 'Google Client ID no configurado',
    'error_code' => 'SSO_CONFIG_ERROR'
  ]);
  exit;
}

// Redirige a Google (con restricción de dominio si está configurado)
$authParams = [
  'client_id' => $client_id,
  'redirect_uri' => $redirect_uri,
  'response_type' => 'code',
  'scope' => $scope,
  'state' => $state
];

// Agregar restricción de dominio si está configurada
$domainRestriction = $_ENV['GOOGLE_HD_DOMAIN'] ?? null;
if ($domainRestriction) {
  $authParams['hd'] = $domainRestriction;
}

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($authParams);

// Log de seguridad (no incluir datos sensibles)
error_log("SSO Start: Redirecting to Google OAuth - State: " . substr($state, 0, 8) . "...");

header('Location: ' . $authUrl);
exit;
