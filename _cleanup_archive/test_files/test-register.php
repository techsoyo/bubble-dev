<?php
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método no permitido']);
  exit;
}

try {
  $input = json_decode(file_get_contents('php://input'), true);
  $candidateData = $input['candidate_data'] ?? [];
  $jobId = $input['job_id'] ?? null;

  $pdo = getDbConnection();

  // Crear candidato
  $candidateId = 'test-' . time();
  $defaultPassword = password_hash('temporal123', PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT INTO bt_candidates (id, first_name, last_name, name, email, password_hash, status, registration_source, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', 'web_application', NOW())");
  $stmt->execute([$candidateId, 'Test', 'User', $candidateData['nombre'] ?? 'Test Name', $candidateData['email'] ?? 'test@example.com', $defaultPassword]);
  $applicationId = null;
  if ($jobId) {
    $applicationId = 'app-' . time();
    $stmt = $pdo->prepare("INSERT INTO bt_applications (id, job_id, candidate_id, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$applicationId, $jobId, $candidateId]);
  }

  echo json_encode([
    'success' => true,
    'data' => ['candidate_id' => $candidateId],
    'application' => $applicationId ? ['application_id' => $applicationId, 'job_id' => $jobId] : null
  ]);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
