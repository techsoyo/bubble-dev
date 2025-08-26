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
 * Pre-Screening API Endpoint
 *
 * Endpoint para screening automÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡tico, generaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de preguntas
 * y detecciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de red flags usando IA.
 *
 * @package Backend\API\AI
 * @version 1.0.0
 * @since 2025-08-10
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/Services/PreScreeningService.php';

use Services\PreScreeningService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos JSON invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lidos']);
        exit;
    }

    $action = $input['action'] ?? 'complete_screening';
    $preScreeningService = new PreScreeningService();

    switch ($action) {
        case 'generate_questions':
            // Generar preguntas personalizadas
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $questionCount = $input['question_count'] ?? 5;
            $questions = $preScreeningService->generateScreeningQuestions(
                $input['candidate_data'],
                $input['job_data'],
                $questionCount
            );

            echo json_encode([
                'success' => true,
                'action' => 'generate_questions',
                'screening_questions' => $questions,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'detect_red_flags':
            // Detectar red flags
            if (!isset($input['candidate_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta candidate_data']);
                exit;
            }

            $redFlags = $preScreeningService->detectRedFlags($input['candidate_data']);

            echo json_encode([
                'success' => true,
                'action' => 'detect_red_flags',
                'red_flags_analysis' => $redFlags,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'validate_requirements':
            // Validar requisitos mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­nimos
            if (!isset($input['candidate_data']) || !isset($input['job_requirements'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_requirements']);
                exit;
            }

            $validation = $preScreeningService->validateMinimumRequirements(
                $input['candidate_data'],
                $input['job_requirements']
            );

            echo json_encode([
                'success' => true,
                'action' => 'validate_requirements',
                'requirements_validation' => $validation,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'complete_screening':
            // Screening completo
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $completeResults = $preScreeningService->performCompleteScreening(
                $input['candidate_data'],
                $input['job_data']
            );

            echo json_encode([
                'success' => true,
                'action' => 'complete_screening',
                'screening_results' => $completeResults,
                'summary' => [
                    'screening_score' => $completeResults['screening_score'],
                    'recommendation' => $completeResults['recommendation']['decision'],
                    'red_flags_count' => count($completeResults['red_flags_analysis']['red_flags']),
                    'requirements_met' => $completeResults['requirements_validation']['percentage_met'] ?? 0
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'AcciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n no vÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lida',
                'valid_actions' => ['generate_questions', 'detect_red_flags', 'validate_requirements', 'complete_screening']
            ]);
            break;
    }
} catch (\Exception $e) {
    error_log('Error en pre-screening endpoint: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

