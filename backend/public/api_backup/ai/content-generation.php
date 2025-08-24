<?php

/**
 * API Endpoint: Content Generation & Predictive Analysis
 *
 * Endpoint unificado para generación automática de contenido y análisis predictivo.
 * Incluye job descriptions, preguntas de entrevista, análisis de éxito, tiempo de contratación.
 *
 * @package Backend\API\AI
 * @version 1.0.0
 * @since 2025-08-10
 */

declare(strict_types=1);
$ROOT = dirname(__DIR__, 2);             // ai -> api -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

require_once __DIR__ . '/../../src/Services/ContentGenerationService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Método no permitido',
        'message' => 'Este endpoint solo acepta POST requests'
    ]);
    exit();
}

try {
    // Leer input JSON
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido en request body');
    }

    // Validar acción requerida
    if (!isset($inputData['action'])) {
        throw new Exception('Campo "action" es requerido');
    }

    // Inicializar servicio
    $contentService = new \Services\ContentGenerationService();
    $action = $inputData['action'];

    // Enrutamiento por acción
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
            throw new Exception("Acción no válida: {$action}");
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
        'error' => 'Error en generación de contenido',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Maneja generación de job description
 */
function handleJobDescriptionGeneration($service, $inputData)
{
    if (!isset($inputData['job_inputs'])) {
        throw new Exception('Campo "job_inputs" es requerido para generar job description');
    }

    $jobInputs = $inputData['job_inputs'];

    // Validaciones básicas
    if (empty($jobInputs['title'])) {
        throw new Exception('Título del trabajo es requerido');
    }

    $result = $service->generateJobDescription($jobInputs);

    return [
        'job_description' => $result,
        'input_data' => $jobInputs,
        'generation_type' => 'automated_job_description'
    ];
}

/**
 * Maneja generación de preguntas de entrevista
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
            'name' => $candidateData['nombre'] ?? 'Anónimo',
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
 * Maneja predicción de éxito laboral
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
 * Maneja estimación de tiempo de contratación
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
 * Maneja generación de templates de sourcing
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
 * Maneja análisis de diversidad
 */
function handleDiversityAnalysis($service, $inputData)
{
    if (!isset($inputData['candidates_data'])) {
        throw new Exception('Campo "candidates_data" es requerido');
    }

    $candidatesData = $inputData['candidates_data'];
    $diversityMetrics = $inputData['diversity_metrics'] ?? ['experience', 'education', 'background'];

    if (!is_array($candidatesData) || empty($candidatesData)) {
        throw new Exception('Se requiere al menos un candidato para el análisis');
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
 * Maneja generación masiva de contenido
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
                    throw new Exception("Tipo no válido: {$request['type']}");
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
