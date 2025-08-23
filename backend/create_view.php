<?php

/**
 * Script para crear la vista vw_jobs_by_department
 */

require_once __DIR__ . '/config/database.php';

try {
  $db = getDbConnection();

  // Intentar eliminar la vista si ya existe
  try {
    $db->exec("DROP VIEW IF EXISTS vw_jobs_by_department");
    echo "Vista anterior eliminada (si existía)\n";
  } catch (Exception $e) {
    // Ignorar error si la vista no existía
  }

  // Crear la nueva vista
  $sql = "CREATE VIEW vw_jobs_by_department AS
    SELECT 
        j.id,
        j.title,
        j.company_name,
        j.location,
        j.type,
        j.level,
        j.category,
        j.salary_min,
        j.salary_max,
        j.salary_currency,
        j.salary_period,
        j.posted_at,
        j.expires_at,
        j.status,
        j.created_at,
        j.is_featured,
        j.department_id,
        d.name as department_name
    FROM bt_jobs j
    LEFT JOIN bt_departments d ON j.department_id = d.id";

  $db->exec($sql);
  echo "✅ Vista vw_jobs_by_department creada exitosamente\n";

  // Probar la vista con una consulta simple
  $testSql = "SELECT COUNT(*) as total FROM vw_jobs_by_department";
  $result = $db->query($testSql)->fetch();
  echo "✅ Test: La vista contiene {$result['total']} registros\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}

echo "\n🎉 Vista lista para usar en /api/jobs/by-department/{id}\n";
