<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');

  echo "=== VERIFICAR COLUMNAS DEPARTMENT EN bt_candidates ===\n";
  $stmt = $pdo->query('SHOW COLUMNS FROM bt_candidates WHERE Field LIKE "%department%"');
  $found_columns = false;
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
    $found_columns = true;
  }

  if (!$found_columns) {
    echo "❌ NO HAY COLUMNAS department* en bt_candidates\n";
  }

  echo "\n=== VERIFICAR QUERY PROBLEMÁTICA EN candidates.php ===\n";
  echo "La query actual usa:\n";
  echo "- c.department_id (NO EXISTE)\n";
  echo "- c.department_category_id (NO EXISTE)\n";
  echo "\n";

  echo "=== ESTRUCTURA ACTUAL bt_candidates (primeras 10 columnas) ===\n";
  $stmt = $pdo->query('DESCRIBE bt_candidates LIMIT 10');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
  }
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}
