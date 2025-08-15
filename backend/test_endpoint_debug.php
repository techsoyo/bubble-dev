<?php
// Simular contexto HTTP
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8000';

require_once 'config/bootstrap.php';

echo "=== TEST ENDPOINT CANDIDATES ===\n";

try {
  // Simular el código exacto del endpoint
  $pdo = getDbConnection();
  echo "✅ Conexión establecida\n";

  // Código exacto del endpoint
  $limit = 50;
  $offset = 0;
  $whereConditions = [];
  $params = [':limit' => $limit, ':offset' => $offset];

  $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

  $stmt = $pdo->prepare("
SELECT c.*,
d.name as department_name,
dc.name as department_category_name,
GROUP_CONCAT(DISTINCT cs.skill) as skills
FROM bt_candidates c
LEFT JOIN bt_departments d ON c.department_id = d.id
LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
LEFT JOIN bt_candidate_skills cs ON c.id = cs.candidate_id
{$whereClause}
GROUP BY c.id, d.name, dc.name
ORDER BY c.created_at DESC
LIMIT :limit OFFSET :offset
");

  echo "✅ Query preparada\n";

  // Bind parameters con tipos específicos
  foreach ($params as $key => $value) {
    if ($key === ':limit' || $key === ':offset') {
      $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
      $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
  }

  echo "✅ Parámetros vinculados\n";

  $stmt->execute();
  echo "✅ Query ejecutada\n";

  $candidates = $stmt->fetchAll();
  echo "✅ Datos obtenidos: " . count($candidates) . " candidatos\n";

  if (count($candidates) > 0) {
    echo "📊 Primer candidato: " . json_encode($candidates[0], JSON_PRETTY_PRINT) . "\n";
  }
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  echo "📍 Código: " . $e->getCode() . "\n";
  echo "📍 Archivo: " . $e->getFile() . "\n";
  echo "📍 Línea: " . $e->getLine() . "\n";

  // Información adicional de PDO
  if (isset($pdo)) {
    echo "📍 PDO Error Info: " . json_encode($pdo->errorInfo()) . "\n";
  }

  if (isset($stmt)) {
    echo "📍 Statement Error Info: " . json_encode($stmt->errorInfo()) . "\n";
  }
}
