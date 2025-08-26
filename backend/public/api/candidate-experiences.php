<?php

declare(strict_types=1);



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
  // Solo permitir mÃƒÂ©todo GET
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'MÃƒÂ©todo no permitido']);
    exit;
  }

  // Verificar sesiÃƒÂ³n activa
  if (empty($_SESSION['candidate_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No authenticated']);
    exit;
  }

  $candidateId = $_SESSION['candidate_id'];

  // Obtener parÃƒÂ¡metro candidate_id desde la URL (opcional para validaciÃƒÂ³n)
  $requestedCandidateId = $_GET['candidate_id'] ?? null;

  // Si se especifica un candidate_id, debe coincidir con la sesiÃƒÂ³n
  if ($requestedCandidateId && $requestedCandidateId !== $candidateId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
  }

  // Conectar a la base de datos
  $db = getDBConnection();

  // Obtener experiencias del candidato
  $stmt = $db->prepare("
    SELECT id, candidate_id, company, position, start_date, end_date, current, description, location 
    FROM bt_candidate_experiences 
    WHERE candidate_id = ? 
    ORDER BY COALESCE(end_date, start_date) DESC, id DESC
  ");
  $stmt->execute([$candidateId]);
  $experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Formatear datos para el frontend
  $formattedExperiences = array_map(function ($exp) {
    return [
      'id' => $exp['id'],
      'position' => $exp['position'],
      'company' => $exp['company'],
      'start_date' => $exp['start_date'],
      'end_date' => $exp['end_date'],
      'current' => (bool)$exp['current'],
      'description' => $exp['description'],
      'location' => $exp['location']
    ];
  }, $experiences);

  // Respuesta exitosa
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'data' => $formattedExperiences,
    'count' => count($formattedExperiences)
  ]);
} catch (Exception $e) {
  error_log("Error in candidate-experiences.php: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}


