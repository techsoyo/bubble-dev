<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}
try {
  echo "=== DIAGNÓSTICO DE AUTH.PHP ===\n";

  echo "1. Verificando CORS...\n";
  require_once __DIR__ . '/cors.php';
  echo "✅ CORS cargado correctamente\n";

  echo "2. Verificando database.php...\n";
  require_once __DIR__ . '/config/database.php';
  echo "✅ database.php cargado correctamente\n";

  echo "3. Verificando conexión DB...\n";
  $db = getDbConnection();
  echo "✅ Conexión DB establecida\n";

  echo "4. Verificando input-sanitizer.php...\n";
  require_once __DIR__ . '/config/input-sanitizer.php';
  echo "✅ input-sanitizer.php cargado correctamente\n";

  echo "5. Verificando User model...\n";
  require_once __DIR__ . '/src/Models/BaseModel.php';
  require_once __DIR__ . '/src/Models/User.php';
  echo "✅ User model cargado correctamente\n";
  echo "6. Verificando JWT utils...\n";
  require_once __DIR__ . '/src/Utils/JWT.php';
  echo "✅ JWT utils cargado correctamente\n";

  echo "\n🎉 TODAS LAS DEPENDENCIAS ESTÁN OK\n";
} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
  echo "Archivo: " . $e->getFile() . "\n";
  echo "Línea: " . $e->getLine() . "\n";
}
