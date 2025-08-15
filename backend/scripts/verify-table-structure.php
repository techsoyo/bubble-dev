<?php

/**
 * Script para verificar la estructura actual de la tabla bt_candidates
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
  $pdo = getDbConnection();

  echo "📊 Estructura actual de la tabla bt_candidates:\n\n";

  $stmt = $pdo->query("DESCRIBE bt_candidates");
  $columns = $stmt->fetchAll();

  printf(
    "%-25s %-20s %-10s %-10s %-10s %-20s\n",
    "COLUMNA",
    "TIPO",
    "NULL",
    "KEY",
    "DEFAULT",
    "EXTRA"
  );
  echo str_repeat("-", 100) . "\n";

  foreach ($columns as $column) {
    printf(
      "%-25s %-20s %-10s %-10s %-10s %-20s\n",
      $column['Field'],
      $column['Type'],
      $column['Null'],
      $column['Key'],
      $column['Default'] ?? 'NULL',
      $column['Extra']
    );
  }

  echo "\n✅ Total de columnas: " . count($columns) . "\n";

  // Verificar específicamente las columnas que agregamos
  $newColumns = [
    'resumen_profesional',
    'soft_skills',
    'hard_skills',
    'idiomas',
    'intereses',
    'referencias',
    'disponibilidad',
    'certificaciones',
    'cv_original_file',
    'cv_text_file',
    'cv_json_file',
    'data_source'
  ];

  $existingColumns = array_column($columns, 'Field');

  echo "\n🔍 Verificación de columnas agregadas:\n";
  foreach ($newColumns as $col) {
    $status = in_array($col, $existingColumns) ? "✅ EXISTE" : "❌ FALTA";
    echo "  {$col}: {$status}\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
