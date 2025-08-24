<?php

declare(strict_types=1);
require_once $BOOT;

use Utils\Database;
use Utils\Logger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Configurar headers de seguridad

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'MÃ©todo no permitido']);
  exit;
}

try {
  // Leer datos de entrada
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input || !isset($input['action']) || $input['action'] !== 'staff_login') {
    throw new Exception('AcciÃ³n no vÃ¡lida');
  }

  $email = trim($input['email'] ?? '');
  $password = trim($input['password'] ?? '');

  // Validaciones bÃ¡sicas
  if (empty($email) || empty($password)) {
    throw new Exception('Email y contraseÃ±a son requeridos');
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email no vÃ¡lido');
  }

  // Verificar que sea email corporativo
  if (substr($email, -strlen('@bubblegum.agency')) !== '@bubblegum.agency') {
    throw new Exception('Solo se permite acceso con email corporativo @bubblegum.agency');
  }

  // Conectar a base de datos
  $db = getDbConnection();

  // Buscar usuario staff
  $stmt = $db->prepare("
        SELECT id, email, role, password_hash, active, name 
        FROM bt_staff_profiles 
        WHERE email = ? AND active = 1
    ");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    throw new Exception('Usuario no encontrado o inactivo');
  }

  // Verificar contraseÃ±a
  if (!password_verify($password, $user['password_hash'])) {
    throw new Exception('Credenciales incorrectas');
  }

  $payload = [
    'user_id' => $user['id'],
    'email' => $user['email'],
    'role' => $user['role'],
    'user_type' => 'staff',
    'iat' => time(),
    'exp' => time() + (24 * 60 * 60) // 24 horas
  ];

  // Usa tu clave secreta JWT (ajusta la ruta o variable segÃºn tu configuraciÃ³n)
  $jwt_secret = $_ENV['JWT_SECRET'] ?? 'tu_clave_secreta_super_segura';
  $token = JWT::encode($payload, $jwt_secret, 'HS256');

  // Crear sesiÃ³n en BD
  $session_token = bin2hex(random_bytes(32));
  $session_id = bin2hex(random_bytes(16));

  $stmt = $db->prepare("
        INSERT INTO bt_staff_sessions (id, staff_id, token, expires_at, created_at) 
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
    ");
  $stmt->execute([$session_id, $user['id'], $session_token]);

  // Log del login exitoso
  $logger = new Logger();
  $logger->info("Staff login exitoso: {$email} (rol: {$user['role']})");

  // Respuesta exitosa
  echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'user' => [
      'id' => $user['id'],
      'email' => $user['email'],
      'role' => $user['role'],
      'name' => $user['name'] ?: $user['email'],
      'user_type' => 'staff'
    ],
    'token' => $token,
    'session_token' => $session_token
  ]);
} catch (Exception $e) {
  // Log del error
  error_log("Error en staff login: " . $e->getMessage());

  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}

