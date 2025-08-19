<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3002');
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
  $db = getDbConnection();

  // Consultar reclutadores reales de la tabla bt_staff_profiles
  $stmt = $db->prepare("
    SELECT 
      id,
      name,
      email,
      role,
      department_id,
      department,
      active,
      created_at,
      hire_date,
      status
    FROM bt_staff_profiles
    WHERE role = 'recruiter' 
      AND active = 1
    ORDER BY name ASC
  ");

  $stmt->execute();
  $recruiters = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Si no hay reclutadores en la BD, retornar array vacío con mensaje
  if (empty($recruiters)) {
    echo json_encode([
      'success' => true,
      'data' => [],
      'count' => 0,
      'message' => 'No hay reclutadores activos en el sistema'
    ]);
    exit();
  }

  // Mapeo manual de departamentos a IDs para compatibilidad
  $departmentMapping = [
    'Sales' => 2,
    'Marketing' => 4,
    'Finance' => 6,
    'HR' => 6,
    'Engineering' => 2
  ];

  // Formatear los datos para el frontend
  $formattedRecruiters = array_map(function ($recruiter) use ($departmentMapping) {
    $departmentId = $recruiter['department_id'] ?? $departmentMapping[$recruiter['department']] ?? 6;

    return [
      'id' => $recruiter['id'],
      'name' => $recruiter['name'],
      'email' => $recruiter['email'],
      'department' => $recruiter['department'] ?? 'Sin departamento',
      'departmentId' => (int)$departmentId,
      'role' => $recruiter['role'],
      'active_jobs' => 0,
      'total_applications' => 0,
      'status' => $recruiter['active'] ? 'active' : 'inactive',
      'hire_date' => $recruiter['hire_date'],
      'created_at' => $recruiter['created_at']
    ];
  }, $recruiters);

  echo json_encode([
    'success' => true,
    'data' => $formattedRecruiters,
    'count' => count($formattedRecruiters)
  ]);
} catch (Exception $e) {
  error_log("Error en recruiters endpoint: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Error interno del servidor',
    'details' => $e->getMessage()
  ]);
}
