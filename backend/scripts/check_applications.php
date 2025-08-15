<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$pdo = getDBConnection();

echo "📋 APLICACIONES DE cnd-203:\n";
echo str_repeat("=", 50) . "\n";

$stmt = $pdo->prepare("SELECT * FROM bt_applications WHERE candidate_id = ?");
$stmt->execute(['cnd-203']);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($applications)) {
  echo "❌ No hay aplicaciones para cnd-203\n";
} else {
  foreach ($applications as $app) {
    echo "ID: {$app['id']} | Job: {$app['job_id']} | Status: {$app['status']} | Date: {$app['applied_date']}\n";
  }
}

echo "\n📋 ESTRUCTURA DE bt_applications:\n";
$stmt = $pdo->query("DESCRIBE bt_applications");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
  echo "{$col['Field']} ({$col['Type']})\n";
}
