<?php

declare(strict_types=1);
$ROOT = dirname(dirname(dirname(__DIR__))); // auth -> api -> public -> backend  
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Permitir tanto GET como POST para verificación de sesión
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

try {
  // Verificar si hay sesión activa
  if (empty($_SESSION['candidate_id'])) {
    http_response_code(200);
    echo json_encode([
      'success' => false,
      'message' => 'No active session'
    ]);
    exit;
  }

  // Obtener datos del candidato desde la base de datos
  $db = getDBConnection();
  $stmt = $db->prepare("SELECT id, first_name, last_name, email FROM bt_candidates WHERE id = ?");
  $stmt->execute([$_SESSION['candidate_id']]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    // Limpiar sesión si el candidato no existe
    session_destroy();
    http_response_code(200);
    echo json_encode([
      'success' => false,
      'message' => 'User not found'
    ]);
    exit;
  }

  // Devolver datos del usuario
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'user' => [
      'id' => $candidate['id'],
      'email' => $candidate['email'],
      'name' => $candidate['first_name'] . ' ' . $candidate['last_name'],
      'role' => 'candidate'
    ]
  ]);
} catch (Exception $e) {
  error_log("Error in verify-session.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Internal server error'
  ]);
}
