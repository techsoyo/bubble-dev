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
/**
 * API Endpoint: Recruitment Insights & Analytics
 *
 * Endpoint para anÃƒÆ’Ã‚Â¡lisis avanzado de mÃƒÆ’Ã‚Â©tricas de reclutamiento,
 * insights del mercado laboral, optimizaciÃƒÆ’Ã‚Â³n de procesos y reportes ejecutivos.
 *
 * @package Backend\API\AI
 * @version 1.0.0
 * @since 2025-08-10
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/Services/RecruitmentInsightsService.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'MÃƒÆ’Ã‚Â©todo no permitido',
        'message' => 'Este endpoint solo acepta POST requests'
    ]);
    exit();
}

try {
    // Leer input JSON
    $inputData = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON invÃƒÆ’Ã‚Â¡lido en request body');
    }

    // Validar acciÃƒÆ’Ã‚Â³n requerida
    if (!isset($inputData['action'])) {
        throw new Exception('Campo "action" es requerido');
    }

    // Inicializar servicio
    $insightsService = new \Services\RecruitmentInsightsService();
    $action = $inputData['action'];

    // Enrutamiento por acciÃƒÆ’Ã‚Â³n
    switch ($action) {
        case 'analyze_pipeline':
            $result = handlePipelineAnalysis($insightsService, $inputData);
            break;

        case 'optimize_process':
            $result = handleProcessOptimization($insightsService, $inputData);
            break;

        case 'analyze_candidate_quality':
            $result = handleCandidateQualityAnalysis($insightsService, $inputData);
            break;

        case 'predict_future_needs':
            $result = handleFutureNeedsPrediction($insightsService, $inputData);
            break;

        case 'analyze_competitiveness':
            $result = handleCompetitivenessAnalysis($insightsService, $inputData);
            break;

        case 'generate_executive_report':
            $result = handleExecutiveReportGeneration($insightsService, $inputData);
            break;

        case 'comprehensive_insights':
            $result = handleComprehensiveInsights($insightsService, $inputData);
            break;

        default:
            throw new Exception("AcciÃƒÆ’Ã‚Â³n no vÃƒÆ’Ã‚Â¡lida: {$action}");
    }

    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'action' => $action,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s'),
        'service' => 'RecruitmentInsightsService v1.0'
    ]);
} catch (Exception $e) {
    error_log('Error en Recruitment Insights API: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'error' => 'Error en anÃƒÆ’Ã‚Â¡lisis de insights',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Maneja anÃƒÆ’Ã‚Â¡lisis de pipeline
 */
function handlePipelineAnalysis($service, $inputData)
{
    if (!isset($inputData['pipeline_data'])) {
        throw new Exception('Campo "pipeline_data" es requerido');
    }

    $pipelineData = $inputData['pipeline_data'];
    $historicalData = $inputData['historical_data'] ?? [];

    // Validaciones bÃƒÆ’Ã‚Â¡sicas
    if (!isset($pipelineData['total_candidates'])) {
        throw new Exception('Total de candidatos es requerido en pipeline_data');
    }

    $result = $service->analyzePipeline($pipelineData, $historicalData);

    return [
        'pipeline_analysis' => $result,
        'analysis_scope' => [
            'total_candidates' => $pipelineData['total_candidates'],
            'active_positions' => $pipelineData['active_positions'] ?? 0,
            'historical_data_available' => !empty($historicalData)
        ],
        'analysis_type' => 'ai_powered_pipeline_insights'
    ];
}

/**
 * Maneja optimizaciÃƒÆ’Ã‚Â³n de procesos
 */
function handleProcessOptimization($service, $inputData)
{
    if (!isset($inputData['process_data'])) {
        throw new Exception('Campo "process_data" es requerido');
    }

    $processData = $inputData['process_data'];
    $bottlenecks = $inputData['bottlenecks'] ?? [];

    $result = $service->optimizeRecruitmentProcess($processData, $bottlenecks);

    return [
        'process_optimization' => $result,
        'optimization_scope' => [
            'stages_analyzed' => count($processData['stages'] ?? []),
            'bottlenecks_provided' => count($bottlenecks),
            'automation_level' => $processData['automation_level'] ?? 'unknown'
        ],
        'optimization_type' => 'ai_driven_process_improvement'
    ];
}

/**
 * Maneja anÃƒÆ’Ã‚Â¡lisis de calidad de candidatos
 */
function handleCandidateQualityAnalysis($service, $inputData)
{
    if (!isset($inputData['candidates_data'])) {
        throw new Exception('Campo "candidates_data" es requerido');
    }

    $candidatesData = $inputData['candidates_data'];
    $qualityMetrics = $inputData['quality_metrics'] ?? ['experience', 'skills', 'education'];

    if (!is_array($candidatesData) || empty($candidatesData)) {
        throw new Exception('Se requiere al menos un candidato para el anÃƒÆ’Ã‚Â¡lisis');
    }

    $result = $service->analyzeCandidateQuality($candidatesData, $qualityMetrics);

    return [
        'quality_analysis' => $result,
        'analysis_scope' => [
            'candidates_analyzed' => count($candidatesData),
            'metrics_evaluated' => $qualityMetrics,
            'data_completeness' => calculateDataCompleteness($candidatesData)
        ],
        'analysis_type' => 'candidate_quality_intelligence'
    ];
}

/**
 * Maneja predicciÃƒÆ’Ã‚Â³n de necesidades futuras
 */
function handleFutureNeedsPrediction($service, $inputData)
{
    if (!isset($inputData['organization_data'])) {
        throw new Exception('Campo "organization_data" es requerido');
    }

    $organizationData = $inputData['organization_data'];
    $growthPlans = $inputData['growth_plans'] ?? [];

    $result = $service->predictFutureNeeds($organizationData, $growthPlans);

    return [
        'future_needs_prediction' => $result,
        'prediction_scope' => [
            'organization_size' => $organizationData['current_size'] ?? 'not_specified',
            'departments_analyzed' => count($organizationData['departments'] ?? []),
            'growth_plans_available' => !empty($growthPlans)
        ],
        'prediction_type' => 'ai_workforce_planning'
    ];
}

/**
 * Maneja anÃƒÆ’Ã‚Â¡lisis de competitividad
 */
function handleCompetitivenessAnalysis($service, $inputData)
{
    if (!isset($inputData['position_data'])) {
        throw new Exception('Campo "position_data" es requerido');
    }

    $positionData = $inputData['position_data'];
    $marketData = $inputData['market_data'] ?? [];

    // ValidaciÃƒÆ’Ã‚Â³n bÃƒÆ’Ã‚Â¡sica
    if (empty($positionData['title'])) {
        throw new Exception('TÃƒÆ’Ã‚Â­tulo de la posiciÃƒÆ’Ã‚Â³n es requerido');
    }

    $result = $service->analyzeCompetitiveness($positionData, $marketData);

    return [
        'competitiveness_analysis' => $result,
        'analysis_scope' => [
            'position_title' => $positionData['title'],
            'salary_provided' => !empty($positionData['salary_range']),
            'benefits_count' => count($positionData['benefits'] ?? []),
            'market_data_available' => !empty($marketData)
        ],
        'analysis_type' => 'market_competitiveness_intelligence'
    ];
}

/**
 * Maneja generaciÃƒÆ’Ã‚Â³n de reportes ejecutivos
 */
function handleExecutiveReportGeneration($service, $inputData)
{
    if (!isset($inputData['recruitment_metrics'])) {
        throw new Exception('Campo "recruitment_metrics" es requerido');
    }

    $recruitmentMetrics = $inputData['recruitment_metrics'];
    $reportPeriod = $inputData['report_period'] ?? 'monthly';

    $result = $service->generateExecutiveReport($recruitmentMetrics, $reportPeriod);

    return [
        'executive_report' => $result,
        'report_scope' => [
            'period' => $reportPeriod,
            'metrics_included' => array_keys($recruitmentMetrics),
            'data_points' => count($recruitmentMetrics),
            'generated_for' => 'executive_leadership'
        ],
        'report_type' => 'ai_generated_executive_summary'
    ];
}

/**
 * Maneja anÃƒÆ’Ã‚Â¡lisis comprehensivo de insights
 */
function handleComprehensiveInsights($service, $inputData)
{
    $results = [];
    $errors = [];

    // AnÃƒÆ’Ã‚Â¡lisis de pipeline si hay datos
    if (isset($inputData['pipeline_data'])) {
        try {
            $results['pipeline_analysis'] = $service->analyzePipeline(
                $inputData['pipeline_data'],
                $inputData['historical_data'] ?? []
            );
        } catch (Exception $e) {
            $errors['pipeline_analysis'] = $e->getMessage();
        }
    }

    // AnÃƒÆ’Ã‚Â¡lisis de calidad si hay datos de candidatos
    if (isset($inputData['candidates_data'])) {
        try {
            $results['quality_analysis'] = $service->analyzeCandidateQuality(
                $inputData['candidates_data'],
                $inputData['quality_metrics'] ?? []
            );
        } catch (Exception $e) {
            $errors['quality_analysis'] = $e->getMessage();
        }
    }

    // OptimizaciÃƒÆ’Ã‚Â³n de proceso si hay datos
    if (isset($inputData['process_data'])) {
        try {
            $results['process_optimization'] = $service->optimizeRecruitmentProcess(
                $inputData['process_data'],
                $inputData['bottlenecks'] ?? []
            );
        } catch (Exception $e) {
            $errors['process_optimization'] = $e->getMessage();
        }
    }

    // Reporte ejecutivo si hay mÃƒÆ’Ã‚Â©tricas
    if (isset($inputData['recruitment_metrics'])) {
        try {
            $results['executive_report'] = $service->generateExecutiveReport(
                $inputData['recruitment_metrics'],
                $inputData['report_period'] ?? 'monthly'
            );
        } catch (Exception $e) {
            $errors['executive_report'] = $e->getMessage();
        }
    }

    if (empty($results)) {
        throw new Exception('No se pudieron generar insights. Verifique los datos proporcionados.');
    }

    return [
        'comprehensive_insights' => $results,
        'analysis_errors' => $errors,
        'insights_generated' => count($results),
        'analysis_type' => 'full_recruitment_intelligence_suite'
    ];
}

/**
 * Calcula completeness de datos de candidatos
 */
function calculateDataCompleteness($candidatesData)
{
    $totalFields = 0;
    $completedFields = 0;

    foreach ($candidatesData as $candidate) {
        $fields = ['nombre', 'puestos_anteriores', 'hard_skills', 'educacion', 'ubicacion_actual'];
        foreach ($fields as $field) {
            $totalFields++;
            if (!empty($candidate[$field])) {
                $completedFields++;
            }
        }
    }

    return $totalFields > 0 ? round(($completedFields / $totalFields) * 100, 2) : 0;
}

