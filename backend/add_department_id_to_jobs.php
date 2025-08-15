<?php
// Script para añadir la columna department_id a la tabla bt_jobs

require_once __DIR__ . '/config/bootstrap.php';

echo "=== AÑADIR COLUMNA DEPARTMENT_ID A BT_JOBS ===\n\n";

try {
  $pdo = getDbConnection();

  // Verificar si la columna ya existe
  $stmt = $pdo->query("DESCRIBE bt_jobs");
  $columns = $stmt->fetchAll();

  $departmentIdExists = false;
  foreach ($columns as $column) {
    if ($column['Field'] === 'department_id') {
      $departmentIdExists = true;
      break;
    }
  }

  if ($departmentIdExists) {
    echo "✅ La columna department_id ya existe en bt_jobs\n";
  } else {
    echo "📝 Añadiendo columna department_id a bt_jobs...\n";

    // Añadir la columna department_id
    $sql = "ALTER TABLE bt_jobs ADD COLUMN department_id INT NULL";
    $pdo->exec($sql);

    echo "✅ Columna department_id añadida exitosamente\n";

    // Añadir índice para mejorar el rendimiento
    $sql = "ALTER TABLE bt_jobs ADD INDEX idx_department_id (department_id)";
    $pdo->exec($sql);

    echo "✅ Índice añadido para department_id\n";

    // Opcional: Añadir foreign key constraint
    try {
      $sql = "ALTER TABLE bt_jobs ADD CONSTRAINT fk_jobs_department 
                    FOREIGN KEY (department_id) REFERENCES bt_departments(id) 
                    ON DELETE SET NULL ON UPDATE CASCADE";
      $pdo->exec($sql);
      echo "✅ Foreign key constraint añadida\n";
    } catch (Exception $e) {
      echo "⚠️ No se pudo añadir foreign key constraint: " . $e->getMessage() . "\n";
    }
  }

  // Verificar la estructura actualizada
  echo "\n📋 Estructura actualizada de bt_jobs:\n";
  $stmt = $pdo->query("DESCRIBE bt_jobs");
  $columns = $stmt->fetchAll();

  foreach ($columns as $column) {
    echo "  - " . $column['Field'] . " (" . $column['Type'] . ")" .
      ($column['Null'] === 'NO' ? ' NOT NULL' : ' NULL') .
      ($column['Key'] === 'PRI' ? ' PRIMARY KEY' : '') .
      ($column['Key'] === 'MUL' ? ' INDEX' : '') . "\n";
  }

  // Mostrar algunos departamentos disponibles para referencia
  echo "\n📋 Departamentos disponibles:\n";
  $stmt = $pdo->query("SELECT id, name FROM bt_departments ORDER BY id");
  $departments = $stmt->fetchAll();

  foreach ($departments as $dept) {
    echo "  - ID: " . $dept['id'] . " - " . $dept['name'] . "\n";
  }

  // Actualizar algunos trabajos con department_id para testing
  echo "\n🔄 Actualizando algunos trabajos con department_id...\n";

  $updates = [
    ['job_title' => 'Backend Developer', 'department_id' => 2], // Engineering
    ['job_title' => 'Full Stack Developer', 'department_id' => 2], // Engineering
    ['job_title' => 'Frontend Developer', 'department_id' => 2], // Engineering
    ['job_title' => 'DevOps Engineer', 'department_id' => 2], // Engineering
    ['job_title' => 'Data Scientist', 'department_id' => 2], // Engineering
    ['job_title' => 'UX/UI Designer', 'department_id' => 6] // Assuming design department
  ];

  foreach ($updates as $update) {
    try {
      $stmt = $pdo->prepare("UPDATE bt_jobs SET department_id = ? WHERE title LIKE ?");
      $stmt->execute([$update['department_id'], '%' . $update['job_title'] . '%']);
      echo "  ✅ Actualizado: " . $update['job_title'] . " -> Departamento " . $update['department_id'] . "\n";
    } catch (Exception $e) {
      echo "  ❌ Error actualizando " . $update['job_title'] . ": " . $e->getMessage() . "\n";
    }
  }

  echo "\n✅ Proceso completado\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
