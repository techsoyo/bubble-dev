<?php
// Test de la tabla bt_departments

require_once __DIR__ . '/config/bootstrap.php';

echo "=== TEST DE LA TABLA BT_DEPARTMENTS ===\n\n";

try {
  $pdo = getDbConnection();

  // Verificar si la tabla existe
  $stmt = $pdo->query("SHOW TABLES LIKE 'bt_departments'");
  $table_exists = $stmt->fetchAll();

  if (empty($table_exists)) {
    echo "❌ La tabla bt_departments NO existe\n";

    // Verificar si hay tablas similares
    $stmt = $pdo->query("SHOW TABLES LIKE '%department%'");
    $similar_tables = $stmt->fetchAll();

    echo "📋 Tablas con 'department' en el nombre:\n";
    foreach ($similar_tables as $table) {
      echo "  - " . array_values($table)[0] . "\n";
    }
  } else {
    echo "✅ La tabla bt_departments existe\n";

    // Verificar estructura
    $stmt = $pdo->query("DESCRIBE bt_departments");
    $columns = $stmt->fetchAll();

    echo "📋 Estructura de bt_departments:\n";
    foreach ($columns as $column) {
      echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }

    // Verificar datos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bt_departments");
    $result = $stmt->fetch();
    echo "\n📊 Registros en bt_departments: " . $result['total'] . "\n";

    if ($result['total'] > 0) {
      $stmt = $pdo->query("SELECT * FROM bt_departments LIMIT 3");
      $departments = $stmt->fetchAll();

      echo "🔍 Primeros 3 departamentos:\n";
      foreach ($departments as $dept) {
        echo "  - ID: " . $dept['id'] . ", Nombre: " . ($dept['name'] ?? 'Sin nombre') . "\n";
      }
    }
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
