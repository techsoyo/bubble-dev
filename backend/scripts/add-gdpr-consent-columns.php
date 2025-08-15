<?php

/**
 * Script para agregar columnas de consentimiento GDPR a la tabla bt_candidates
 * 
 * CRÍTICO PARA CUMPLIMIENTO GDPR: Registrar cuándo y qué consentimientos se dieron
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

  echo "🛡️ GDPR COMPLIANCE: Agregando columnas de consentimiento...\n\n";

  // Verificar estructura actual de bt_candidates
  echo "📋 Verificando estructura actual de bt_candidates...\n";
  $stmt = $pdo->query("DESCRIBE bt_candidates");
  $currentColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

  // Columnas GDPR que necesitamos agregar
  $gdprColumns = [
    'gdpr_consent_given' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN gdpr_consent_given BOOLEAN NOT NULL DEFAULT FALSE',
      'description' => 'Consentimiento general GDPR aceptado'
    ],
    'gdpr_consent_date' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN gdpr_consent_date DATETIME NULL',
      'description' => 'Fecha y hora del consentimiento GDPR'
    ],
    'openai_processing_consent' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN openai_processing_consent BOOLEAN NOT NULL DEFAULT FALSE',
      'description' => 'Consentimiento específico procesamiento OpenAI'
    ],
    'openai_consent_date' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN openai_consent_date DATETIME NULL',
      'description' => 'Fecha y hora consentimiento OpenAI'
    ],
    'data_processing_purposes' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN data_processing_purposes JSON NULL',
      'description' => 'JSON con propósitos específicos del tratamiento aceptados'
    ],
    'consent_version' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN consent_version VARCHAR(50) DEFAULT "1.0"',
      'description' => 'Versión de la política de privacidad aceptada'
    ],
    'ip_address_consent' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN ip_address_consent VARCHAR(45) NULL',
      'description' => 'IP desde donde se dio el consentimiento (auditoría)'
    ],
    'user_agent_consent' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN user_agent_consent TEXT NULL',
      'description' => 'User Agent del navegador (auditoría)'
    ],
    'consent_withdrawn_date' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN consent_withdrawn_date DATETIME NULL',
      'description' => 'Fecha si el usuario retiró el consentimiento'
    ],
    'data_retention_until' => [
      'sql' => 'ALTER TABLE bt_candidates ADD COLUMN data_retention_until DATETIME NULL',
      'description' => 'Fecha límite de retención de datos'
    ]
  ];

  // Agregar cada columna si no existe
  foreach ($gdprColumns as $columnName => $columnInfo) {
    if (in_array($columnName, $currentColumns)) {
      echo "⚠️  Columna '{$columnName}' ya existe - omitiendo\n";
      continue;
    }

    try {
      $pdo->exec($columnInfo['sql']);
      echo "✅ Agregada: {$columnName} - {$columnInfo['description']}\n";
    } catch (PDOException $e) {
      echo "❌ Error agregando {$columnName}: " . $e->getMessage() . "\n";
    }
  }

  // Verificar que todas las columnas se agregaron correctamente
  echo "\n🔍 Verificando columnas GDPR agregadas...\n";
  $stmt = $pdo->query("DESCRIBE bt_candidates");
  $newColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $gdprColumnsAdded = 0;
  foreach ($newColumns as $column) {
    if (array_key_exists($column['Field'], $gdprColumns)) {
      echo "✅ {$column['Field']} ({$column['Type']}) - {$column['Default']}\n";
      $gdprColumnsAdded++;
    }
  }

  echo "\n🎯 Resumen:\n";
  echo "📊 Total columnas GDPR definidas: " . count($gdprColumns) . "\n";
  echo "✅ Columnas GDPR presentes en tabla: {$gdprColumnsAdded}\n";

  if ($gdprColumnsAdded >= 8) {
    echo "\n🛡️ ¡CUMPLIMIENTO GDPR MEJORADO!\n";
    echo "📋 La tabla bt_candidates ahora puede registrar:\n";
    echo "   - Consentimientos específicos con timestamps\n";
    echo "   - Propósitos del tratamiento aceptados\n";
    echo "   - Información de auditoría (IP, User Agent)\n";
    echo "   - Gestión de retención de datos\n";
    echo "   - Tracking de retirada de consentimiento\n";
  }

  // Crear índices para optimizar consultas GDPR
  echo "\n🔧 Creando índices optimizados para consultas GDPR...\n";

  $indexes = [
    "CREATE INDEX IF NOT EXISTS idx_gdpr_consent ON bt_candidates(gdpr_consent_given, gdpr_consent_date)",
    "CREATE INDEX IF NOT EXISTS idx_openai_consent ON bt_candidates(openai_processing_consent, openai_consent_date)",
    "CREATE INDEX IF NOT EXISTS idx_data_retention ON bt_candidates(data_retention_until)"
  ];

  foreach ($indexes as $indexSql) {
    try {
      $pdo->exec($indexSql);
      echo "✅ Índice creado correctamente\n";
    } catch (PDOException $e) {
      echo "⚠️  Índice ya existe o error: " . $e->getMessage() . "\n";
    }
  }
} catch (Exception $e) {
  echo "❌ Error crítico: " . $e->getMessage() . "\n";
  exit(1);
}

echo "\n🎉 ¡Script de columnas GDPR completado exitosamente!\n";
echo "🚨 PRÓXIMO PASO: Actualizar save-candidate.php para registrar consentimientos\n";
