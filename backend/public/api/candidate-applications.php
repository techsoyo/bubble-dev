<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

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

// Configurar headers CORS seguros - NO USAR HTTP_ORIGIN
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Credentials: false'); // ✅ FIXED: Deshabilitado por seguridad
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

try {
  // Obtener y validar candidate_id del query parameter
  $requestedCandidateId = $_GET['candidate_id'] ?? null;

  if (!$requestedCandidateId) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'candidate_id es requerido',
      'error_code' => 'MISSING_CANDIDATE_ID'
    ]);
    exit;
  }

  // Validar que candidate_id sea un número entero
  $requestedCandidateId = filter_var($requestedCandidateId, FILTER_VALIDATE_INT);
  if ($requestedCandidateId === false || $requestedCandidateId <= 0) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'candidate_id debe ser un número entero válido',
      'error_code' => 'INVALID_CANDIDATE_ID'
    ]);
    exit;
  }

  // CONTROL DE ACCESO: Solo el propio candidato o admin/hr pueden ver aplicaciones
  $currentUserId = $userId;

  if ($userRole === 'candidate' && $currentUserId != $requestedCandidateId) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'message' => 'Solo puedes ver tus propias aplicaciones',
      'error_code' => 'INSUFFICIENT_PERMISSIONS'
    ]);
    exit;
  }

  if (!in_array($userRole, ['candidate', 'admin', 'hr', 'recruiter'], true)) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'message' => 'Permisos insuficientes',
      'error_code' => 'INSUFFICIENT_PERMISSIONS'
    ]);
    exit;
  }

  $db = getDbConnection();

  // QUERY SEGURA CON PREPARED STATEMENTS
  $sql = "
        SELECT
            a.id as application_id,
            a.job_id,
            a.candidate_id,
            a.status,
            a.score,
            a.created_at as applied_date,
            a.updated_at,
            a.cover_letter,
            j.title as job_title,
            j.description as job_description,
            j.location as job_location,
            j.job_type,
            j.department,
            j.experience_level,
            j.salary_range,
            j.company_name,
            j.status as job_status,
            j.deadline
        FROM bt_applications a
        LEFT JOIN bt_jobs j ON a.job_id = j.id
        WHERE a.candidate_id = ?
        ORDER BY a.created_at DESC
    ";

  $stmt = $db->prepare($sql);
  $stmt->execute([$requestedCandidateId]);
  $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // FORMATEAR Y LIMPIAR DATOS
  foreach ($applications as &$app) {
    // Formatear fechas
    if ($app['applied_date']) {
      $app['applied_date'] = date('Y-m-d H:i:s', strtotime($app['applied_date']));
    }
    if ($app['updated_at']) {
      $app['updated_at'] = date('Y-m-d H:i:s', strtotime($app['updated_at']));
    }

    // Limpiar campos nulos
    $app['score'] = $app['score'] ?? 0;
    $app['cover_letter'] = $app['cover_letter'] ?? '';

    // Agregar información adicional
    $app['can_withdraw'] = in_array($app['status'], ['pending', 'in_review'], true);
  }

  echo json_encode([
    'success' => true,
    'message' => 'Aplicaciones del candidato obtenidas exitosamente',
    'data' => [
      'applications' => $applications,
      'candidate_id' => $requestedCandidateId,
      'total_count' => count($applications),
      'status_summary' => [
        'pending' => count(array_filter($applications, fn($a) => $a['status'] === 'pending')),
        'in_review' => count(array_filter($applications, fn($a) => $a['status'] === 'in_review')),
        'accepted' => count(array_filter($applications, fn($a) => $a['status'] === 'accepted')),
        'rejected' => count(array_filter($applications, fn($a) => $a['status'] === 'rejected'))
      ]
    ]
  ]);
} catch (Exception $e) {
  error_log("CANDIDATE APPLICATIONS ERROR: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor',
    'error_code' => 'INTERNAL_ERROR'
  ]);
}
