<?php

// Configuración de base de datos
$db_config = [
  'host' => '192.168.1.40',
  'dbname' => 'bubble_talents_DB',
  'username' => 'user',
  'password' => 'user123'
];

try {
  $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8";
  $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  // Leer y ejecutar el archivo seed
  $seed_sql = file_get_contents('cypress_e2e_seed_final.sql');
  $statements = explode(';', $seed_sql);

  $pdo->beginTransaction();

  echo "Loading Cypress E2E seed data...\n";

  $executed = 0;
  foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
      $pdo->exec($statement);
      $executed++;
    }
  }

  $pdo->commit();
  echo "✅ Cypress E2E seed data loaded successfully ($executed statements)\n";

  // Verificar datos insertados
  $jobs = $pdo->query("SELECT id, title FROM bt_jobs WHERE id LIKE 'cypress-%'")->fetchAll();
  echo "✅ Jobs inserted: " . count($jobs) . "\n";
  foreach ($jobs as $job) {
    echo "   - Job ID: {$job['id']}, Title: {$job['title']}\n";
  }

  $reqs = $pdo->query("SELECT COUNT(*) as total FROM bt_job_requirements WHERE job_id LIKE 'cypress-%'")->fetch();
  echo "✅ Requirements inserted: " . $reqs['total'] . "\n";

  $skills = $pdo->query("SELECT COUNT(*) as total FROM bt_job_skills WHERE job_id LIKE 'cypress-%'")->fetch();
  echo "✅ Skills inserted: " . $skills['total'] . "\n";

  echo "\n🎯 Cypress test data ready!\n";
  echo "Use job IDs: 'cypress-job-1', 'cypress-job-2' in your E2E tests\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  if (isset($pdo)) {
    $pdo->rollback();
  }
  exit(1);
}
