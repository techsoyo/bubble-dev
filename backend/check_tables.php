<?php
try {
  $pdo = new PDO('mysql:host=localhost;dbname=bubble_talents_DB', 'root', '');
  $stmt = $pdo->query('DESCRIBE bt_applications');
  echo "Estructura de bt_applications:\n";
  foreach ($stmt->fetchAll() as $row) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
  }

  echo "\n\nEstructura de bt_candidates:\n";
  $stmt2 = $pdo->query('DESCRIBE bt_candidates');
  foreach ($stmt2->fetchAll() as $row) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Key'] . "\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage();
}
