<?php
// test_chatbot_data.php
declare(strict_types=1);
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}
$ROOT = __DIR__;
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
  // Conexión a la base de datos
  require_once __DIR__ . '/config/config.php';

  $host = config('DB_HOST');
  $dbname = config('DB_NAME');
  $username = config('DB_USER');
  $password = config('DB_PASS');

  $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
  $pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Obtener nodos
  $stmt = $pdo->query('SELECT * FROM bt_chatbot_nodes ORDER BY created_at');
  $nodes = $stmt->fetchAll();

  // Obtener opciones  
  $stmt = $pdo->query('SELECT * FROM bt_chatbot_options ORDER BY order_position');
  $options = $stmt->fetchAll();

  // Respuesta
  echo json_encode([
    'success' => true,
    'data' => [
      'nodes' => $nodes,
      'options' => $options,
      'total_nodes' => count($nodes),
      'total_options' => count($options)
    ]
  ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
