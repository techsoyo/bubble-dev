<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__, 2);             // sso -> auth -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
$client_id = 'TU_CLIENT_ID';
// $redirect_uri = 'https://bubblegum.agency/backend/auth/sso/callback.php';
$redirect_uri = 'https://localhost/backend/auth/sso/callback.php';
$scope = 'openid email profile';
$state = bin2hex(random_bytes(16));
$_SESSION['oidc_state'] = $state;

// Redirige a Google (con restricción de dominio)
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
  'client_id' => $client_id,
  'redirect_uri' => $redirect_uri,
  'response_type' => 'code',
  'scope' => $scope,
  'state' => $state,
  'hd' => 'bubblegum.agency'
]));
exit;
