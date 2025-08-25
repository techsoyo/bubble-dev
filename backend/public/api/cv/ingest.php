<?php


require_once __DIR__ . '/../bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
declare(strict_types=1);


/**
 * Endpoint: POST /api/cv/ingest
 * Pipeline completo: CV Upload ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Parse ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Match ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Route
 * 
 * Input: multipart/form-data con archivo 'cv' + metadata JSON
 * Output: candidato procesado, score, y ruteo automÃƒÆ’Ã‚Â¡tico
 */

use Services\CV\AdvancedCVParser;
use Services\MatchingService;
use Utils\Log;
use Utils\RequestId;

$start_time = microtime(true);

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

try {
  // Validar archivo CV
  if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('CV file required');
  }

  $file = $_FILES['cv'];
  if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
    throw new Exception('File too large (max 5MB)');
  }

  // Extraer metadata JSON
  $metadata = json_decode($_POST['metadata'] ?? '{}', true);
  $jobId = $metadata['job_id'] ?? null;
  $aiEnabled = ($metadata['ai_enabled'] ?? false) === true;

  // PASO 1: Parse CV
  $cvText = file_get_contents($file['tmp_name']);
  $parser = new AdvancedCVParser($cvText);
  $candidateData = $parser->analyzeCV();

  // PASO 2: Match si hay job_id
  $matchScore = null;
  if ($jobId && $aiEnabled) {
    $jobData = ['id' => $jobId, 'required_skills' => ['React', 'JavaScript']]; // Mock job
    $matcher = new MatchingService();
    $matchScore = $matcher->createFallbackScore($candidateData, $jobData);
  }

  // PASO 3: Guardar candidato (mock)
  $candidateId = 'cand_' . uniqid();

  // PASO 4: Ruteo automÃƒÆ’Ã‚Â¡tico (mock)
  $assignedRecruiter = $candidateData['categoria'] === 'Frontend Developer' ? 'recruiter_frontend' : 'recruiter_general';

  // Response
  $processingTime = round((microtime(true) - $start_time) * 1000, 2);

  echo json_encode([
    'success' => true,
    'data' => [
      'candidate_id' => $candidateId,
      'parsed_data' => $candidateData,
      'match_score' => $matchScore['overall_score'] ?? null,
      'assigned_recruiter' => $assignedRecruiter,
      'processing_steps' => [
        'parse' => 'completed',
        'match' => $jobId ? 'completed' : 'skipped',
        'route' => 'completed'
      ]
    ],
    'meta' => [
      'processing_time_ms' => $processingTime,
      'ai_enabled' => $aiEnabled,
      'request_id' => RequestId::get()
    ]
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'debug' => $e->getTraceAsString()
  ]);
}

