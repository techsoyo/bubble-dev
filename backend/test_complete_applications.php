<?php
// Test completo del endpoint con información de departamentos

require_once __DIR__ . '/config/bootstrap.php';

echo "=== TEST COMPLETO CON DEPARTAMENTOS ===\n\n";

try {
  $pdo = getDbConnection();

  // Consulta completa igual que en el endpoint
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
        ORDER BY a.created_at DESC
        LIMIT 3
    ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  $applications = $stmt->fetchAll();

  echo "📊 Aplicaciones encontradas: " . count($applications) . "\n\n";

  foreach ($applications as $app) {
    echo "🎯 Aplicación #" . $app['application_id'] . "\n";
    echo "   Candidato: " . $app['first_name'] . " " . $app['last_name'] . "\n";
    echo "   Candidato Depto: " . ($app['candidate_department_name'] ?? 'Sin departamento') . "\n";
    echo "   Trabajo: " . $app['job_title'] . "\n";
    echo "   Trabajo Depto: " . ($app['job_department_name'] ?? 'Sin departamento') . "\n";
    echo "   Salario: " . ($app['salary_min'] ? $app['salary_min'] . '-' . $app['salary_max'] . ' ' . $app['salary_currency'] : 'No especificado') . "\n";
    echo "   Estado: " . $app['status'] . "\n";
    echo "   Fecha: " . $app['application_date'] . "\n\n";
  }

  // Crear la estructura JSON como en el endpoint
  $processedApplications = [];
  foreach ($applications as $app) {
    $processedApplications[] = [
      'id' => $app['application_id'],
      'candidate_id' => $app['candidate_id'],
      'candidate_email' => $app['candidate_email'],
      'job_id' => $app['job_id'],
      'status' => $app['status'],
      'score' => (float)($app['score'] ?? 0),
      'match_score' => (float)($app['score'] ?? 0),
      'created_at' => $app['application_date'],
      'application_date' => $app['application_date'],
      'updated_at' => $app['updated_at'],
      'insights' => $app['insights'] ? json_decode($app['insights'], true) : null,
      'source' => $app['source'],

      'candidate' => [
        'id' => $app['candidate_id'],
        'name' => $app['first_name'] . ' ' . $app['last_name'],
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

  echo "✅ JSON Response (primeras 3 aplicaciones):\n";
  echo json_encode([
    'success' => true,
    'data' => $processedApplications,
    'total' => count($processedApplications),
    'message' => 'Aplicaciones obtenidas exitosamente'
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
