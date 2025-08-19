<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');
// SECURITY: Restrict origins for production instead of wildcard
$allowedOrigins = ['http://localhost:3002', 'http://localhost:3000', 'http://127.0.0.1:3002'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
} else {
  header('Access-Control-Allow-Origin: http://localhost:3002'); // Default fallback
}
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

  $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.location,
            c.status,
            c.department_id,
            c.department_category_id,
            d.name as department_name,
            dc.name as department_category_name,
            GROUP_CONCAT(DISTINCT cs.skill) as skills
        FROM bt_candidates c
        LEFT JOIN bt_departments d ON c.department_id = d.id
        LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
        LEFT JOIN bt_candidate_skills cs ON c.id = cs.candidate_id
        WHERE c.status = 'active'
        GROUP BY c.id, c.name, c.first_name, c.last_name, c.email, c.phone, c.location, c.status, c.department_id, c.department_category_id, d.name, dc.name
        ORDER BY c.name ASC
    ");

  $stmt->execute();
  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Procesar las skills para convertirlas a array
  foreach ($candidates as &$candidate) {
    if ($candidate['skills']) {
      $candidate['skills'] = explode(',', $candidate['skills']);
    } else {
      $candidate['skills'] = [];
    }
  }

  // Devolver con formato estándar consistente con otros endpoints
  echo json_encode([
    'success' => true,
    'message' => 'Candidatos obtenidos correctamente',
    'data' => $candidates,
    'count' => count($candidates)
  ]);
} catch (Exception $e) {
  error_log("Error en candidates endpoint: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Error interno del servidor',
    'details' => $e->getMessage()
  ]);
}
