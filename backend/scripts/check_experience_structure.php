<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

try {
  $db = getDBConnection();

  echo "Estructura de la tabla bt_candidate_experiences:\n";
  echo "=============================================\n";

  $stmt = $db->query("DESCRIBE bt_candidate_experiences");
  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($columns as $column) {
    echo $column['Field'] . " - " . $column['Type'] . " - " . $column['Null'] . " - " . $column['Default'] . "\n";
  }

  echo "\nContenido para candidato cnd-203:\n";
  echo "================================\n";

  $stmt = $db->prepare("SELECT * FROM bt_candidate_experiences WHERE candidate_id = ?");
  $stmt->execute(['cnd-203']);
  $experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($experiences)) {
    echo "No hay experiencias registradas.\n";
  } else {
    foreach ($experiences as $i => $exp) {
      echo "Experiencia " . ($i + 1) . ":\n";
      foreach ($exp as $field => $value) {
        echo "  $field: $value\n";
      }
      echo "\n";
    }
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
