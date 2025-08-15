<?php
// Test de conexión a la base de datos con la nueva configuración

require_once __DIR__ . '/config/bootstrap.php';

echo "=== TEST DE CONEXIÓN A LA BASE DE DATOS ===\n\n";

try {
  // Cargar variables de entorno
  loadEnvironmentVars();

  echo "Variables de entorno cargadas:\n";
  echo "DB_HOST: " . config('DB_HOST', 'no definido') . "\n";
  echo "DB_PORT: " . config('DB_PORT', 'no definido') . "\n";
  echo "DB_NAME: " . config('DB_NAME', 'no definido') . "\n";
  echo "DB_USER: " . config('DB_USER', 'no definido') . "\n\n";

  // Intentar conexión
  $pdo = getDbConnection();
  echo "✅ Conexión exitosa a la base de datos\n\n";

  // Probar consulta simple
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
  $result = $stmt->fetch();
  echo "📊 Número de aplicaciones en la BD: " . $result['total'] . "\n";

  $stmt = $pdo->query("SELECT COUNT(*) as total FROM candidates");
  $result = $stmt->fetch();
  echo "👥 Número de candidatos en la BD: " . $result['total'] . "\n";

  $stmt = $pdo->query("SELECT COUNT(*) as total FROM jobs");
  $result = $stmt->fetch();
  echo "💼 Número de trabajos en la BD: " . $result['total'] . "\n\n";

  echo "✅ Test completado exitosamente\n";
} catch (Exception $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
