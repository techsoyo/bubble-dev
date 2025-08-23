<?php

/**
 * GET /api/candidate-applications/{candidate_id}
 * Obtiene las aplicaciones de un candidato específico
 */

require_once __DIR__ . '/bootstrap.php';

// Content Type header (CORS ya configurado en bootstrap.php via api/bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  $pdo = getDBConnection();

  // Obtener candidate_id de la URL
  $candidateId = $_GET['candidate_id'] ?? null;

  if (!$candidateId) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'message' => 'candidate_id es requerido',
      'data' => null
    ]);
    exit;
  }

  // Consulta para obtener aplicaciones con detalles del trabajo
  $sql = "
        SELECT 
            a.id as application_id,
            a.job_id,
            a.candidate_id,
            a.status,
            a.score,
            a.created_at as applied_date,
            a.updated_at,
            a.resume,
            a.cover_letter,
            a.insights,
            a.source,
            j.title as job_title,
            j.description as job_description,
            j.location as job_location,
            j.type as job_type,
            j.category as job_category,
            j.level as job_level,
            j.salary_min,
            j.salary_max,
            j.salary_currency,
            j.salary_period,
            j.company_name,
            j.status as job_status
        FROM bt_applications a
        LEFT JOIN bt_jobs j ON a.job_id = j.id
        WHERE a.candidate_id = ?
        ORDER BY a.created_at DESC
    ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$candidateId]);
  $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Procesar datos adicionales si es necesario
  foreach ($applications as &$app) {
    // Formatear fecha de aplicación
    if ($app['applied_date']) {
      $app['applied_date'] = date('Y-m-d', strtotime($app['applied_date']));
    }

    // Construir rango de salario
    if ($app['salary_min'] && $app['salary_max']) {
      $app['salary_range'] = $app['salary_min'] . ' - ' . $app['salary_max'] . ' ' . ($app['salary_currency'] ?: 'EUR');
    } elseif ($app['salary_min']) {
      $app['salary_range'] = 'Desde ' . $app['salary_min'] . ' ' . ($app['salary_currency'] ?: 'EUR');
    } else {
      $app['salary_range'] = 'A negociar';
    }
  }
  echo json_encode([
    'success' => true,
    'message' => 'Aplicaciones obtenidas exitosamente',
    'data' => $applications,
    'count' => count($applications)
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor: ' . $e->getMessage(),
    'data' => null
  ]);
}
