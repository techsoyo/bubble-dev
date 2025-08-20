<?php

declare(strict_types=1);

// 1. Cargo el bootstrap que pone los headers CORS
$BOOT = dirname(__DIR__, 2) . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// 2. Usar Database singleton para consistencia
require_once dirname(__DIR__, 2) . '/src/Utils/Database.php';

function db()
{
  static $pdo = null;
  if ($pdo === null) {
    try {
      $database = \Utils\Database::getInstance();
      $pdo = $database->getConnection();
    } catch (Exception $e) {
      error_log('Database connection failed in jobs.php: ' . $e->getMessage());
      http_response_code(500);
      echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
      ]);
      exit;
    }
  }
  return $pdo;
}

// 3. Devuelvo los trabajos
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
  $limit  = (int)($_GET['limit'] ?? 10);
  $offset = (int)($_GET['offset'] ?? 0);

  try {
    $sql = "SELECT * FROM bt_jobs WHERE status='open' ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($jobs as $row) {
      $result[] = [
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
      'data'    => $result
    ]);
    exit;
  } catch (Exception $e) {
    error_log('Error in jobs.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'Database query failed',
      'error' => $e->getMessage()
    ]);
    exit;
  }
}

// 4. Si no es GET, devuelvo error
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
