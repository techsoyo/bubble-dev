<?php declare(strict_types=1);
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

// Proteger solo mÃƒÂ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃƒÂ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
/**
 * Candidate Matching API Endpoint
 *
 * Endpoint para scoring automÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡tico y anÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis de compatibilidad
 * entre candidatos y trabajos usando IA.
 *
 * @package Backend\API\AI
 * @version 1.0.0
 * @since 2025-08-10
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/Services/MatchingService.php';

// Cargar variables de entorno si existe el archivo .env
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

use Services\MatchingService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido']);
    exit;
}

try {
    // Obtener datos JSON del body
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos JSON invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lidos']);
        exit;
    }

    $action = $input['action'] ?? 'single_match';
    $matchingService = new MatchingService();

    switch ($action) {
        case 'single_match':
            // Matching individual candidato vs trabajo
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $result = $matchingService->calculateMatchingScore(
                $input['candidate_data'],
                $input['job_data']
            );

            echo json_encode([
                'success' => true,
                'action' => 'single_match',
                'matching_result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'rank_candidates':
            // Ranking de mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºltiples candidatos
            if (!isset($input['candidates']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidates o job_data']);
                exit;
            }

            $ranking = $matchingService->rankCandidates(
                $input['candidates'],
                $input['job_data']
            );

            echo json_encode([
                'success' => true,
                'action' => 'rank_candidates',
                'ranked_candidates' => $ranking,
                'total_candidates' => count($ranking),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'qualification_analysis':
            // AnÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis de sobre/subcalificaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $analysis = $matchingService->analyzeQualificationFit(
                $input['candidate_data'],
                $input['job_data']
            );

            echo json_encode([
                'success' => true,
                'action' => 'qualification_analysis',
                'qualification_analysis' => $analysis,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'batch_analysis':
            // AnÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis completo: matching + qualification
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $matching = $matchingService->calculateMatchingScore(
                $input['candidate_data'],
                $input['job_data']
            );

            $qualification = $matchingService->analyzeQualificationFit(
                $input['candidate_data'],
                $input['job_data']
            );

            echo json_encode([
                'success' => true,
                'action' => 'batch_analysis',
                'matching_result' => $matching,
                'qualification_analysis' => $qualification,
                'summary' => [
                    'overall_score' => $matching['overall_score'],
                    'recommendation' => $matching['recommendation'],
                    'qualification_level' => $qualification['qualification_level'],
                    'risk_assessment' => $qualification['risk_level']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'AcciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n no vÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lida',
                'valid_actions' => ['single_match', 'rank_candidates', 'qualification_analysis', 'batch_analysis']
            ]);
            break;
    }
} catch (\Exception $e) {
    error_log('Error en matching endpoint: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

