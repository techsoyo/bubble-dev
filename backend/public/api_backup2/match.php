<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Sube 1 nivel: api â†’ backend/
require_once $BOOT;

/**
 * Endpoint: POST /api/match
 * Objetivo: Calcular scores de matching entre candidatos y trabajos usando IA
 *
 * Input: {
 *   "candidates": [candidate_data_array],
 *   "job": job_data_object,
 *   "feature_flags": {"ai_matching_enabled": true}
 * }
 *
 * Output: {
 *   "success": true,
 *   "data": {
 *     "ranked_candidates": [...],
 *     "job_id": string,
 *     "matching_timestamp": iso_date
 *   },
 *   "meta": {
 *     "ai_provider": "openai|ollama|fallback",
 *     "total_candidates": number,
 *     "processing_time_ms": number
 *   }
 * }
 */

use Utils\Cors;
use Utils\Log;
use Utils\RequestId;
use Services\MatchingService;

$start_time = microtime(true);

// CORS ya configurado en bootstrap.php - no necesario
// if (!$isCli) {
//     Cors::handleRequest();
// }

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

// Validar Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Content-Type must be application/json']);
  exit;
}

try {
  // Leer y validar input JSON
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
  }

  // Validar campos requeridos
  if (!isset($data['candidates']) || !isset($data['job'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: candidates, job']);
    exit;
  }

  if (!is_array($data['candidates']) || empty($data['candidates'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'candidates must be a non-empty array']);
    exit;
  }

  // Feature flags (con valores por defecto seguros)
  $featureFlags = $data['feature_flags'] ?? [];
  $aiMatchingEnabled = ($featureFlags['ai_matching_enabled'] ?? false) === true;

  // Log inicio del proceso
  Log::json('info', [
    'endpoint' => '/api/match',
    'req_id' => RequestId::get(),
    'candidates_count' => count($data['candidates']),
    'job_id' => $data['job']['id'] ?? 'unknown',
    'ai_matching_enabled' => $aiMatchingEnabled
  ]);

  // Instanciar servicio de matching
  $matchingService = new MatchingService();

  // Procesar matching
  if ($aiMatchingEnabled) {
    // Matching con IA
    $rankedCandidates = $matchingService->rankCandidates($data['candidates'], $data['job']);
    $provider = 'ai';
  } else {
    // Matching bÃ¡sico (fallback) - usando createFallbackScore para cada candidato
    $rankedCandidates = [];
    foreach ($data['candidates'] as $candidate) {
      $fallbackScore = $matchingService->createFallbackScore($candidate, $data['job']);
      $rankedCandidates[] = [
        'candidate_id' => $candidate['id'] ?? null,
        'candidate_data' => $candidate,
        'matching_score' => $fallbackScore['overall_score'],
        'matching_analysis' => $fallbackScore,
        'ranking_timestamp' => date('Y-m-d H:i:s')
      ];
    }

    // Ordenar por score descendente
    usort($rankedCandidates, function ($a, $b) {
      return $b['matching_score'] <=> $a['matching_score'];
    });

    $provider = 'fallback';
  }

  // Calcular tiempo de procesamiento
  $processingTime = round((microtime(true) - $start_time) * 1000, 2);

  // Respuesta exitosa
  $response = [
    'success' => true,
    'data' => [
      'ranked_candidates' => $rankedCandidates,
      'job_id' => $data['job']['id'] ?? null,
      'matching_timestamp' => date('c'), // ISO 8601
      'feature_flags_applied' => [
        'ai_matching_enabled' => $aiMatchingEnabled
      ]
    ],
    'meta' => [
      'ai_provider' => $provider,
      'total_candidates' => count($rankedCandidates),
      'processing_time_ms' => $processingTime,
      'request_id' => RequestId::get()
    ]
  ];

  // Log Ã©xito
  Log::json('info', [
    'endpoint' => '/api/match',
    'req_id' => RequestId::get(),
    'result' => 'success',
    'processing_time_ms' => $processingTime,
    'top_score' => $rankedCandidates[0]['matching_score'] ?? null
  ]);

  http_response_code(200);
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  // Log error
  Log::json('error', [
    'endpoint' => '/api/match',
    'req_id' => RequestId::get(),
    'error' => $e->getMessage(),
    'trace' => APP_ENV === 'development' ? $e->getTraceAsString() : null
  ]);

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Internal server error',
    'debug' => APP_ENV === 'development' ? $e->getMessage() : null
  ]);
}

