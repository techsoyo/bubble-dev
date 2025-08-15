<?php
// Test para ver qué tablas existen en la base de datos

require_once __DIR__ . '/config/bootstrap.php';

echo "=== LISTADO DE TABLAS EN LA BASE DE DATOS ===\n\n";

try {
  // Cargar variables de entorno
  loadEnvironmentVars();

  // Intentar conexión
  $pdo = getDbConnection();
  echo "✅ Conexión exitosa a la base de datos\n\n";

  // Listar todas las tablas
  $stmt = $pdo->query("SHOW TABLES");
  $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

  echo "📋 Tablas encontradas:\n";
  foreach ($tables as $table) {
    echo "  - " . $table . "\n";
  }

  echo "\n📊 Total de tablas: " . count($tables) . "\n\n";

  // Si hay tablas con prefijo bt_, mostrarlas
  $btTables = array_filter($tables, function ($table) {
    return strpos($table, 'bt_') === 0;
  });

  if (!empty($btTables)) {
    echo "🏷️ Tablas con prefijo 'bt_':\n";
    foreach ($btTables as $table) {
      echo "  - " . $table . "\n";

      // Contar registros en cada tabla
      try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
        $result = $stmt->fetch();
        echo "    → Registros: " . $result['total'] . "\n";
      } catch (Exception $e) {
        echo "    → Error al contar: " . $e->getMessage() . "\n";
      }
    }
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
