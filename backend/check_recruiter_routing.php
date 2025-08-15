<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');

  echo "=== VERIFICAR RELACIONES RECRUITERS ===\n";

  // Verificar tabla staff y su relación con departamentos
  $stmt = $pdo->query("SHOW TABLES LIKE '%staff%'");
  while ($row = $stmt->fetch()) {
    echo "Tabla: " . $row[0] . "\n";
  }

  echo "\n=== ESTRUCTURA bt_staff_profiles ===\n";
  $stmt = $pdo->query('DESCRIBE bt_staff_profiles');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
  }

  echo "\n=== DATOS EJEMPLO bt_department_categories ===\n";
  $stmt = $pdo->query('SELECT * FROM bt_department_categories LIMIT 5');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " | Dept: " . $row['department_id'] . " | Name: " . $row['name'] . "\n";
  }

  echo "\n=== DATOS EJEMPLO bt_departments ===\n";
  $stmt = $pdo->query('SELECT * FROM bt_departments LIMIT 5');
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . "\n";
  }

  echo "\n=== RECRUITERS Y SUS DEPARTAMENTOS ===\n";
  $stmt = $pdo->query("SELECT email, role, department_id FROM bt_staff_profiles WHERE role = 'recruiter'");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Email: " . $row['email'] . " | Dept: " . $row['department_id'] . "\n";
  }
} catch (Exception $e) {
  echo 'Error: ' . $e->getMessage();
}
