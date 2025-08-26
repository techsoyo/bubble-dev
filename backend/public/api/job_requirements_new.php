<?php declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
JWTMiddleware::requireAuth();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  CsrfMiddleware::protect();
}

use Src\Models\JobRequirementModel;

$model = new JobRequirementModel(getDbConnection());

header('Content-Type: application/json');

switch ($method) {
  case 'GET':
    $jobId = (int)($_GET['job_id'] ?? 0);
    if (!$jobId) {
      http_response_code(400);
      echo json_encode(['error' => 'job_id requerido']);
      exit;
    }
    // BaseModel: add where/filter method si la tienes; si no, usa consulta directa
    $stmt = $model->getPdo()->prepare("SELECT id, job_id, requirement, created_at, updated_at FROM bt_job_requirements WHERE job_id = :job_id ORDER BY id");
    $stmt->execute(['job_id' => $jobId]);
    echo json_encode(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    break;

  case 'POST':
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId = (int)($payload['job_id'] ?? 0);
    $req   = trim($payload['requirement'] ?? '');
    if (!$jobId || $req === '') {
      http_response_code(422);
      echo json_encode(['error' => 'job_id y requirement son obligatorios']);
      exit;
    }
    $id = $model->createJobRequirement(['job_id' => $jobId, 'requirement' => $req]); // o insert manual si no tienes método
    echo json_encode(['id' => $id, 'ok' => true]); // 201 si prefieres
    break;

  case 'DELETE':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
      http_response_code(400);
      echo json_encode(['error' => 'id requerido']);
      exit;
    }
    $ok = $model->delete($id);
    echo json_encode(['ok' => (bool)$ok]);
    break;

  default:
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
