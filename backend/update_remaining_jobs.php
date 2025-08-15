<?php
// Actualizar los trabajos restantes sin department_id

require_once __DIR__ . '/config/bootstrap.php';

echo "=== ACTUALIZAR TRABAJOS RESTANTES ===\n\n";

try {
  $pdo = getDbConnection();

  // Actualizar trabajos específicos que quedaron sin departamento
  $updates = [
    ['job_title' => 'Marketing Specialist', 'department_id' => 4], // Marketing
    ['job_title' => 'Mobile Developer', 'department_id' => 2],     // Engineering
    ['job_title' => 'Product Manager', 'department_id' => 1],      // Administration
    ['job_title' => 'QA Engineer', 'department_id' => 2]           // Engineering
  ];

  foreach ($updates as $update) {
    $stmt = $pdo->prepare("UPDATE bt_jobs SET department_id = ? WHERE title = ? AND department_id IS NULL");
    $result = $stmt->execute([$update['department_id'], $update['job_title']]);
    $rowCount = $stmt->rowCount();

    if ($rowCount > 0) {
      echo "✅ Actualizado: " . $update['job_title'] . " -> Departamento " . $update['department_id'] . "\n";
    } else {
      echo "⚠️ No se actualizó: " . $update['job_title'] . " (puede que ya tenga departamento o no exista)\n";
    }
  }

  // Verificar estado final
  echo "\n📋 Estado final de trabajos:\n";
  $stmt = $pdo->query("
        SELECT j.id, j.title, j.department_id, d.name as department_name 
        FROM bt_jobs j 
        LEFT JOIN bt_departments d ON j.department_id = d.id 
        ORDER BY j.title
    ");
  $jobs = $stmt->fetchAll();

  $withDepartment = 0;
  $withoutDepartment = 0;

  foreach ($jobs as $job) {
    if ($job['department_id']) {
      $withDepartment++;
      echo "  ✅ " . $job['title'] . " -> " . $job['department_name'] . "\n";
    } else {
      $withoutDepartment++;
      echo "  ❌ " . $job['title'] . " -> Sin departamento\n";
    }
  }

  echo "\n📊 Resumen:\n";
  echo "  - Trabajos con departamento: " . $withDepartment . "\n";
  echo "  - Trabajos sin departamento: " . $withoutDepartment . "\n";
  echo "  - Total trabajos: " . ($withDepartment + $withoutDepartment) . "\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
