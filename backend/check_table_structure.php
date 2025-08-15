<?php
// Test para ver la estructura de las tablas

require_once __DIR__ . '/config/bootstrap.php';

echo "=== ESTRUCTURA DE TABLAS ===\n\n";

try {
  $pdo = getDbConnection();

  $tables = ['bt_applications', 'bt_candidates', 'bt_jobs'];

  foreach ($tables as $table) {
    echo "📋 Estructura de $table:\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll();

    foreach ($columns as $column) {
      echo "  - " . $column['Field'] . " (" . $column['Type'] . ")" .
        ($column['Null'] === 'NO' ? ' NOT NULL' : '') .
        ($column['Key'] === 'PRI' ? ' PRIMARY KEY' : '') . "\n";
    }
    echo "\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
