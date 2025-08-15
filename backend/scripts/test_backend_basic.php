#!/usr/bin/env php
<?php
/**
 * Script de prueba para verificar funcionalidades básicas del backend
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "🧪 Iniciando pruebas del backend...\n\n";

// Test 1: Conexión a base de datos
echo "1️⃣  Probando conexión a base de datos...\n";
try {
  require_once __DIR__ . '/../config/database.php';
  $pdo = getDbConnection();
  echo "   ✅ Conexión a base de datos: OK\n";
} catch (Exception $e) {
  echo "   ❌ Error en base de datos: " . $e->getMessage() . "\n";
}

// Test 2: Verificar configuración
echo "\n2️⃣  Verificando configuración...\n";
echo "   📂 APP_ENV: " . ($_ENV['APP_ENV'] ?? 'no definido') . "\n";
echo "   🔑 JWT_SECRET: " . (isset($_ENV['JWT_SECRET']) ? 'configurado' : 'no configurado') . "\n";
echo "   🤖 OPENAI_API_KEY: " . (isset($_ENV['OPENAI_API_KEY']) ? 'configurado' : 'no configurado') . "\n";

// Test 3: Verificar autoload
echo "\n3️⃣  Verificando autoload...\n";
try {
  // Intentar cargar una clase del proyecto
  if (class_exists('Services\\CVParsingService')) {
    echo "   ✅ Autoload: OK\n";
  } else {
    echo "   ⚠️  Autoload: Algunas clases no se encuentran (normal si no están implementadas)\n";
  }
} catch (Exception $e) {
  echo "   ❌ Error en autoload: " . $e->getMessage() . "\n";
}

// Test 4: Verificar permisos de escritura
echo "\n4️⃣  Verificando permisos...\n";
$storageDir = __DIR__ . '/../storage/logs';
if (is_writable($storageDir)) {
  echo "   ✅ Permisos de escritura en storage: OK\n";
} else {
  echo "   ❌ Sin permisos de escritura en storage\n";
}

$uploadsDir = __DIR__ . '/../uploads';
if (is_writable($uploadsDir)) {
  echo "   ✅ Permisos de escritura en uploads: OK\n";
} else {
  echo "   ❌ Sin permisos de escritura en uploads\n";
}

echo "\n🏁 Pruebas completadas.\n";
echo "\n💡 Para probar el servidor web, ejecuta:\n";
echo "   cd backend && php -S localhost:8000 public/index.php\n";
echo "   Luego visita: http://localhost:8000/api/test.php\n";
