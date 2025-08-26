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
 * API Endpoint: Content Generation & Predictive Analysis
 *
 * Endpoint unificado para generaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n automÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡tica de contenido y anÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis predictivo.
 * Incluye job descriptions, preguntas de entrevista, anÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis de ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©xito, tiempo de contrataciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n.
 *
 * @package Backend\API\AI
 * @version 1.0.0
 * @since 2025-08-10
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/Services/ContentGenerationService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido',
        'message' => 'Este endpoint solo acepta POST requests'
    ]);
    exit();
}

try {
    // Leer input JSON
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lido en request body');
    }

    // Validar acciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n requerida
    if (!isset($inputData['action'])) {
        throw new Exception('Campo "action" es requerido');
    }

    // Inicializar servicio
    $contentService = new \Services\ContentGenerationService();
    $action = $inputData['action'];

    // Enrutamiento por acciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n
    switch ($action) {
        case 'generate_job_description':
            $result = handleJobDescriptionGeneration($contentService, $inputData);
            break;

        case 'generate_interview_questions':
            $result = handleInterviewQuestionsGeneration($contentService, $inputData);
            break;

        case 'predict_job_success':
            $result = handleJobSuccessPrediction($contentService, $inputData);
            break;

        case 'estimate_time_to_fill':
            $result = handleTimeToFillEstimation($contentService, $inputData);
            break;

        case 'generate_sourcing_templates':
            $result = handleSourcingTemplatesGeneration($contentService, $inputData);
            break;

        case 'analyze_diversity':
            $result = handleDiversityAnalysis($contentService, $inputData);
            break;

        case 'bulk_content_generation':
            $result = handleBulkContentGeneration($contentService, $inputData);
            break;

        default:
            throw new Exception("AcciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n no vÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lida: {$action}");
    }

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'action' => $action,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s'),
        'service' => 'ContentGenerationService v1.0'
    ]);
} catch (Exception $e) {
    error_log('Error en Content Generation API: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'error' => 'Error en generaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de contenido',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Maneja generaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de job description
 */
function handleJobDescriptionGeneration($service, $inputData)
{
    if (!isset($inputData['job_inputs'])) {
        throw new Exception('Campo "job_inputs" es requerido para generar job description');
    }

    $jobInputs = $inputData['job_inputs'];

    // Validaciones bÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡sicas
    if (empty($jobInputs['title'])) {
        throw new Exception('TÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â­tulo del trabajo es requerido');
    }

    $result = $service->generateJobDescription($jobInputs);

    return [
        'job_description' => $result,
        'input_data' => $jobInputs,
        'generation_type' => 'automated_job_description'
    ];
}

/**
 * Maneja generaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de preguntas de entrevista
 */
function handleInterviewQuestionsGeneration($service, $inputData)
{
    if (!isset($inputData['candidate_data']) || !isset($inputData['job_data'])) {
        throw new Exception('Campos "candidate_data" y "job_data" son requeridos');
    }

    $candidateData = $inputData['candidate_data'];
    $jobData = $inputData['job_data'];
    $interviewType = $inputData['interview_type'] ?? 'general';

    $result = $service->generateInterviewQuestions($candidateData, $jobData, $interviewType);

    return [
        'interview_questions' => $result,
        'candidate_info' => [
            'name' => $candidateData['nombre'] ?? 'AnÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³nimo',
            'experience_years' => count($candidateData['puestos_anteriores'] ?? [])
        ],
        'job_info' => [
            'title' => $jobData['title'] ?? 'No especificado',
            'level' => $jobData['level'] ?? 'No especificado'
        ],
        'interview_type' => $interviewType
    ];
}

/**
 * Maneja predicciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©xito laboral
 */
function handleJobSuccessPrediction($service, $inputData)
{
    if (!isset($inputData['candidate_data']) || !isset($inputData['job_data'])) {
        throw new Exception('Campos "candidate_data" y "job_data" son requeridos');
    }

    $candidateData = $inputData['candidate_data'];
    $jobData = $inputData['job_data'];
    $historicalData = $inputData['historical_data'] ?? [];

    $result = $service->predictJobSuccess($candidateData, $jobData, $historicalData);

    return [
        'success_prediction' => $result,
        'analysis_factors' => [
            'experience_match' => !empty($candidateData['puestos_anteriores']),
            'skills_match' => !empty($candidateData['hard_skills']),
            'education_provided' => !empty($candidateData['educacion']),
            'historical_data_available' => !empty($historicalData)
        ],
        'prediction_type' => 'ai_powered_analysis'
    ];
}

/**
 * Maneja estimaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de tiempo de contrataciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n
 */
function handleTimeToFillEstimation($service, $inputData)
{
    if (!isset($inputData['job_data'])) {
        throw new Exception('Campo "job_data" es requerido');
    }

    $jobData = $inputData['job_data'];
    $marketData = $inputData['market_data'] ?? [];

    $result = $service->estimateTimeToFill($jobData, $marketData);

    return [
        'time_estimation' => $result,
        'job_complexity' => [
            'skills_required' => count($jobData['required_skills'] ?? []),
            'location_specified' => !empty($jobData['location']),
            'salary_competitive' => !empty($jobData['salary_range'])
        ],
        'estimation_type' => 'market_analysis'
    ];
}

/**
 * Maneja generaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de templates de sourcing
 */
function handleSourcingTemplatesGeneration($service, $inputData)
{
    if (!isset($inputData['job_data'])) {
        throw new Exception('Campo "job_data" es requerido');
    }

    $jobData = $inputData['job_data'];
    $sourceType = $inputData['source_type'] ?? 'linkedin';

    $result = $service->generateSourceingTemplates($jobData, $sourceType);

    return [
        'sourcing_templates' => $result,
        'target_platform' => $sourceType,
        'job_title' => $jobData['title'] ?? 'No especificado',
        'template_count' => count($result['templates'] ?? [])
    ];
}

/**
 * Maneja anÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis de diversidad
 */
function handleDiversityAnalysis($service, $inputData)
{
    if (!isset($inputData['candidates_data'])) {
        throw new Exception('Campo "candidates_data" es requerido');
    }

    $candidatesData = $inputData['candidates_data'];
    $diversityMetrics = $inputData['diversity_metrics'] ?? ['experience', 'education', 'background'];

    if (!is_array($candidatesData) || empty($candidatesData)) {
        throw new Exception('Se requiere al menos un candidato para el anÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lisis');
    }

    $result = $service->analyzeDiversity($candidatesData, $diversityMetrics);

    return [
        'diversity_analysis' => $result,
        'analyzed_candidates' => count($candidatesData),
        'metrics_evaluated' => $diversityMetrics,
        'analysis_scope' => 'candidate_pipeline'
    ];
}

/**
 * Maneja generaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n masiva de contenido
 */
function handleBulkContentGeneration($service, $inputData)
{
    if (!isset($inputData['bulk_requests']) || !is_array($inputData['bulk_requests'])) {
        throw new Exception('Campo "bulk_requests" debe ser un array');
    }

    $bulkRequests = $inputData['bulk_requests'];
    $results = [];
    $errors = [];

    foreach ($bulkRequests as $index => $request) {
        try {
            if (!isset($request['type'])) {
                throw new Exception("Request {$index}: 'type' es requerido");
            }

            switch ($request['type']) {
                case 'job_description':
                    $results[$index] = $service->generateJobDescription($request['data']);
                    break;

                case 'interview_questions':
                    $results[$index] = $service->generateInterviewQuestions(
                        $request['candidate_data'],
                        $request['job_data'],
                        $request['interview_type'] ?? 'general'
                    );
                    break;

                case 'success_prediction':
                    $results[$index] = $service->predictJobSuccess(
                        $request['candidate_data'],
                        $request['job_data'],
                        $request['historical_data'] ?? []
                    );
                    break;

                default:
                    throw new Exception("Tipo no vÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lido: {$request['type']}");
            }
        } catch (Exception $e) {
            $errors[$index] = $e->getMessage();
        }
    }

    return [
        'bulk_results' => $results,
        'bulk_errors' => $errors,
        'total_requests' => count($bulkRequests),
        'successful_requests' => count($results),
        'failed_requests' => count($errors),
        'processing_type' => 'bulk_generation'
    ];
}

