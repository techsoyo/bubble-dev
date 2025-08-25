#!/usr/bin/env php
<?php
/**
 * Script simple para verificar tablas
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "🔍 Verificando tablas de la base de datos...\n\n";

try {
  require_once __DIR__ . '/../config/database.php';
  $pdo = getDbConnection();
  echo "✅ Conexión a base de datos: OK\n\n";

  // Obtener todas las tablas que empiecen con bt_
  $stmt = $pdo->query("SHOW TABLES LIKE 'bt_%'");
  $tables = $stmt->fetchAll(PDO::FETCH_NUM);

  echo "📋 Tablas encontradas:\n";
  foreach ($tables as $table) {
    $tableName = $table[0];
    echo "   ✅ $tableName\n";

    // Contar registros
    try {
      $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $tableName");
      $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
      echo "      📊 Registros: $count\n";
    } catch (Exception $e) {
      echo "      ⚠️  Error al contar registros\n";
    }
  }

  // Verificar tablas específicas que necesitamos
  $neededTables = ['bt_jobs', 'bt_news'];
  echo "\n🎯 Verificando tablas necesarias:\n";

  foreach ($neededTables as $neededTable) {
    $found = false;
    foreach ($tables as $table) {
      if ($table[0] === $neededTable) {
        $found = true;
        break;
      }
    }

    if ($found) {
      echo "   ✅ $neededTable: EXISTE\n";
    } else {
      echo "   ❌ $neededTable: NO EXISTE\n";
    }
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}

echo "\n🏁 Verificación completada.\n";
?>