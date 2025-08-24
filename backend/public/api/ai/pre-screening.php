<?php

/**
 * Pre-Screening API Endpoint
 *
 * Endpoint para screening automÃƒÂ¡tico, generaciÃƒÂ³n de preguntas
 * y detecciÃƒÂ³n de red flags usando IA.
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
    echo json_encode(['error' => 'MÃƒÂ©todo no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos JSON invÃƒÂ¡lidos']);
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
            // Validar requisitos mÃƒÂ­nimos
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
                'error' => 'AcciÃƒÂ³n no vÃƒÂ¡lida',
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
