#!/usr/bin/env php
<?php
/**
 * Script para verificar la estructura de tablas necesarias para las secciones del frontend
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "🔍 Verificando estructura de base de datos...\n\n";

try {
  require_once __DIR__ . '/../config/database.php';
  $pdo = getDbConnection();
  echo "✅ Conexión a base de datos: OK\n\n";

  // Verificar tablas principales
  $tablesToCheck = [
    'bt_jobs' => 'Ofertas de trabajo',
    'bt_news' => 'Noticias',
    'bt_companies' => 'Empresas',
    'bt_candidates' => 'Candidatos'
  ];

  echo "📋 Verificando tablas:\n";
  foreach ($tablesToCheck as $table => $description) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    $exists = $stmt->fetch();

    if ($exists) {
      echo "   ✅ $table ($description): EXISTE\n";

      // Contar registros
      $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
      $countStmt->execute();
      $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
      echo "      📊 Registros: $count\n";
    } else {
      echo "   ❌ $table ($description): NO EXISTE\n";
    }
  }

  echo "\n🔧 Verificando estructura de tablas existentes:\n";

  // Verificar estructura de bt_jobs si existe
  $stmt = $pdo->prepare("SHOW TABLES LIKE 'bt_jobs'");
  $stmt->execute();
  if ($stmt->fetch()) {
    echo "\n📋 Estructura de bt_jobs:\n";
    $cols = $pdo->query("DESCRIBE bt_jobs")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
      echo "   - {$col['Field']} ({$col['Type']})\n";
    }
  }

  // Verificar estructura de bt_news si existe
  $stmt = $pdo->prepare("SHOW TABLES LIKE 'bt_news'");
  $stmt->execute();
  if ($stmt->fetch()) {
    echo "\n📋 Estructura de bt_news:\n";
    $cols = $pdo->query("DESCRIBE bt_news")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
      echo "   - {$col['Field']} ({$col['Type']})\n";
    }
  }
} catch (Exception $e) {
  echo "\n❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🏁 Verificación completada.\n";
echo "\n💡 Si faltan tablas, puedes ejecutar:\n";
echo "   php backend/scripts/setup-missing-tables.php\n";
?>