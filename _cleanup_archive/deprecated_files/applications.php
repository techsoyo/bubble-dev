<?php

/**
 * GET /api/applications - Obtiene todas las aplicaciones con información de candidatos y trabajos
 * 
 * Endpoint principal para el HR Dashboard que devuelve aplicaci        'job' => [
          'id' => $app['job_id'],
          'title' => $app['job_title'],
          'description' => $app['job_description'],
          'location' => $app['job_location'],
          'salary_range' => ($app['salary_min'] && $app['salary_max']) ? 
                          $app['salary_min'] . ' - ' . $app['salary_max'] . ' ' . ($app['salary_currency'] ?? 'EUR') : null,
          'employment_type' => $app['employment_type'],
          'status' => $app['job_status'],
          'department_id' => $app['job_department_id'],
          'department_name' => $app['job_department_name']
        ]cidas
 * con datos de candidatos y trabajos
 */

require_once __DIR__ . '/bootstrap.php';

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  $pdo = getDbConnection();
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    // Parámetros opcionales de filtrado
    $jobId = $_GET['jobId'] ?? null;
    $candidateId = $_GET['candidateId'] ?? null;
    $status = $_GET['status'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    // Construir consulta base con JOINs para obtener toda la información
    $sql = "
            SELECT 
                a.id as application_id,
                a.candidate_id,
                a.job_id,
                a.status,
                a.score,
                a.created_at as application_date,
                a.updated_at,
                a.resume,
                a.cover_letter,
                a.insights,
                a.source,
                
                -- Información del candidato
                c.id as candidate_internal_id,
                c.first_name,
                c.last_name,
                c.email as candidate_email,
                c.phone,
                c.location as candidate_location,
                c.status as candidate_status,
                c.department_id,
                c.department_category_id,
                
                -- Información del departamento del candidato
                cd.name as candidate_department_name,
                cdc.name as candidate_department_category_name,
                
                -- Información del trabajo
                j.title as job_title,
                j.description as job_description,
                j.location as job_location,
                j.salary_min,
                j.salary_max,
                j.salary_currency,
                j.salary_period,
                j.type as employment_type,
                j.status as job_status,
                j.department_id as job_department_id,
                
                -- Información del departamento del trabajo
                jd.name as job_department_name
                
            FROM bt_applications a
            LEFT JOIN bt_candidates c ON a.candidate_id = c.id
            LEFT JOIN bt_departments cd ON c.department_id = cd.id
            LEFT JOIN bt_department_categories cdc ON c.department_category_id = cdc.id
            LEFT JOIN bt_jobs j ON a.job_id = j.id
            LEFT JOIN bt_departments jd ON j.department_id = jd.id
            WHERE 1=1
        ";
    $params = [];

    // Agregar filtros si están presentes
    if ($jobId) {
      $sql .= " AND a.job_id = ?";
      $params[] = $jobId;
    }

    if ($candidateId) {
      $sql .= " AND a.candidate_id = ?";
      $params[] = $candidateId;
    }

    if ($status) {
      $sql .= " AND a.status = ?";
      $params[] = $status;
    }

    // Ordenar por fecha de aplicación más reciente
    $sql .= " ORDER BY a.created_at DESC";

    // Agregar paginación
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total de aplicaciones para paginación
    $countSql = "
            SELECT COUNT(*) as total
            FROM bt_applications a
            LEFT JOIN bt_candidates c ON a.candidate_id = c.id
            LEFT JOIN bt_jobs j ON a.job_id = j.id
            WHERE 1=1
        ";

    $countParams = [];
    if ($jobId) {
      $countSql .= " AND a.job_id = ?";
      $countParams[] = $jobId;
    }
    if ($candidateId) {
      $countSql .= " AND a.candidate_id = ?";
      $countParams[] = $candidateId;
    }
    if ($status) {
      $countSql .= " AND a.status = ?";
      $countParams[] = $status;
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Procesar y limpiar los datos
    $processedApplications = [];
    foreach ($applications as $app) {
      // Parsear insights si es JSON
      $insights = null;
      if ($app['insights']) {
        $decoded = json_decode($app['insights'], true);
        $insights = $decoded ?: $app['insights'];
      }

      $processedApplications[] = [
        'id' => $app['application_id'],
        'candidate_id' => $app['candidate_id'],
        'candidate_email' => $app['candidate_email'],
        'job_id' => $app['job_id'],
        'status' => $app['status'] ?: 'Received',
        'score' => (float)($app['score'] ?: 0),
        'match_score' => (float)($app['score'] ?: 0),
        'created_at' => $app['application_date'],
        'application_date' => $app['application_date'],
        'updated_at' => $app['updated_at'],
        'insights' => $insights,
        'source' => $app['source'],

        // Información del candidato estructurada
        'candidate' => [
          'id' => $app['candidate_id'],
          'name' => trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')),
          'first_name' => $app['first_name'],
          'last_name' => $app['last_name'],
          'email' => $app['candidate_email'],
          'phone' => $app['phone'],
          'location' => $app['candidate_location'],
          'status' => $app['candidate_status'],
          'department_id' => $app['department_id'],
          'department_category_id' => $app['department_category_id'],
          'department_name' => $app['candidate_department_name'],
          'department_category_name' => $app['candidate_department_category_name']
        ],

        // Información del trabajo estructurada
        'job' => [
          'id' => $app['job_id'],
          'title' => $app['job_title'],
          'description' => $app['job_description'],
          'location' => $app['job_location'],
          'salary_range' => ($app['salary_min'] && $app['salary_max']) ?
            $app['salary_min'] . ' - ' . $app['salary_max'] . ' ' . ($app['salary_currency'] ?? 'EUR') : null,
          'employment_type' => $app['employment_type'],
          'status' => $app['job_status'],
          'department_id' => $app['job_department_id'],
          'department_name' => $app['job_department_name']
        ]
      ];
    }

    // Respuesta exitosa
    echo json_encode([
      'success' => true,
      'message' => 'Aplicaciones obtenidas correctamente',
      'data' => $processedApplications,
      'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => (int)$total,
        'total_pages' => ceil($total / $limit)
      ],
      'count' => count($processedApplications)
    ], JSON_UNESCAPED_UNICODE);
  } else {
    // Método no permitido
    http_response_code(405);
    echo json_encode([
      'success' => false,
      'message' => 'Método no permitido'
    ]);
  }
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error de base de datos',
    'error' => $e->getMessage()
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error interno del servidor',
    'error' => $e->getMessage()
  ]);
}
