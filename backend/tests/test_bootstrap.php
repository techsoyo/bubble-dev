<?php
echo "PHP está funcionando correctamente\n";
echo "Directorio actual: " . __DIR__ . "\n";
echo "BASE_PATH definido: " . (defined('BASE_PATH') ? BASE_PATH : 'NO DEFINIDO') . "\n";

// Verificar si se puede cargar el bootstrap
try {
  require_once __DIR__ . '/config/bootstrap.php';
  echo "Bootstrap cargado exitosamente\n";

  // Verificar variables de entorno
  echo "CORS_ALLOWED_ORIGINS: " . getenv('CORS_ALLOWED_ORIGINS') . "\n";
  echo "DB_HOST: " . getenv('DB_HOST') . "\n";
} catch (Exception $e) {
  echo "Error cargando bootstrap: " . $e->getMessage() . "\n";
}
