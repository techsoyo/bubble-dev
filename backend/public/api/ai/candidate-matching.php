<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;
use Utils\ResponseHelper as Res;

// Autenticación: cookie HttpOnly obligatoria
JWTMiddleware::requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Protege solo métodos que cambian estado (double-submit cookie)
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect();
}

// En producción NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    Res::error('Unauthorized (cookie required)', null, 401);
}

// Este endpoint acepta solo POST
if ($method !== 'POST') {
    Res::fail('Método no permitido', 405);
}

// Mantener compatibilidad con lectura previa de .env si existe (no obligatorio)
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Leer y validar JSON de entrada usando ResponseHelper
$input = Res::getJsonInput();
if (!is_array($input)) {
    Res::fail('JSON inválido o no enviado', 400);
}

$action = $input['action'] ?? 'single_match';

// Instanciar servicio (fallback si hay distintas implementaciones)
$matchingService = null;
if (class_exists(\Services\MatchingService::class)) {
    $matchingService = new \Services\MatchingService();
} elseif (class_exists(\Services\Matching\JobMatchingService::class)) {
    $matchingService = new \Services\Matching\JobMatchingService();
} else {
    Res::error('Servicio de matching no disponible', null, 500);
}

try {
    $start = microtime(true);

    switch ($action) {
        case 'single_match':
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidate_data o job_data', 400);
            }

            $result = $matchingService->calculateMatchingScore(
                $input['candidate_data'],
                $input['job_data']
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            $payload = [
                'action' => 'single_match',
                'matching_result' => $result,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ];

            Res::success('single_match', $payload);
            break;

        case 'rank_candidates':
            if (!isset($input['candidates']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidates o job_data', 400);
            }

            $ranking = $matchingService->rankCandidates(
                $input['candidates'],
                $input['job_data']
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            $payload = [
                'action' => 'rank_candidates',
                'ranked_candidates' => $ranking,
                'total_candidates' => is_array($ranking) ? count($ranking) : 0,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ];

            Res::success('rank_candidates', $payload);
            break;

        case 'qualification_analysis':
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidate_data o job_data', 400);
            }

            $analysis = $matchingService->analyzeQualificationFit(
                $input['candidate_data'],
                $input['job_data']
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            $payload = [
                'action' => 'qualification_analysis',
                'qualification_analysis' => $analysis,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ];

            Res::success('qualification_analysis', $payload);
            break;

        case 'batch_analysis':
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidate_data o job_data', 400);
            }

            $matching = $matchingService->calculateMatchingScore(
                $input['candidate_data'],
                $input['job_data']
            );

            $qualification = $matchingService->analyzeQualificationFit(
                $input['candidate_data'],
                $input['job_data']
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            $summary = [
                'overall_score' => $matching['overall_score'] ?? null,
                'recommendation' => $matching['recommendation'] ?? null,
                'qualification_level' => $qualification['qualification_level'] ?? null,
                'risk_assessment' => $qualification['risk_level'] ?? null,
            ];

            $payload = [
                'action' => 'batch_analysis',
                'matching_result' => $matching,
                'qualification_analysis' => $qualification,
                'summary' => $summary,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ];

            Res::success('batch_analysis', $payload);
            break;

        default:
            Res::fail('Acción no válida', 400, ['valid_actions' => ['single_match', 'rank_candidates', 'qualification_analysis', 'batch_analysis']]);
            break;
    }
} catch (\Throwable $e) {
    // Log structured error
    Res::log('error', 'candidate-matching error', [
        'endpoint' => basename(__FILE__),
        'action' => $action ?? null,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    Res::error('Error interno del servidor', $e, 500);
}
