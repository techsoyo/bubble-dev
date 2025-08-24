<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';


// Headers de seguridad
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

// Ã¢Å“â€¦ REQUERIR AUTENTICACIÃƒâ€œN JWT SIEMPRE
$userPayload = JWTMiddleware::requireAuth();
if (!$userPayload) {
  // JWTMiddleware ya enviÃƒÂ³ la respuesta de error
  exit;
}

try {
  $db = getDbConnection();

  // Obtener candidate_id del query parameter
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

  // Ã¢Å“â€¦ CONTROL DE ACCESO: Solo el propio candidato o admin/hr pueden ver aplicaciones
  $userRole = $userPayload['role'] ?? 'candidate';
  $currentUserId = $userPayload['user_id'];

  if ($userRole === 'candidate' && $currentUserId !== $requestedCandidateId) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'message' => 'Solo puedes ver tus propias aplicaciones',
      'error_code' => 'INSUFFICIENT_PERMISSIONS'
    ]);
    exit;
  } elseif (!in_array($userRole, ['candidate', 'admin', 'hr', 'recruiter'])) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'message' => 'Permisos insuficientes',
      'error_code' => 'INSUFFICIENT_PERMISSIONS'
    ]);
    exit;
  }

  // Ã¢Å“â€¦ QUERY SEGURA CON PREPARED STATEMENTS
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

  // Ã¢Å“â€¦ FORMATEAR Y LIMPIAR DATOS
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

    // AÃƒÂ±adir informaciÃƒÂ³n adicional
    $app['can_withdraw'] = in_array($app['status'], ['pending', 'in_review']);
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

