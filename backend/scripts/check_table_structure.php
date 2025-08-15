<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

try {
  $db = getDBConnection();

  // Mostrar estructura de la tabla
  $stmt = $db->query("DESCRIBE bt_candidates");
  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Estructura de la tabla bt_candidates:\n";
  echo "=====================================\n";
  foreach ($columns as $column) {
    echo $column['Field'] . " - " . $column['Type'] . " - " . $column['Null'] . " - " . $column['Default'] . "\n";
  }

  echo "\nBuscando candidato por email:\n";
  // Buscar candidato sin incluir password
  $stmt = $db->prepare("SELECT * FROM bt_candidates WHERE email = ?");
  $stmt->execute(['raul.campos@example.com']);
  $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($candidate) {
    echo "Candidato encontrado:\n";
    foreach ($candidate as $field => $value) {
      echo "$field: $value\n";
    }
  } else {
    echo "Candidato no encontrado con email raul.campos@example.com\n";
  }
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
