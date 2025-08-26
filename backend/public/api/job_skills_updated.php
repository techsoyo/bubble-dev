<?php declare(strict_types=1);


require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized (cookie required)']);
  exit;
}

header('Content-Type: application/json; charset=UTF-8');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido', 'data' => null]);
    exit;
  }

  // Usar Database singleton para consistencia
  $database = \Utils\Database::getInstance();
  $db = $database->getConnection();
  $jobId = $_GET['jobId'] ?? null;
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
  $offset = ($page - 1) * $limit;

  // Consultar datos reales de la base de datos
  if ($jobId) {
    $stmt = $db->prepare("SELECT id, job_id, skill, level, required, created_at, updated_at 
                             FROM bt_job_skills 
                             WHERE job_id = :jobId 
                             ORDER BY id ASC 
                             LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':jobId', $jobId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  } else {
    $stmt = $db->prepare("SELECT id, job_id, skill, level, required, created_at, updated_at 
                             FROM bt_job_skills 
                             ORDER BY id ASC 
                             LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  }

  $stmt->execute();
  $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Si no hay datos en BD, usar datos de respaldo (solo para desarrollo)
  if (empty($list) && ($_ENV['APP_ENV'] ?? 'production') === 'development') {
    $fallbackData = [
      ['id' => 1, 'job_id' => 1, 'skill' => 'JavaScript', 'level' => 'advanced', 'required' => 1],
      ['id' => 2, 'job_id' => 1, 'skill' => 'React', 'level' => 'intermediate', 'required' => 1],
      ['id' => 3, 'job_id' => 1, 'skill' => 'HTML5', 'level' => 'advanced', 'required' => 1]
    ];
    $list = $jobId ? array_filter($fallbackData, fn($item) => $item['job_id'] == $jobId) : $fallbackData;
  }

  echo json_encode(['ok' => true, 'message' => 'OK', 'data' => $list]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'Error', 'data' => ['error' => $e->getMessage()]]);
}
