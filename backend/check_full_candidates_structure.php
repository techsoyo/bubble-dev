<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');

  echo "=== ESTRUCTURA COMPLETA bt_candidates ===\n";
  $stmt = $pdo->query('DESCRIBE bt_candidates');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
  }
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}
