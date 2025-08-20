<?php
require_once __DIR__ . '/config/bootstrap.php';

use Utils\Database;

$db = Database::getInstance();
$pdo = $db->getConnection();

echo "=== ESTRUCTURA DE LA TABLA bt_candidates ===\n";
$stmt = $pdo->query('DESCRIBE bt_candidates');
while ($row = $stmt->fetch()) {
  echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n=== ESTRUCTURA DE LA TABLA bt_candidate_experiences ===\n";
$stmt = $pdo->query('DESCRIBE bt_candidate_experiences');
while ($row = $stmt->fetch()) {
  echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
