<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';



// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Agregar logging para debug
error_log("Change password endpoint called with method: " . $_SERVER['REQUEST_METHOD']);

try {
  // Solo permitir mÃƒÂ©todo POST
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'MÃƒÂ©todo no permitido']);
    exit;
  }

  // Obtener datos del request
  $rawInput = file_get_contents('php://input');
  error_log("Raw input received: " . $rawInput);

  $input = json_decode($rawInput, true);

  if (!$input) {
    error_log("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos invÃƒÂ¡lidos: ' . json_last_error_msg()]);
    exit;
  }

  error_log("Decoded input: " . print_r($input, true));  // Validar campos requeridos
  $required_fields = ['candidate_id', 'current_password', 'new_password', 'new_password_confirmation'];
  foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => "Campo requerido: {$field}"]);
      exit;
    }
  }

  $candidateId = trim($input['candidate_id']);
  $currentPassword = $input['current_password'];
  $newPassword = $input['new_password'];
  $newPasswordConfirmation = $input['new_password_confirmation'];

  // Validar que las nuevas contraseÃƒÂ±as coincidan
  if ($newPassword !== $newPasswordConfirmation) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Las contraseÃƒÂ±as nuevas no coinciden']);
    exit;
  }

  // Validar longitud mÃƒÂ­nima de la nueva contraseÃƒÂ±a
  if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nueva contraseÃƒÂ±a debe tener al menos 6 caracteres']);
    exit;
  }

  // Conectar a la base de datos
  $db = getDBConnection();

  // Obtener el candidato actual
  $stmt = $db->prepare("SELECT id, password_hash, first_name, last_name, email FROM bt_candidates WHERE id = ?");
  $stmt->execute([$candidateId]);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$candidate) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Candidato no encontrado']);
    exit;
  }

  // Verificar contraseÃƒÂ±a actual
  if (!password_verify($currentPassword, $candidate['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseÃƒÂ±a actual es incorrecta']);
    exit;
  }

  // Generar hash para la nueva contraseÃƒÂ±a
  $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

  // Actualizar la contraseÃƒÂ±a
  $updateStmt = $db->prepare("UPDATE bt_candidates SET password_hash = ? WHERE id = ?");
  $result = $updateStmt->execute([$newPasswordHash, $candidateId]);

  if ($result) {
    echo json_encode([
      'success' => true,
      'message' => 'ContraseÃƒÂ±a actualizada correctamente',
      'data' => [
        'candidate_id' => $candidateId,
        'candidate_name' => $candidate['first_name'] . ' ' . $candidate['last_name'],
        'updated_at' => date('Y-m-d H:i:s')
      ]
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseÃƒÂ±a']);
  }
} catch (Exception $e) {
  error_log("Error en change-password.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}


