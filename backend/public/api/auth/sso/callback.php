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
// auth/sso/callback.php
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oidc_state']) {
  http_response_code(400);
  exit('Estado inválido');
}

$client_id = 'TU_CLIENT_ID';
$client_secret = 'TU_CLIENT_SECRET';
// $redirect_uri = 'https://bubblegum.agency/backend/auth/sso/callback.php';
$redirect_uri = 'https://localhost/backend/auth/sso/callback.php';

// Intercambia el "code" por un token
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
  'code' => $_GET['code'],
  'client_id' => $client_id,
  'client_secret' => $client_secret,
  'redirect_uri' => $redirect_uri,
  'grant_type' => 'authorization_code'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$id_token = $data['id_token'] ?? null;
if (!$id_token) {
  http_response_code(400);
  exit('No se recibió ID token');
}

// Decodifica el ID Token
$payload = json_decode(base64_decode(explode('.', $id_token)[1]), true);

// Verificar dominio corporativo
if (!str_ends_with($payload['email'], '@xxagencia.agency')) {
  http_response_code(403);
  exit('No autorizado');
}

// Consultar en la BD si existe y tiene rol válido
require_once __DIR__ . '/../../config/db.php'; // tu conexión
$stmt = $pdo->prepare("SELECT id, role FROM bt_staff_profiles WHERE email = ? AND active = 1");
$stmt->execute([$payload['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  http_response_code(403);
  exit('Usuario no encontrado o inactivo');
}


// Crear token de sesión para API
$session_token = bin2hex(random_bytes(32));
$stmt = $pdo->prepare("INSERT INTO bt_staff_sessions (id, staff_user_id, refresh_jti, expires_at, created_at) VALUES (UUID(), ?, ?, DATE_ADD(NOW(), INTERVAL 8 HOUR), NOW())");
$stmt->execute([$user['id'], $session_token]);

// Guardar token en cookie segura
setcookie('__Host-admin.sid', $session_token, [
  'expires' => time() + 8 * 3600,
  'path' => '/dashboard/',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Lax'
]);

// Redirigir según el rol
if ($user['role'] === 'hr') {
  header('Location: https://bubblegum.agency/dashboard/hrdashboard');
} elseif ($user['role'] === 'recruiter') {
  header('Location: https://bubblegum.agency/dashboard/recruiterdashboard');
} else {
  http_response_code(403);
  exit('Rol no autorizado');
}

exit;
