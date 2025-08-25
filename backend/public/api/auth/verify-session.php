<?php

declare(strict_types=1);


require_once __DIR__ . '/../bootstrap.php';
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

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
  }
}

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Permitir tanto GET como POST para verificaciÃƒÆ’Ã‚Â³n de sesiÃƒÆ’Ã‚Â³n
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'MÃƒÆ’Ã‚Â©todo no permitido']);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

try {
  // Verificar si hay sesiÃƒÆ’Ã‚Â³n activa
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
    // Limpiar sesiÃƒÆ’Ã‚Â³n si el candidato no existe
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

