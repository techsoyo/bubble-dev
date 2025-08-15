<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}
// Test de conexión simple a MySQL remoto
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Iniciando test de conexión a MySQL remoto...\n";

$host = "192.168.1.40";
$port = 3306;
$dbname = "bubble_talents_DB";
$username = "user";
$password = "user123";

echo "📡 Configuración:\n";
echo "   Host: $host:$port\n";
echo "   Base de datos: $dbname\n";
echo "   Usuario: $username\n\n";

try {
  echo "🔌 Intentando conectar...\n";

  // Crear conexión con MySQLi
  $conn = new mysqli($host, $username, $password, $dbname, $port);

  // Verificar conexión
  if ($conn->connect_error) {
    throw new Exception("Error de conexión: " . $conn->connect_error);
  }

  echo "✅ ¡Conexión exitosa!\n";
  echo "🗄️  Info del servidor: " . $conn->server_info . "\n";

  // Probar consulta simple
  echo "\n🔍 Probando consultas...\n";

  // Test tabla bt_jobs
  $result = $conn->query("SELECT COUNT(*) as total FROM bt_jobs");
  if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Tabla bt_jobs: " . $row['total'] . " registros\n";
  } else {
    echo "❌ Error en bt_jobs: " . $conn->error . "\n";
  }

  // Test tabla bt_culture  
  $result = $conn->query("SELECT COUNT(*) as total FROM bt_culture");
  if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Tabla bt_culture: " . $row['total'] . " registros\n";
  } else {
    echo "❌ Error en bt_culture: " . $conn->error . "\n";
  }

  // Test tabla bt_news
  $result = $conn->query("SELECT COUNT(*) as total FROM bt_news");
  if ($result) {
    $row = $result->fetch_assoc();
    echo "✅ Tabla bt_news: " . $row['total'] . " registros\n";
  } else {
    echo "❌ Error en bt_news: " . $conn->error . "\n";
  }

  // Mostrar algunas muestras de datos
  echo "\n📋 Muestra de datos:\n";

  $result = $conn->query("SELECT id, title, location FROM bt_jobs LIMIT 3");
  if ($result && $result->num_rows > 0) {
    echo "🎯 Jobs encontrados:\n";
    while ($row = $result->fetch_assoc()) {
      echo "   - {$row['id']}: {$row['title']} ({$row['location']})\n";
    }
  }

  $result = $conn->query("SELECT id, title FROM bt_culture LIMIT 3");
  if ($result && $result->num_rows > 0) {
    echo "\n🏢 Culture encontrados:\n";
    while ($row = $result->fetch_assoc()) {
      echo "   - {$row['id']}: {$row['title']}\n";
    }
  }

  $result = $conn->query("SELECT id, title FROM bt_news LIMIT 3");
  if ($result && $result->num_rows > 0) {
    echo "\n📰 News encontrados:\n";
    while ($row = $result->fetch_assoc()) {
      echo "   - {$row['id']}: {$row['title']}\n";
    }
  }

  $conn->close();
  echo "\n🎉 Test completado exitosamente!\n";
} catch (Exception $e) {
  echo "💥 ERROR: " . $e->getMessage() . "\n";
  echo "\n🔧 Posibles soluciones:\n";
  echo "   1. Verificar que el contenedor Docker esté ejecutándose\n";
  echo "   2. Verificar credenciales de acceso\n";
  echo "   3. Verificar que MySQL acepte conexiones externas\n";
  echo "   4. Verificar firewall en 192.168.1.40\n";
}
