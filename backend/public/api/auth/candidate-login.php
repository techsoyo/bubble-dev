<?php

declare(strict_types=1);

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

try {
  // Solo permitir mÃƒÂ©todo POST
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'MÃƒÂ©todo no permitido']);
    exit;
  }

  // Obtener datos del request
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos invÃƒÂ¡lidos']);
    exit;
  }

  $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
  $password = $input['password'] ?? '';

  if (!$email || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email y contraseÃƒÂ±a son requeridos']);
    exit;
  }

  // Conectar a la base de datos
  $db = getDBConnection();

  // Buscar candidato por email
  $stmt = $db->prepare("SELECT id, first_name, last_name, email, password_hash FROM bt_candidates WHERE email = ? AND status = 'active'");
  $stmt->execute([$email]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Credenciales invÃƒÂ¡lidas']);
    exit;
  }

  // Verificar contraseÃƒÂ±a
  if (!password_verify($password, $candidate['password_hash'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Credenciales invÃƒÂ¡lidas']);
    exit;
  }

  // Crear sesiÃƒÂ³n
  $_SESSION['candidate_id'] = $candidate['id'];
  $_SESSION['candidate_email'] = $candidate['email'];
  $_SESSION['candidate_name'] = $candidate['first_name'] . ' ' . $candidate['last_name'];

  // Respuesta exitosa
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'user' => [
      'id' => $candidate['id'],
      'email' => $candidate['email'],
      'name' => $candidate['first_name'] . ' ' . $candidate['last_name'],
      'role' => 'candidate'
    ],
    'message' => 'Login exitoso'
  ]);
} catch (Exception $e) {
  error_log("Error in candidate-login.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

