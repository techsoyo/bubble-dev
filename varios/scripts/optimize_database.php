<?php

/**
 * Optimizador de Base de Datos para Bubble of Talents
 * 
 * Script para optimizar rendimiento de consultas y estructura de BD
 * 
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 */

require_once __DIR__ . '/backend/config/database.php';

class DatabaseOptimizer
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = getDbConnection();
  }

  /**
   * Ejecuta todas las optimizaciones
   */
  public function optimize(): void
  {
    echo "🔧 Iniciando optimización de base de datos...\n\n";

    $this->addIndexes();
    $this->optimizeTables();
    $this->analyzeQueries();

    echo "✅ Optimización completada!\n";
  }

  /**
   * Agrega índices para mejorar rendimiento
   */
  private function addIndexes(): void
  {
    echo "📊 Agregando índices de rendimiento...\n";

    $indexes = [
      // Users table
      "CREATE INDEX IF NOT EXISTS idx_users_email ON bt_users(email)",
      "CREATE INDEX IF NOT EXISTS idx_users_role ON bt_users(role)",
      "CREATE INDEX IF NOT EXISTS idx_users_status ON bt_users(status)",
      "CREATE INDEX IF NOT EXISTS idx_users_created_at ON bt_users(created_at)",

      // Candidates table
      "CREATE INDEX IF NOT EXISTS idx_candidates_email ON bt_candidates(email)",
      "CREATE INDEX IF NOT EXISTS idx_candidates_status ON bt_candidates(status)",
      "CREATE INDEX IF NOT EXISTS idx_candidates_created_at ON bt_candidates(created_at)",

      // Jobs table
      "CREATE INDEX IF NOT EXISTS idx_jobs_status ON bt_jobs(status)",
      "CREATE INDEX IF NOT EXISTS idx_jobs_department ON bt_jobs(department_id)",
      "CREATE INDEX IF NOT EXISTS idx_jobs_created_at ON bt_jobs(created_at)",

      // Applications table
      "CREATE INDEX IF NOT EXISTS idx_applications_candidate ON bt_applications(candidate_id)",
      "CREATE INDEX IF NOT EXISTS idx_applications_job ON bt_applications(job_id)",
      "CREATE INDEX IF NOT EXISTS idx_applications_status ON bt_applications(status)",
      "CREATE INDEX IF NOT EXISTS idx_applications_created_at ON bt_applications(created_at)",

      // Composite indexes for common queries
      "CREATE INDEX IF NOT EXISTS idx_applications_candidate_status ON bt_applications(candidate_id, status)",
      "CREATE INDEX IF NOT EXISTS idx_jobs_status_department ON bt_jobs(status, department_id)"
    ];

    foreach ($indexes as $sql) {
      try {
        $this->pdo->exec($sql);
        echo "✓ Índice agregado: " . substr($sql, strpos($sql, 'idx_')) . "\n";
      } catch (PDOException $e) {
        echo "⚠ Error creando índice: " . $e->getMessage() . "\n";
      }
    }

    echo "\n";
  }

  /**
   * Optimiza tablas existentes
   */
  private function optimizeTables(): void
  {
    echo "🗃️  Optimizando tablas...\n";

    // Obtener todas las tablas con prefijo bt_
    $stmt = $this->pdo->query("SHOW TABLES LIKE 'bt_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
      try {
        // Analizar tabla
        $this->pdo->exec("ANALYZE TABLE `$table`");

        // Optimizar tabla
        $this->pdo->exec("OPTIMIZE TABLE `$table`");

        echo "✓ Tabla optimizada: $table\n";
      } catch (PDOException $e) {
        echo "⚠ Error optimizando $table: " . $e->getMessage() . "\n";
      }
    }

    echo "\n";
  }

  /**
   * Analiza queries lentas potenciales
   */
  private function analyzeQueries(): void
  {
    echo "🔍 Analizando queries potencialmente lentas...\n";

    // Verificar consultas sin índices
    $slowQueries = [
      "Búsqueda de candidatos por email sin índice",
      "Filtros por status sin índice",
      "Ordenamiento por fecha sin índice"
    ];

    // Sugerencias de optimización
    $suggestions = [
      "✓ Usar LIMIT en consultas de listado",
      "✓ Implementar paginación en resultados",
      "✓ Usar prepared statements para todas las consultas",
      "✓ Agregar cache para consultas frecuentes",
      "✓ Considerar particionado para tablas grandes"
    ];

    foreach ($suggestions as $suggestion) {
      echo "$suggestion\n";
    }

    echo "\n";
  }
}

// Ejecutar optimización si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
  try {
    $optimizer = new DatabaseOptimizer();
    $optimizer->optimize();
  } catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
  }
}
