<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');

  echo "=== CREAR COLUMNAS DEPARTMENT EN bt_candidates ===\n";

  // Verificar columnas existentes
  $stmt = $pdo->query("SHOW COLUMNS FROM bt_candidates WHERE Field LIKE '%department%'");
  $existing_columns = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing_columns[] = $row['Field'];
  }

  if (empty($existing_columns)) {
    echo "❌ NO hay columnas department en bt_candidates\n";
    echo "✅ Añadiendo department_id y department_category_id...\n";

    // Añadir AMBAS columnas necesarias
    $sql = "ALTER TABLE bt_candidates 
                ADD COLUMN department_id INT NULL 
                AFTER status,
                ADD COLUMN department_category_id INT NULL 
                AFTER department_id,
                ADD INDEX idx_department (department_id),
                ADD INDEX idx_department_category (department_category_id)";

    echo "Ejecutando: " . $sql . "\n";
    $pdo->exec($sql);

    echo "✅ Columnas department_id y department_category_id añadidas exitosamente\n";

    // Intentar añadir foreign keys (opcional)
    try {
      $fk1 = "ALTER TABLE bt_candidates 
                    ADD FOREIGN KEY fk_candidate_dept (department_id) 
                    REFERENCES bt_departments(id) ON DELETE SET NULL";
      $pdo->exec($fk1);
      echo "✅ Foreign key para department_id creada\n";

      $fk2 = "ALTER TABLE bt_candidates 
                    ADD FOREIGN KEY fk_candidate_dept_category (department_category_id) 
                    REFERENCES bt_department_categories(id) ON DELETE SET NULL";
      $pdo->exec($fk2);
      echo "✅ Foreign key para department_category_id creada\n";
    } catch (Exception $fk_error) {
      echo "⚠️  Foreign keys no creadas: " . $fk_error->getMessage() . "\n";
      echo "✅ Columnas creadas sin foreign keys (funcional)\n";
    }
  } else {
    echo "✅ Columnas department existentes: " . implode(', ', $existing_columns) . "\n";
  }

  // Verificar la nueva estructura
  echo "\n=== NUEVA ESTRUCTURA bt_candidates (department fields) ===\n";
  $stmt = $pdo->query("SHOW COLUMNS FROM bt_candidates WHERE Field LIKE '%department%'");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . " (" . $row['Null'] . ")\n";
  }

  // Verificar foreign keys
  echo "\n=== FOREIGN KEYS CREADAS ===\n";
  $stmt = $pdo->query("SELECT 
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'bubble_talents_DB' 
        AND TABLE_NAME = 'bt_candidates' 
        AND REFERENCED_TABLE_NAME IS NOT NULL");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['CONSTRAINT_NAME'] . ": " . $row['COLUMN_NAME'] . " -> " .
      $row['REFERENCED_TABLE_NAME'] . "." . $row['REFERENCED_COLUMN_NAME'] . "\n";
  }
} catch (Exception $e) {
  echo '❌ Error: ' . $e->getMessage() . "\n";

  // Si hay error de foreign key, intentar sin FK
  try {
    echo "\n=== INTENTANDO SIN FOREIGN KEY ===\n";
    $sql_simple = "ALTER TABLE bt_candidates 
                       ADD COLUMN department_category_id INT NULL 
                       AFTER department_id,
                       ADD INDEX idx_department_category (department_category_id)";

    echo "Ejecutando: " . $sql_simple . "\n";
    $pdo->exec($sql_simple);
    echo "✅ Columna department_category_id añadida (sin FK)\n";
  } catch (Exception $e2) {
    echo '❌ Error final: ' . $e2->getMessage() . "\n";
  }
}
