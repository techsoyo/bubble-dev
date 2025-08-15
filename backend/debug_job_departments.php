<?php
// Debug específico del JOIN con departamentos de trabajos

require_once __DIR__ . '/config/bootstrap.php';

echo "=== DEBUG JOIN DEPARTAMENTOS DE TRABAJOS ===\n\n";

try {
  $pdo = getDbConnection();

  // Primero verificar trabajos y sus department_id
  echo "📋 Trabajos con department_id:\n";
  $stmt = $pdo->query("SELECT id, title, department_id FROM bt_jobs WHERE id IN (SELECT DISTINCT job_id FROM bt_applications) ORDER BY title");
  $jobs = $stmt->fetchAll();

  foreach ($jobs as $job) {
    echo "  - " . $job['title'] . " (ID: " . $job['id'] . ") -> Departamento ID: " . ($job['department_id'] ?? 'NULL') . "\n";
  }

  // Luego probar el JOIN específico
  echo "\n🔍 Test JOIN trabajos-departamentos:\n";
  $stmt = $pdo->query("
        SELECT j.id, j.title, j.department_id, d.name as department_name 
        FROM bt_jobs j 
        LEFT JOIN bt_departments d ON j.department_id = d.id 
        WHERE j.id IN (SELECT DISTINCT job_id FROM bt_applications)
        ORDER BY j.title
    ");
  $jobsWithDepts = $stmt->fetchAll();

  foreach ($jobsWithDepts as $job) {
    echo "  - " . $job['title'] . " -> " . ($job['department_name'] ?? 'NULL') . " (ID: " . ($job['department_id'] ?? 'NULL') . ")\n";
  }

  // Probar la consulta completa pero solo con los campos de trabajo
  echo "\n🔍 Test consulta aplicaciones con trabajos:\n";
  $stmt = $pdo->query("
        SELECT 
            a.id as application_id,
            j.title as job_title,
            j.department_id as job_department_id,
            jd.name as job_department_name
        FROM bt_applications a
        LEFT JOIN bt_jobs j ON a.job_id = j.id
        LEFT JOIN bt_departments jd ON j.department_id = jd.id
        ORDER BY a.id
        LIMIT 3
    ");
  $apps = $stmt->fetchAll();

  foreach ($apps as $app) {
    echo "  - App " . $app['application_id'] . ": " . $app['job_title'] .
      " -> Depto ID: " . ($app['job_department_id'] ?? 'NULL') .
      " -> Depto: " . ($app['job_department_name'] ?? 'NULL') . "\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
