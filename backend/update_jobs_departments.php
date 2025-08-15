<?php
// Verificar y actualizar los department_id de los trabajos

require_once __DIR__ . '/config/bootstrap.php';

echo "=== VERIFICAR Y ACTUALIZAR DEPARTMENT_ID EN TRABAJOS ===\n\n";

try {
  $pdo = getDbConnection();

  // Verificar trabajos actuales
  echo "📋 Trabajos actuales con department_id:\n";
  $stmt = $pdo->query("SELECT id, title, department_id FROM bt_jobs ORDER BY title");
  $jobs = $stmt->fetchAll();

  foreach ($jobs as $job) {
    echo "  - " . $job['title'] . " (ID: " . $job['id'] . ") -> Departamento: " . ($job['department_id'] ?? 'NULL') . "\n";
  }

  echo "\n🔄 Actualizando trabajos sin department_id...\n";

  // Actualizar trabajos específicos
  $updates = [
    ['pattern' => '%Backend Developer%', 'department_id' => 2, 'name' => 'Backend Developer'],
    ['pattern' => '%Full Stack Developer%', 'department_id' => 2, 'name' => 'Full Stack Developer'],
    ['pattern' => '%Frontend Developer%', 'department_id' => 2, 'name' => 'Frontend Developer'],
    ['pattern' => '%DevOps Engineer%', 'department_id' => 2, 'name' => 'DevOps Engineer'],
    ['pattern' => '%Data Scientist%', 'department_id' => 2, 'name' => 'Data Scientist'],
    ['pattern' => '%UX/UI Designer%', 'department_id' => 6, 'name' => 'UX/UI Designer']
  ];

  foreach ($updates as $update) {
    $stmt = $pdo->prepare("UPDATE bt_jobs SET department_id = ? WHERE title LIKE ? AND department_id IS NULL");
    $result = $stmt->execute([$update['department_id'], $update['pattern']]);
    $rowCount = $stmt->rowCount();

    if ($rowCount > 0) {
      echo "  ✅ Actualizado " . $rowCount . " trabajo(s): " . $update['name'] . " -> Departamento " . $update['department_id'] . "\n";
    } else {
      echo "  ⚠️ No se encontraron trabajos para actualizar: " . $update['name'] . "\n";
    }
  }

  // Verificar trabajos después de la actualización
  echo "\n📋 Trabajos después de la actualización:\n";
  $stmt = $pdo->query("
        SELECT j.id, j.title, j.department_id, d.name as department_name 
        FROM bt_jobs j 
        LEFT JOIN bt_departments d ON j.department_id = d.id 
        ORDER BY j.title
    ");
  $jobs = $stmt->fetchAll();

  foreach ($jobs as $job) {
    echo "  - " . $job['title'] . " -> Departamento: " .
      ($job['department_name'] ?? 'Sin departamento') . " (ID: " . ($job['department_id'] ?? 'NULL') . ")\n";
  }

  echo "\n✅ Actualización completada\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
