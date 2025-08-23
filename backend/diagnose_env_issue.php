<?php

/**
 * SCRIPT DE DIAGNÓSTICO COMPLETO
 * Diagnostica problemas de variables de entorno entre CLI y servidor web embebido
 */

echo "🔍 DIAGNÓSTICO COMPLETO DE VARIABLES DE ENTORNO\n";
echo str_repeat("=", 60) . "\n\n";

echo "📋 INFORMACIÓN DEL ENTORNO:\n";
echo "- PHP SAPI: " . PHP_SAPI . "\n";
echo "- PHP Version: " . PHP_VERSION . "\n";
echo "- Working Directory: " . getcwd() . "\n";
echo "- Script Path: " . __FILE__ . "\n\n";

// Cargar configuración paso a paso
echo "📂 PASO 1: CARGANDO ARCHIVOS DE CONFIGURACIÓN\n";

$configPath = __DIR__ . '/config/config.php';
echo "- config.php: " . ($configPath) . " - " . (file_exists($configPath) ? "✅ EXISTE" : "❌ NO EXISTE") . "\n";

if (file_exists($configPath)) {
  require_once $configPath;
  echo "- config.php cargado: ✅\n";

  $envPath = __DIR__ . '/.env';
  echo "- .env path: " . $envPath . " - " . (file_exists($envPath) ? "✅ EXISTE" : "❌ NO EXISTE") . "\n";

  echo "\n📋 PASO 2: VERIFICANDO CONTENIDO DEL ARCHIVO .env\n";
  if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    echo "- Total líneas en .env: " . count($envLines) . "\n";

    foreach ($envLines as $lineNum => $line) {
      $line = trim($line);
      if (empty($line) || $line[0] === '#') continue;

      if (strpos($line, 'DB_') === 0) {
        echo "  Línea " . ($lineNum + 1) . ": " . $line . "\n";
      }
    }
  }

  echo "\n📋 PASO 3: EJECUTANDO loadEnvironmentVars()\n";
  $result = loadEnvironmentVars();
  echo "- Resultado de loadEnvironmentVars(): " . ($result ? "✅ TRUE" : "❌ FALSE") . "\n";
} else {
  echo "❌ No se puede cargar config.php\n";
}

echo "\n📋 PASO 4: VERIFICANDO VARIABLES DE ENTORNO DESPUÉS DE LA CARGA\n";

$dbVars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PASSWORD'];

foreach ($dbVars as $var) {
  $getenvValue = getenv($var);
  $envValue = $_ENV[$var] ?? 'NO SET';
  $serverValue = $_SERVER[$var] ?? 'NO SET';

  echo "- {$var}:\n";
  echo "  getenv(): " . ($getenvValue === false ? 'FALSE' : "'{$getenvValue}'") . "\n";
  echo "  \$_ENV: " . $envValue . "\n";
  echo "  \$_SERVER: " . $serverValue . "\n\n";
}

echo "📋 PASO 5: VERIFICANDO FUNCIÓN config()\n";
if (function_exists('config')) {
  echo "- Función config() existe: ✅\n";

  $testVars = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_PASSWORD'];
  foreach ($testVars as $var) {
    $configValue = config($var, 'DEFAULT_NOT_FOUND');
    echo "- config('{$var}'): '{$configValue}'\n";
  }
} else {
  echo "- Función config() NO existe: ❌\n";
}

echo "\n📋 PASO 6: SIMULANDO CONEXIÓN A BASE DE DATOS\n";

try {
  require_once __DIR__ . '/config/database.php';
  echo "- database.php cargado: ✅\n";

  if (function_exists('getDbConnection')) {
    echo "- Función getDbConnection() existe: ✅\n";
    echo "- Intentando conexión...\n";

    $pdo = getDbConnection();
    echo "- ✅ CONEXIÓN EXITOSA!\n";

    // Test simple
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "- Test query: " . ($result['test'] == 1 ? "✅ OK" : "❌ FAIL") . "\n";
  } else {
    echo "- Función getDbConnection() NO existe: ❌\n";
  }
} catch (Exception $e) {
  echo "- ❌ ERROR DE CONEXIÓN: " . $e->getMessage() . "\n";
  echo "- Error Code: " . $e->getCode() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏁 DIAGNÓSTICO COMPLETADO\n";
