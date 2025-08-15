<?php
require_once 'config/bootstrap.php';

try {
  $pdo = getDbConnection();
  echo "✅ Conexión establecida\n";

  $query = "SELECT c.id, c.name, c.department_category_id, dc.name as category_name 
              FROM bt_candidates c 
              LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id 
              LIMIT 2";

  echo "🔍 Ejecutando query: $query\n";

  $stmt = $pdo->query($query);
  $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "✅ Query exitosa. Resultados:\n";
  echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "📍 Archivo: " . $e->getFile() . "\n";
  echo "📍 Línea: " . $e->getLine() . "\n";
}
