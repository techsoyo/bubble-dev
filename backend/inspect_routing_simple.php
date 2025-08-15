<?php

require_once __DIR__ . '/config/bootstrap.php';

try {
  $pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
    getenv('DB_USER'),
    getenv('DB_PASS')
  );

  echo "🔍 ESTRUCTURA bt_candidate_routing:" . PHP_EOL;
  $stmt = $pdo->query("DESCRIBE bt_candidate_routing");
  $columns = $stmt->fetchAll();
  foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")" . PHP_EOL;
  }

  echo PHP_EOL . "📊 RECRUITERS (bt_staff_profiles):" . PHP_EOL;
  $stmt = $pdo->query("DESCRIBE bt_staff_profiles");
  $columns = $stmt->fetchAll();
  $fields = [];
  foreach ($columns as $col) {
    $fields[] = $col['Field'];
  }
  echo "Campos disponibles: " . implode(", ", $fields) . PHP_EOL;

  echo PHP_EOL . "Sample routing data:" . PHP_EOL;
  $stmt = $pdo->query("SELECT candidate_id, department_id, recruiter_id, source, assigned_at FROM bt_candidate_routing ORDER BY assigned_at DESC LIMIT 3");
  $routings = $stmt->fetchAll();
  foreach ($routings as $r) {
    echo "- " . $r['candidate_id'] . " -> Dept:" . $r['department_id'] . " Recruiter:" . ($r['recruiter_id'] ?? 'NULL') . " Source:" . $r['source'] . PHP_EOL;
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . PHP_EOL;
}
