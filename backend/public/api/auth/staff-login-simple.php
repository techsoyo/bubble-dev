<?php

declare(strict_types=1);
$ROOT = dirname(dirname(dirname(dirname(__DIR__)))); // Corregido: auth -> api -> public -> backend -> raiz
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Bloquear en producción: endpoint solo para entornos de desarrollo
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

try {
  // Leer datos de entrada
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input || !isset($input['action']) || $input['action'] !== 'staff_login') {
    throw new Exception('Acción no válida');
  }

  $email = trim($input['email'] ?? '');
  $password = trim($input['password'] ?? '');

  // Validaciones básicas
  if (empty($email) || empty($password)) {
    throw new Exception('Email y contraseña son requeridos');
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email no válido');
  }

  // Verificar que sea email corporativo
  if (!str_ends_with($email, '@bubblegum.agency')) {
    throw new Exception('Solo se permite acceso con email corporativo @bubblegum.agency');
  }

  // Conectar a base de datos directamente
  $host = '192.168.1.40';
  $dbname = 'bubble_talents_DB';
  $username = 'user';
  $password_db = 'user123';

  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password_db);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Buscar usuario staff
  $stmt = $pdo->prepare("
        SELECT id, email, role, password_hash, active, name 
        FROM bt_staff_profiles 
        WHERE email = ? AND active = 1
    ");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    throw new Exception('Usuario no encontrado o inactivo');
  }

  // Verificar contraseña
  if (!password_verify($password, $user['password_hash'])) {
    throw new Exception('Credenciales incorrectas');
  }

  // Respuesta exitosa (simplificada sin JWT por ahora)
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
    'token' => 'token_' . time() // Token temporal
  ]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}

