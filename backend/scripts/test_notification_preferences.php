<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__);
$BOOT = $ROOT . '/config/bootstrap.php';
require_once $BOOT;

try {
  $db = getDBConnection();

  // Verificar si la tabla existe
  $stmt = $db->query("SHOW TABLES LIKE 'bt_notification_preferences'");
  $tableExists = $stmt->fetchColumn();

  if (!$tableExists) {
    echo "Tabla bt_notification_preferences no existe.\n";
    echo "Creando tabla...\n";

    $sql = "CREATE TABLE bt_notification_preferences (
            id VARCHAR(50) PRIMARY KEY,
            candidate_id VARCHAR(50) NOT NULL,
            application_updates BOOLEAN DEFAULT TRUE,
            new_jobs BOOLEAN DEFAULT FALSE,
            reminders BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (candidate_id) REFERENCES bt_candidates(id)
        )";

    $db->exec($sql);
    echo "Tabla creada exitosamente.\n";
  } else {
    echo "Tabla bt_notification_preferences existe.\n";

    // Mostrar estructura
    $stmt = $db->query("DESCRIBE bt_notification_preferences");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Estructura de la tabla:\n";
    foreach ($columns as $column) {
      echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
