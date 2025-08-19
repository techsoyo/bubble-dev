<?php

/**
 * GET /api/candidates - Obtiene candidatos usando vistas MySQL
 * 
 * Refactorizado para usar consultas PDO directas a vistas:
 * - Sin id: vw_candidates_list con filtros opcionales (department_id, status, q)
 * - Con id: vw_candidate_profile_full para detalle completo
 * 
 * Filtros permitidos: department_id, status, q (name/email LIKE)
 * Orden: name ASC
 * Paginación: LIMIT/OFFSET bindeados
 */

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

  // Verificar si se solicita un candidato específico
  $candidateId = $_GET['id'] ?? null;

  if ($candidateId) {
    // ========================================
    // DETALLE DE CANDIDATO - vw_candidate_profile_full
    // ========================================

    $sql = "
      SELECT 
        id, name, email, phone, location, status,
        education_summary, experience_summary, skills_text, 
        department_name, department_category_name,
        total_applications, last_activity
      FROM vw_candidate_profile_full
      WHERE id = :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $candidateId, PDO::PARAM_STR);
    $stmt->execute();

    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
      http_response_code(404);
      echo json_encode([
        'success' => false,
        'error' => 'Candidato no encontrado'
      ]);
      exit();
    }

    // Procesar skills si existen
    if (!empty($candidate['skills_text'])) {
      $candidate['skills'] = explode(', ', $candidate['skills_text']);
    } else {
      $candidate['skills'] = [];
    }
    unset($candidate['skills_text']);

    echo json_encode([
      'success' => true,
      'message' => 'Candidato obtenido correctamente',
      'data' => $candidate
    ]);
  } else {
    // ========================================
    // LISTADO DE CANDIDATOS - vw_candidates_list
    // ========================================

    // Obtener parámetros de filtros permitidos
    $departmentId = $_GET['department_id'] ?? null;
    $status = $_GET['status'] ?? 'active';
    $query = $_GET['q'] ?? null;

    // Configuración de paginación
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20))); // Máximo 100
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Si se proporciona offset directamente, usarlo
    if (isset($_GET['offset'])) {
      $offset = max(0, (int)$_GET['offset']);
    }

    // Construir consulta con filtros
    $whereClauses = ['1=1'];
    $params = [];

    if (!empty($status)) {
      $whereClauses[] = 'status = :status';
      $params[':status'] = $status;
    }

    if (!empty($departmentId)) {
      $whereClauses[] = 'department_id = :department_id';
      $params[':department_id'] = $departmentId;
    }

    if (!empty($query)) {
      $whereClauses[] = '(name LIKE :query OR email LIKE :query)';
      $params[':query'] = '%' . $query . '%';
    }

    $whereClause = implode(' AND ', $whereClauses);

    // Consulta principal a la vista
    $sql = "
      SELECT 
        id, name, first_name, last_name, email, phone, location, status,
        department_id, department_category_id, department_name, department_category_name,
        skills_text
      FROM vw_candidates_list
      WHERE {$whereClause}
      ORDER BY name ASC
      LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    // Bind de parámetros de filtros
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    // Bind de parámetros de paginación
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consulta de conteo con mismos filtros
    $countSql = "SELECT COUNT(*) as total FROM vw_candidates_list WHERE {$whereClause}";
    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
      $countStmt->bindValue($key, $value, PDO::PARAM_STR);
    }

    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    // Procesar las skills para convertirlas a array
    foreach ($candidates as &$candidate) {
      if (!empty($candidate['skills_text'])) {
        $candidate['skills'] = explode(', ', $candidate['skills_text']);
      } else {
        $candidate['skills'] = [];
      }
      unset($candidate['skills_text']);
    }

    // Respuesta con metadatos de paginación
    echo json_encode([
      'success' => true,
      'message' => 'Candidatos obtenidos correctamente',
      'data' => $candidates,
      'meta' => [
        'count' => count($candidates),
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'page' => (int)($offset / $limit) + 1,
        'total_pages' => (int)ceil($total / $limit),
        'has_next' => ($offset + $limit) < $total,
        'has_prev' => $offset > 0
      ]
    ]);
  }
} catch (Exception $e) {
  error_log("Error en candidates endpoint: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Error interno del servidor',
    'details' => $e->getMessage()
  ]);
}
