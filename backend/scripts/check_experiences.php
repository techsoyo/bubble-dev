<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

try {
  $db = getDBConnection();

  // Verificar si existe la tabla de experiencias
  $tables = $db->query("SHOW TABLES LIKE 'bt_candidate_experiences'")->fetchAll();

  if (empty($tables)) {
    echo "La tabla bt_candidate_experiences no existe.\n";
    echo "Verificando columnas en bt_candidates:\n";

    $stmt = $db->query("DESCRIBE bt_candidates");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
      if (
        strpos($column['Field'], 'experience') !== false ||
        strpos($column['Field'], 'trabajo') !== false ||
        strpos($column['Field'], 'puesto') !== false
      ) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
      }
    }
  } else {
    echo "Tabla bt_candidate_experiences existe.\n";
    echo "Contenido para candidato cnd-203:\n";

    $stmt = $db->prepare("SELECT * FROM bt_candidate_experiences WHERE candidate_id = ?");
    $stmt->execute(['cnd-203']);
    $experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($experiences)) {
      echo "No hay experiencias registradas para el candidato cnd-203.\n";
    } else {
      foreach ($experiences as $exp) {
        echo "- {$exp['job_title']} en {$exp['company_name']} ({$exp['start_date']} - {$exp['end_date']})\n";
      }
    }
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
