<?php

/**
 * Migración para agregar soporte de autenticación social
 * 
 * Agrega columnas para manejar OAuth providers (Google, LinkedIn, etc.)
 * a la tabla bt_candidates
 */

require_once __DIR__ . '/../../config/database.php';

try {
  echo "🔄 Iniciando migración de autenticación social...\n";

  // Obtener conexión a la base de datos
  $pdo = getDbConnection();  // Verificar si las columnas ya existen
  $checkColumns = $pdo->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = 'bubble_talents_DB' 
        AND TABLE_NAME = 'bt_candidates' 
        AND COLUMN_NAME IN ('provider_id', 'provider_type', 'avatar')
    ");

  $existingColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);

  if (count($existingColumns) >= 3) {
    echo "✅ Las columnas de autenticación social ya existen.\n";
    exit(0);
  }

  // Agregar columnas para autenticación social
  $migrations = [
    "ALTER TABLE bt_candidates ADD COLUMN provider_id VARCHAR(255) NULL COMMENT 'ID del usuario en el proveedor OAuth'",
    "ALTER TABLE bt_candidates ADD COLUMN provider_type ENUM('google', 'linkedin', 'facebook', 'github') NULL COMMENT 'Tipo de proveedor OAuth'",
    "ALTER TABLE bt_candidates ADD COLUMN avatar TEXT NULL COMMENT 'URL del avatar del usuario'",
    "CREATE INDEX idx_bt_candidates_provider ON bt_candidates(provider_type, provider_id)",
    "CREATE INDEX idx_bt_candidates_email_provider ON bt_candidates(email, provider_type)"
  ];

  foreach ($migrations as $index => $migration) {
    try {
      echo "🔄 Ejecutando migración " . ($index + 1) . "/" . count($migrations) . "...\n";
      $pdo->exec($migration);
      echo "✅ Migración " . ($index + 1) . " completada.\n";
    } catch (PDOException $e) {
      // Ignorar errores de columnas/índices que ya existen
      if (
        strpos($e->getMessage(), 'Duplicate column name') !== false ||
        strpos($e->getMessage(), 'Duplicate key name') !== false
      ) {
        echo "ℹ️  Migración " . ($index + 1) . " ya aplicada anteriormente.\n";
      } else {
        throw $e;
      }
    }
  }

  echo "\n🎉 Migración de autenticación social completada exitosamente!\n";
  echo "📋 Columnas agregadas:\n";
  echo "   - provider_id: ID del usuario en el proveedor OAuth\n";
  echo "   - provider_type: Tipo de proveedor (google, linkedin, etc.)\n";
  echo "   - avatar: URL del avatar del usuario\n";
  echo "📋 Índices creados:\n";
  echo "   - idx_bt_candidates_provider\n";
  echo "   - idx_bt_candidates_email_provider\n\n";

  // Verificar la estructura final
  echo "🔍 Verificando estructura de la tabla...\n";
  $columns = $pdo->query("DESCRIBE bt_candidates");
  $columnList = $columns->fetchAll(PDO::FETCH_ASSOC);

  $socialColumns = array_filter($columnList, function ($col) {
    return in_array($col['Field'], ['provider_id', 'provider_type', 'avatar']);
  });

  if (count($socialColumns) >= 3) {
    echo "✅ Verificación exitosa: Todas las columnas de autenticación social están presentes.\n";
  } else {
    echo "⚠️  Advertencia: Algunas columnas pueden no haberse creado correctamente.\n";
  }
} catch (PDOException $e) {
  echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
  echo "📋 Detalles del error:\n";
  echo "   - Código: " . $e->getCode() . "\n";
  echo "   - Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
  exit(1);
} catch (Exception $e) {
  echo "❌ Error general: " . $e->getMessage() . "\n";
  exit(1);
}
