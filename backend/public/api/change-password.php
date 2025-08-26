<?php


require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
declare(strict_types=1);

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Agregar logging para debug
error_log("Change password endpoint called with method: " . $_SERVER['REQUEST_METHOD']);

try {
  // Solo permitir mÃƒÆ’Ã‚Â©todo POST
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'MÃƒÆ’Ã‚Â©todo no permitido']);
    exit;
  }

  // Obtener datos del request
  $rawInput = file_get_contents('php://input');
  error_log("Raw input received: " . $rawInput);

  $input = json_decode($rawInput, true);

  if (!$input) {
    error_log("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos invÃƒÆ’Ã‚Â¡lidos: ' . json_last_error_msg()]);
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

  // Validar que las nuevas contraseÃƒÆ’Ã‚Â±as coincidan
  if ($newPassword !== $newPasswordConfirmation) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Las contraseÃƒÆ’Ã‚Â±as nuevas no coinciden']);
    exit;
  }

  // Validar longitud mÃƒÆ’Ã‚Â­nima de la nueva contraseÃƒÆ’Ã‚Â±a
  if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nueva contraseÃƒÆ’Ã‚Â±a debe tener al menos 6 caracteres']);
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

  // Verificar contraseÃƒÆ’Ã‚Â±a actual
  if (!password_verify($currentPassword, $candidate['password_hash'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseÃƒÆ’Ã‚Â±a actual es incorrecta']);
    exit;
  }

  // Generar hash para la nueva contraseÃƒÆ’Ã‚Â±a
  $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

  // Actualizar la contraseÃƒÆ’Ã‚Â±a
  $updateStmt = $db->prepare("UPDATE bt_candidates SET password_hash = ? WHERE id = ?");
  $result = $updateStmt->execute([$newPasswordHash, $candidateId]);

  if ($result) {
    echo json_encode([
      'success' => true,
      'message' => 'ContraseÃƒÆ’Ã‚Â±a actualizada correctamente',
      'data' => [
        'candidate_id' => $candidateId,
        'candidate_name' => $candidate['first_name'] . ' ' . $candidate['last_name'],
        'updated_at' => date('Y-m-d H:i:s')
      ]
    ]);
  } else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la contraseÃƒÆ’Ã‚Â±a']);
  }
} catch (Exception $e) {
  error_log("Error en change-password.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}



