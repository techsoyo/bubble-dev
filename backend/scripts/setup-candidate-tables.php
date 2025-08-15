<?php

/**
 * Script para verificar y crear tablas necesarias para candidatos
 * 
 * @package Backend\Scripts
 * @version 1.0.0
 * @since 2025-08-10
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';

try {
  // Usar la función de conexión existente
  $pdo = getDbConnection();

  echo "✅ Conexión a base de datos exitosa\n";  // Verificar tablas existentes
  $stmt = $pdo->query("SHOW TABLES");
  $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

  echo "\n📋 Tablas existentes en la base de datos:\n";
  foreach ($tables as $table) {
    echo "  - {$table}\n";
  }

  // Definir tablas necesarias
  $requiredTables = [
    'bt_candidates' => "
            CREATE TABLE IF NOT EXISTS bt_candidates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                nombre VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                telefono VARCHAR(50),
                ubicacion_actual VARCHAR(255),
                fecha_nacimiento DATE NULL,
                portfolio VARCHAR(500) NULL,
                linkedin VARCHAR(500) NULL,
                otras_redes JSON NULL,
                resumen_profesional TEXT NULL,
                soft_skills JSON NULL,
                hard_skills JSON NULL,
                idiomas JSON NULL,
                intereses JSON NULL,
                referencias TEXT NULL,
                disponibilidad VARCHAR(255) NULL,
                certificaciones JSON NULL,
                cv_original_file VARCHAR(255) NULL,
                cv_text_file VARCHAR(255) NULL,
                cv_json_file VARCHAR(255) NULL,
                data_source ENUM('ai_processing', 'manual_entry', 'hybrid') DEFAULT 'manual_entry',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

    'bt_candidate_experience' => "
            CREATE TABLE IF NOT EXISTS bt_candidate_experience (
                id INT AUTO_INCREMENT PRIMARY KEY,
                candidate_id INT NOT NULL,
                puesto VARCHAR(255) NOT NULL,
                empresa VARCHAR(255) NOT NULL,
                fecha_inicio DATE NULL,
                fecha_fin DATE NULL,
                descripcion TEXT NULL,
                responsabilidades JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (candidate_id) REFERENCES bt_candidates(id) ON DELETE CASCADE,
                INDEX idx_candidate_id (candidate_id),
                INDEX idx_fecha_inicio (fecha_inicio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

    'bt_candidate_education' => "
            CREATE TABLE IF NOT EXISTS bt_candidate_education (
                id INT AUTO_INCREMENT PRIMARY KEY,
                candidate_id INT NOT NULL,
                titulo VARCHAR(255) NOT NULL,
                institucion VARCHAR(255) NOT NULL,
                fecha_inicio DATE NULL,
                fecha_fin DATE NULL,
                descripcion TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (candidate_id) REFERENCES bt_candidates(id) ON DELETE CASCADE,
                INDEX idx_candidate_id (candidate_id),
                INDEX idx_fecha_inicio (fecha_inicio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

    'bt_candidate_projects' => "
            CREATE TABLE IF NOT EXISTS bt_candidate_projects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                candidate_id INT NOT NULL,
                nombre VARCHAR(255) NOT NULL,
                descripcion TEXT NULL,
                tecnologias JSON NULL,
                fecha_inicio DATE NULL,
                fecha_fin DATE NULL,
                url VARCHAR(500) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (candidate_id) REFERENCES bt_candidates(id) ON DELETE CASCADE,
                INDEX idx_candidate_id (candidate_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
  ];

  echo "\n🔧 Verificando estructura de tablas existentes...\n";

  // Verificar estructura de bt_candidates
  if (in_array('bt_candidates', $tables)) {
    echo "  📊 Analizando estructura de bt_candidates...\n";
    $stmt = $pdo->query("DESCRIBE bt_candidates");
    $columns = $stmt->fetchAll();

    $candidateColumns = array_column($columns, 'Field');
    echo "    Columnas existentes: " . implode(', ', $candidateColumns) . "\n";

    // Verificar si ya tiene las columnas que necesitamos
    $neededColumns = [
      'resumen_profesional' => 'ALTER TABLE bt_candidates ADD COLUMN resumen_profesional TEXT NULL',
      'soft_skills' => 'ALTER TABLE bt_candidates ADD COLUMN soft_skills JSON NULL',
      'hard_skills' => 'ALTER TABLE bt_candidates ADD COLUMN hard_skills JSON NULL',
      'idiomas' => 'ALTER TABLE bt_candidates ADD COLUMN idiomas JSON NULL',
      'intereses' => 'ALTER TABLE bt_candidates ADD COLUMN intereses JSON NULL',
      'referencias' => 'ALTER TABLE bt_candidates ADD COLUMN referencias TEXT NULL',
      'disponibilidad' => 'ALTER TABLE bt_candidates ADD COLUMN disponibilidad VARCHAR(255) NULL',
      'certificaciones' => 'ALTER TABLE bt_candidates ADD COLUMN certificaciones JSON NULL',
      'cv_original_file' => 'ALTER TABLE bt_candidates ADD COLUMN cv_original_file VARCHAR(255) NULL',
      'cv_text_file' => 'ALTER TABLE bt_candidates ADD COLUMN cv_text_file VARCHAR(255) NULL',
      'cv_json_file' => 'ALTER TABLE bt_candidates ADD COLUMN cv_json_file VARCHAR(255) NULL',
      'data_source' => 'ALTER TABLE bt_candidates ADD COLUMN data_source ENUM("ai_processing", "manual_entry", "hybrid") DEFAULT "manual_entry"'
    ];

    foreach ($neededColumns as $column => $alterSQL) {
      if (!in_array($column, $candidateColumns)) {
        echo "    🔨 Agregando columna {$column}...\n";
        try {
          $pdo->exec($alterSQL);
          echo "    ✅ Columna {$column} agregada\n";
        } catch (Exception $e) {
          echo "    ⚠️  Error agregando {$column}: " . $e->getMessage() . "\n";
        }
      } else {
        echo "    ✅ Columna {$column} ya existe\n";
      }
    }
  }

  // Verificar y crear tablas relacionadas si no existen con nombres alternativos
  $relatedTables = [
    'bt_candidate_experience' => [
      'alternative' => 'bt_candidate_experiences',
      'sql' => "
                CREATE TABLE IF NOT EXISTS bt_candidate_experience (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    candidate_id INT NOT NULL,
                    puesto VARCHAR(255) NOT NULL,
                    empresa VARCHAR(255) NOT NULL,
                    fecha_inicio DATE NULL,
                    fecha_fin DATE NULL,
                    descripcion TEXT NULL,
                    responsabilidades JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_candidate_id (candidate_id),
                    INDEX idx_fecha_inicio (fecha_inicio)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
    ],

    'bt_candidate_projects' => [
      'alternative' => null,
      'sql' => "
                CREATE TABLE IF NOT EXISTS bt_candidate_projects (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    candidate_id INT NOT NULL,
                    nombre VARCHAR(255) NOT NULL,
                    descripcion TEXT NULL,
                    tecnologias JSON NULL,
                    fecha_inicio DATE NULL,
                    fecha_fin DATE NULL,
                    url VARCHAR(500) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_candidate_id (candidate_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
    ]
  ];

  echo "\n🔧 Verificando tablas relacionadas...\n";

  foreach ($relatedTables as $tableName => $config) {
    $exists = in_array($tableName, $tables);
    $altExists = $config['alternative'] ? in_array($config['alternative'], $tables) : false;

    if ($exists) {
      echo "  ✅ Tabla {$tableName} ya existe\n";
    } elseif ($altExists) {
      echo "  ✅ Tabla alternativa {$config['alternative']} ya existe (usar esta)\n";
    } else {
      echo "  🔨 Creando tabla {$tableName}...\n";
      try {
        $pdo->exec($config['sql']);
        echo "  ✅ Tabla {$tableName} creada exitosamente\n";
      } catch (Exception $e) {
        echo "  ⚠️  Error creando {$tableName}: " . $e->getMessage() . "\n";
      }
    }
  }  // Verificar estructura de la tabla usuarios si existe
  if (in_array('usuarios', $tables)) {
    echo "\n👤 Verificando estructura de tabla usuarios...\n";
    $stmt = $pdo->query("DESCRIBE usuarios");
    $columns = $stmt->fetchAll();

    $userColumns = array_column($columns, 'Field');
    echo "  Columnas encontradas: " . implode(', ', $userColumns) . "\n";

    // Verificar si tiene las columnas necesarias
    $requiredUserColumns = ['id', 'username', 'email', 'password_hash', 'role'];
    $missingColumns = array_diff($requiredUserColumns, $userColumns);

    if (empty($missingColumns)) {
      echo "  ✅ Tabla usuarios tiene todas las columnas necesarias\n";
    } else {
      echo "  ⚠️  Faltan columnas en usuarios: " . implode(', ', $missingColumns) . "\n";
    }
  } else {
    echo "\n⚠️  Tabla usuarios no encontrada. Puedes necesitar crearla para autenticación.\n";
  }

  echo "\n🎉 Verificación y setup de base de datos completado!\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}
