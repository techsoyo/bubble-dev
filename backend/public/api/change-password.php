<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

/**
 * Endpoint: /api/change-password
 * Maneja cambio de contraseña de forma segura
 *
 * @package API
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */

// Configurar headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Configurar CORS seguro
$allowedOrigins = [
  'https://bubble-talents.com',
  'https://www.bubble-talents.com',
  'https://app.bubble-talents.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Methods: POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
  header('Access-Control-Max-Age: 86400');
}

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Autenticación requerida - obtener payload del usuario
$userPayload = \Middleware\JWTMiddleware::requireAuth();
if (!$userPayload) {
  exit; // El middleware ya maneja la respuesta de error
}

$userId = (int)$userPayload['user_id'];
$userRole = $userPayload['role'] ?? 'candidate';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  \Middleware\CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized (cookie required)']);
  exit;
}

try {
  // Solo permitir método POST
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
  }

  // Obtener y validar datos del request
  $rawInput = file_get_contents('php://input');
  $input = json_decode($rawInput, true);

  if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
  }

  // Validar campos requeridos
  $required_fields = ['current_password', 'new_password', 'new_password_confirmation'];
  foreach ($required_fields as $field) {
    if (!isset($input[$field]) || !is_string($input[$field]) || empty(trim($input[$field]))) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => "Campo requerido: {$field}"]);
      exit;
    }
  }

  $currentPassword = trim($input['current_password']);
  $newPassword = trim($input['new_password']);
  $newPasswordConfirmation = trim($input['new_password_confirmation']);

  // Validar que las nuevas contraseñas coincidan
  if ($newPassword !== $newPasswordConfirmation) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Las contraseñas nuevas no coinciden']);
    exit;
  }

  // Validar fortaleza de la nueva contraseña
  if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres']);
    exit;
  }

  // Validar complejidad de la contraseña
  if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'La contraseña debe contener al menos una letra minúscula, una mayúscula y un número'
    ]);
    exit;
  }

  // Conectar a la base de datos
  $db = getDBConnection();

  // Obtener el usuario actual con prepared statement
  $stmt = $db->prepare("SELECT id, password_hash, first_name, last_name, email, status FROM bt_candidates WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
  }

  // Verificar que el usuario esté activo
  if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Usuario inactivo']);
    exit;
  }

  // Verificar contraseña actual
  if (!password_verify($currentPassword, $user['password_hash'])) {
    // Log intento fallido por seguridad
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('password_change_failed', [
        'user_id' => $userId,
        'reason' => 'wrong_current_password',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
    exit;
  }

  // Verificar que la nueva contraseña no sea igual a la actual
  if (password_verify($newPassword, $user['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña no puede ser igual a la actual']);
    exit;
  }

  // Generar hash para la nueva contraseña
  $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

  // Actualizar la contraseña
  $updateStmt = $db->prepare("UPDATE bt_candidates SET password_hash = ?, updated_at = NOW() WHERE id = ?");
  $result = $updateStmt->execute([$newPasswordHash, $userId]);

  if ($result) {
    // Log cambio exitoso de contraseña
    if (class_exists('\Utils\Logger')) {
      \Utils\Logger::security('password_changed', [
        'user_id' => $userId,
        'user_email' => $user['email'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      ]);
    }

    echo json_encode([
      'success' => true,
      'message' => 'Contraseña actualizada correctamente',
      'data' => [
        'user_id' => $userId,
        'user_name' => $user['first_name'] . ' ' . $user['last_name'],
        'updated_at' => date('Y-m-d H:i:s')
      ]
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseña']);
  }
} catch (Exception $e) {
  error_log("Error en change-password.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
