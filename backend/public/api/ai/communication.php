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
 * Communication Automation API Endpoint
 *
 * Endpoint para generaciÃƒÆ’Ã‚Â³n automÃƒÆ’Ã‚Â¡tica de emails, comunicaciÃƒÆ’Ã‚Â³n
 * personalizada y automatizaciÃƒÆ’Ã‚Â³n de respuestas.
 *
 * @package Backend\API\AI
 * @version 1.0.0
 * @since 2025-08-10
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/Services/CommunicationService.php';

// Cargar variables de entorno
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

use Services\CommunicationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'MÃƒÆ’Ã‚Â©todo no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos JSON invÃƒÆ’Ã‚Â¡lidos']);
        exit;
    }

    $action = $input['action'] ?? 'generate_email';
    $communicationService = new CommunicationService();

    switch ($action) {
        case 'generate_email':
            // Generar email personalizado
            if (!isset($input['email_type']) || !isset($input['candidate_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan email_type o candidate_data']);
                exit;
            }

            $email = $communicationService->generatePersonalizedEmail(
                $input['email_type'],
                $input['candidate_data'],
                $input['job_data'] ?? [],
                $input['additional_data'] ?? []
            );

            echo json_encode([
                'success' => true,
                'action' => 'generate_email',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'application_response':
            // Respuesta automÃƒÆ’Ã‚Â¡tica a aplicaciÃƒÆ’Ã‚Â³n
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $email = $communicationService->generateApplicationResponse(
                $input['candidate_data'],
                $input['job_data']
            );

            echo json_encode([
                'success' => true,
                'action' => 'application_response',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'rejection_email':
            // Email de rechazo con feedback
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $rejectionReason = $input['rejection_reason'] ?? '';
            $email = $communicationService->generateRejectionEmail(
                $input['candidate_data'],
                $input['job_data'],
                $rejectionReason
            );

            echo json_encode([
                'success' => true,
                'action' => 'rejection_email',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'interview_invitation':
            // InvitaciÃƒÆ’Ã‚Â³n a entrevista
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $interviewDetails = $input['interview_details'] ?? [];
            $email = $communicationService->generateInterviewInvitation(
                $input['candidate_data'],
                $input['job_data'],
                $interviewDetails
            );

            echo json_encode([
                'success' => true,
                'action' => 'interview_invitation',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'follow_up':
            // Email de seguimiento
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data o job_data']);
                exit;
            }

            $stage = $input['stage'] ?? 'general';
            $email = $communicationService->generateFollowUpEmail(
                $input['candidate_data'],
                $input['job_data'],
                $stage
            );

            echo json_encode([
                'success' => true,
                'action' => 'follow_up',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'status_update':
            // ActualizaciÃƒÆ’Ã‚Â³n de estado
            if (!isset($input['candidate_data']) || !isset($input['job_data']) || !isset($input['new_status'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data, job_data o new_status']);
                exit;
            }

            $nextSteps = $input['next_steps'] ?? [];
            $email = $communicationService->generateStatusUpdateEmail(
                $input['candidate_data'],
                $input['job_data'],
                $input['new_status'],
                $nextSteps
            );

            echo json_encode([
                'success' => true,
                'action' => 'status_update',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'generate_templates':
            // Generar templates para el trabajo
            if (!isset($input['job_data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Falta job_data']);
                exit;
            }

            $templates = $communicationService->generateEmailTemplates($input['job_data']);

            echo json_encode([
                'success' => true,
                'action' => 'generate_templates',
                'templates' => $templates,
                'template_count' => count($templates),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'schedule_emails':
            // Programar emails automÃƒÆ’Ã‚Â¡ticos
            if (!isset($input['candidate_data']) || !isset($input['job_data']) || !isset($input['triggers'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidate_data, job_data o triggers']);
                exit;
            }

            $scheduledEmails = $communicationService->scheduleAutomaticEmails(
                $input['candidate_data'],
                $input['job_data'],
                $input['triggers']
            );

            echo json_encode([
                'success' => true,
                'action' => 'schedule_emails',
                'scheduled_emails' => $scheduledEmails,
                'total_scheduled' => count($scheduledEmails),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'bulk_communications':
            // Comunicaciones masivas
            if (!isset($input['candidates']) || !isset($input['job_data']) || !isset($input['email_type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Faltan candidates, job_data o email_type']);
                exit;
            }

            $bulkEmails = [];
            foreach ($input['candidates'] as $candidate) {
                $email = $communicationService->generatePersonalizedEmail(
                    $input['email_type'],
                    $candidate,
                    $input['job_data'],
                    $input['additional_data'] ?? []
                );

                $bulkEmails[] = [
                    'candidate_id' => $candidate['id'] ?? null,
                    'candidate_email' => $candidate['email'] ?? null,
                    'email' => $email
                ];
            }

            echo json_encode([
                'success' => true,
                'action' => 'bulk_communications',
                'bulk_emails' => $bulkEmails,
                'total_emails' => count($bulkEmails),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'AcciÃƒÆ’Ã‚Â³n no vÃƒÆ’Ã‚Â¡lida',
                'valid_actions' => [
                    'generate_email',
                    'application_response',
                    'rejection_email',
                    'interview_invitation',
                    'follow_up',
                    'status_update',
                    'generate_templates',
                    'schedule_emails',
                    'bulk_communications'
                ]
            ]);
            break;
    }
} catch (\Exception $e) {
    error_log('Error en communication endpoint: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

