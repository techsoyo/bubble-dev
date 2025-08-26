<?php declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use Security\CsrfMiddleware;
use Src\Models\JobSkillModel;

// JWTMiddleware usa alias global del bootstrap
JWTMiddleware::requireAuth();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
  CsrfMiddleware::protect();
}

$model = new JobSkillModel();

header('Content-Type: application/json');

switch ($method) {
  case 'GET':
    $jobId = (int)($_GET['job_id'] ?? 0);
    if (!$jobId) {
      http_response_code(400);
      echo json_encode(['error' => 'job_id requerido']);
      exit;
    }
    // Usar findBy para filtrar por job_id
    $results = $model->findBy('job_id', $jobId);
    echo json_encode(['data' => $results]);
    break;

  case 'POST':
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $jobId = (int)($payload['job_id'] ?? 0);
    $skill = trim($payload['skill'] ?? '');
    $level = trim($payload['level'] ?? '');
    $required = (bool)($payload['required'] ?? false);
    if (!$jobId || $skill === '') {
      http_response_code(422);
      echo json_encode(['error' => 'job_id y skill son obligatorios']);
      exit;
    }
    $id = $model->store(['job_id' => $jobId, 'skill' => $skill, 'level' => $level, 'required' => $required]);
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
