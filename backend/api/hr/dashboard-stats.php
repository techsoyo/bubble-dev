<?php
require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Método no permitido']);
  exit();
}

try {
  $pdo = getDBConnection();

  // Obtener estadísticas generales
  $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM bt_candidates WHERE status = 'active') as total_candidates,
            (SELECT COUNT(*) FROM bt_jobs WHERE status = 'open') as active_jobs,
            (SELECT COUNT(*) FROM bt_applications) as total_applications,
            (SELECT COUNT(*) FROM bt_applications WHERE status = 'hired') as hired_count
    ");
  $stmt->execute();
  $stats = $stmt->fetch(PDO::FETCH_ASSOC);

  // Aplicaciones por estado
  $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM bt_applications 
        GROUP BY status
        ORDER BY count DESC
    ");
  $stmt->execute();
  $applicationsByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Aplicaciones por departamento
  $stmt = $pdo->prepare("
        SELECT 
            d.name as department_name,
            COUNT(*) as count
        FROM bt_applications a
        JOIN bt_jobs j ON a.job_id = j.id
        JOIN bt_departments d ON j.department_id = d.id
        GROUP BY d.name
        ORDER BY count DESC
    ");
  $stmt->execute();
  $applicationsByDepartment = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Aplicaciones recientes (últimos 7 días)
  $stmt = $pdo->prepare("
        SELECT 
            a.*,
            c.name as candidate_name,
            j.title as job_title
        FROM bt_applications a
        JOIN bt_candidates c ON a.candidate_id = c.id
        JOIN bt_jobs j ON a.job_id = j.id
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
  $stmt->execute();
  $recentApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Métricas de rendimiento
  $conversionRate = $stats['total_applications'] > 0 ?
    round(($stats['hired_count'] / $stats['total_applications']) * 100, 2) : 0;

  $response = [
    'success' => true,
    'data' => [
      'totalCandidates' => (int)$stats['total_candidates'],
      'activeJobs' => (int)$stats['active_jobs'],
      'totalApplications' => (int)$stats['total_applications'],
      'hiredCount' => (int)$stats['hired_count'],
      'conversionRate' => $conversionRate,
      'applicationsByStatus' => $applicationsByStatus,
      'applicationsByDepartment' => $applicationsByDepartment,
      'recentApplications' => $recentApplications
    ]
  ];

  echo json_encode($response);
} catch (Exception $e) {
  error_log("Error en HR dashboard stats: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Error interno del servidor',
    'details' => $e->getMessage()
  ]);
}
