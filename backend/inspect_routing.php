<?php

require_once __DIR__ . '/config/bootstrap.  // Sample recruiters
  echo PHP_EOL . "Sample recruiters:" . PHP_EOL;
  try {
    $stmt = $pdo->query("SELECT id, name, email, department_id FROM bt_staff_profiles WHERE role = 'recruiter' LIMIT 3");
    $recruiters = $stmt->fetchAll();
    foreach ($recruiters as $recruiter) {
      echo "- ID: " . $recruiter['id'] . ", Name: " . $recruiter['name'] . ", Email: " . $recruiter['email'] . ", Dept: " . $recruiter['department_id'] . PHP_EOL;
    }
  } catch(Exception $e) {
    echo "Error getting recruiters: " . $e->getMessage() . PHP_EOL;
    // Try to see available fields
    echo "Checking available fields..." . PHP_EOL;
    $stmt = $pdo->query("DESCRIBE bt_staff_profiles");
    $columns = $stmt->fetchAll();
    echo "Available fields: ";
    foreach($columns as $col) {
      echo $col['Field'] . " ";
    }
    echo PHP_EOL;
  }cho "🔍 INSPECCIÓN BASE DE DATOS - ROUTING TABLES" . PHP_EOL;
echo "============================================" . PHP_EOL;

try {
  $pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
  );

  // Buscar tablas relacionadas con routing
  $stmt = $pdo->query("SHOW TABLES LIKE '%routing%'");
  $tables = $stmt->fetchAll();

  echo "Tablas routing encontradas:" . PHP_EOL;
  foreach ($tables as $table) {
    echo "- " . $table[0] . PHP_EOL;
  }

  // Buscar tablas de departments y staff
  echo PHP_EOL . "Tablas relacionadas:" . PHP_EOL;
  $related = ['bt_departments', 'bt_department_categories', 'bt_staff_profiles', 'bt_skill_department_map'];
  foreach ($related as $tableName) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
    $exists = $stmt->fetch();
    echo "- $tableName: " . ($exists ? "✅ EXISTS" : "❌ NOT FOUND") . PHP_EOL;
  }

  // Verificar estructura de bt_candidate_routing
  echo PHP_EOL . "Estructura bt_candidate_routing:" . PHP_EOL;
  $stmt = $pdo->query("DESCRIBE bt_candidate_routing");
  $columns = $stmt->fetchAll();
  foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")" . PHP_EOL;
  }

  // Sample routing data
  echo PHP_EOL . "Sample routing records:" . PHP_EOL;
  $stmt = $pdo->query("SELECT * FROM bt_candidate_routing ORDER BY assigned_at DESC LIMIT 3");
  $routings = $stmt->fetchAll();
  foreach ($routings as $routing) {
    echo "- Candidate: " . ($routing['candidate_id'] ?? 'N/A') .
      ", Dept: " . ($routing['department_id'] ?? 'N/A') .
      ", Recruiter: " . ($routing['recruiter_id'] ?? 'N/A') .
      ", Date: " . ($routing['assigned_at'] ?? 'N/A') . PHP_EOL;
  }

  // Sample recruiters
  echo PHP_EOL . "Sample recruiters:" . PHP_EOL;
  $stmt = $pdo->query("SELECT id, first_name, email, department_id FROM bt_staff_profiles WHERE role = 'recruiter' LIMIT 3");
  $recruiters = $stmt->fetchAll();
  foreach ($recruiters as $recruiter) {
    echo "- ID: " . ($recruiter['id'] ?? 'N/A') .
      ", Name: " . ($recruiter['first_name'] ?? 'N/A') .
      ", Email: " . ($recruiter['email'] ?? 'N/A') .
      ", Dept: " . ($recruiter['department_id'] ?? 'N/A') . PHP_EOL;
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}
