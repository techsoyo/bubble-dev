<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');
  $stmt = $pdo->query('SHOW TABLES');
  echo "=== TABLAS EXISTENTES ===\n";
  while ($row = $stmt->fetch()) {
    if (stripos($row[0], 'candidate') !== false || stripos($row[0], 'department') !== false) {
      echo $row[0] . "\n";
    }
  }

  echo "\n=== ESTRUCTURA bt_candidates ===\n";
  $stmt = $pdo->query('DESCRIBE bt_candidates');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
  }

  echo "\n=== ESTRUCTURA bt_departments ===\n";
  $stmt = $pdo->query('DESCRIBE bt_departments');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
  }
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}
