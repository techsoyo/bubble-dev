<?php

declare(strict_types=1);

// 1. Cargo el bootstrap que pone los headers CORS
$BOOT = dirname(__DIR__, 2) . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// 2. Funciones para hablar con la base de datos
function db()
{
  static $conn = null;
  if ($conn === null) {
    $conn = new mysqli(
      $_ENV['DB_HOST'] ?? 'localhost',
      $_ENV['DB_USER'] ?? 'root',
      $_ENV['DB_PASS'] ?? '',
      $_ENV['DB_NAME'] ?? 'bubble_talents',
      (int)($_ENV['DB_PORT'] ?? 3306)
    );
    if ($conn->connect_error) {
      exit('DB error');
    }
  }
  return $conn;
}

// 3. Devuelvo los trabajos
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
  $limit  = (int)($_GET['limit'] ?? 10);
  $offset = (int)($_GET['offset'] ?? 0);

  $sql = "SELECT * FROM bt_jobs WHERE status='open' ORDER BY created_at DESC LIMIT ? OFFSET ?";
  $stmt = db()->prepare($sql);
  $stmt->bind_param('ii', $limit, $offset);
  $stmt->execute();
  $res = $stmt->get_result();

  $jobs = [];
  while ($row = $res->fetch_assoc()) {
    $jobs[] = [
      'id'   => $row['id'],
      'title' => $row['title'],
      'company' => $row['company_name'] ?? '',
      'location' => $row['location'] ?? '',
      'description' => $row['description'] ?? ''
    ];
  }

  header('Content-Type: application/json');
  echo json_encode([
    'success' => true,
    'data'    => $jobs
  ]);
  exit;
}

// 4. Si no es GET, devuelvo error
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
