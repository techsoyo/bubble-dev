<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');

  echo "\n=== ESTRUCTURA bt_department_categories ===\n";
  $stmt = $pdo->query('DESCRIBE bt_department_categories');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
  }

  echo "\n=== VERIFICAR COLUMN department_category_id EN bt_candidates ===\n";
  $stmt = $pdo->query("SHOW COLUMNS FROM bt_candidates LIKE 'department_category_id'");
  $column_exists = $stmt->fetch();
  if ($column_exists) {
    echo "EXISTE: department_category_id en bt_candidates\n";
  } else {
    echo "NO EXISTE: department_category_id en bt_candidates\n";
  }
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}
