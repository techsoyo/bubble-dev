<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;
use Utils\ResponseHelper as Res;

// Auth
JWTMiddleware::requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect();
}

// En producción solo cookie
if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    Res::error('Unauthorized (cookie required)', null, 401);
}

// Este endpoint acepta solo POST
if ($method !== 'POST') {
    Res::fail('Metodo no permitido', 405);
}

// Mantener compatibilidad con require_once local si el servicio no está en autoload
if (file_exists(__DIR__ . '/../../src/Services/CommunicationService.php')) {
    require_once __DIR__ . '/../../src/Services/CommunicationService.php';
}

// Leer y validar JSON
$input = Res::getJsonInput();
if (!is_array($input)) {
    Res::fail('Datos JSON inválidos', 400);
}

$action = $input['action'] ?? 'generate_email';

// Instanciar servicio con fallback
$communicationService = null;
if (class_exists(\Services\CommunicationService::class)) {
    $communicationService = new \Services\CommunicationService();
} else {
    Res::error('Servicio de comunicación no disponible', null, 500);
}

try {
    $start = microtime(true);

    switch ($action) {
        case 'generate_email':
            if (!isset($input['email_type']) || !isset($input['candidate_data'])) {
                Res::fail('Faltan email_type o candidate_data', 400);
            }

            $email = $communicationService->generatePersonalizedEmail(
                $input['email_type'],
                $input['candidate_data'],
                $input['job_data'] ?? [],
                $input['additional_data'] ?? []
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('generate_email', [
                'action' => 'generate_email',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'application_response':
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidate_data o job_data', 400);
            }

            $email = $communicationService->generateApplicationResponse(
                $input['candidate_data'],
                $input['job_data']
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('application_response', [
                'action' => 'application_response',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'rejection_email':
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidate_data o job_data', 400);
            }

            $rejectionReason = $input['rejection_reason'] ?? '';
            $email = $communicationService->generateRejectionEmail(
                $input['candidate_data'],
                $input['job_data'],
                $rejectionReason
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('rejection_email', [
                'action' => 'rejection_email',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'interview_invitation':
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidate_data o job_data', 400);
            }

            $interviewDetails = $input['interview_details'] ?? [];
            $email = $communicationService->generateInterviewInvitation(
                $input['candidate_data'],
                $input['job_data'],
                $interviewDetails
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('interview_invitation', [
                'action' => 'interview_invitation',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'follow_up':
            if (!isset($input['candidate_data']) || !isset($input['job_data'])) {
                Res::fail('Faltan candidate_data o job_data', 400);
            }

            $stage = $input['stage'] ?? 'general';
            $email = $communicationService->generateFollowUpEmail(
                $input['candidate_data'],
                $input['job_data'],
                $stage
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('follow_up', [
                'action' => 'follow_up',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'status_update':
            if (!isset($input['candidate_data']) || !isset($input['job_data']) || !isset($input['new_status'])) {
                Res::fail('Faltan candidate_data, job_data o new_status', 400);
            }

            $nextSteps = $input['next_steps'] ?? [];
            $email = $communicationService->generateStatusUpdateEmail(
                $input['candidate_data'],
                $input['job_data'],
                $input['new_status'],
                $nextSteps
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('status_update', [
                'action' => 'status_update',
                'email' => $email,
                'ready_to_send' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'generate_templates':
            if (!isset($input['job_data'])) {
                Res::fail('Falta job_data', 400);
            }

            $templates = $communicationService->generateEmailTemplates($input['job_data']);

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('generate_templates', [
                'action' => 'generate_templates',
                'templates' => $templates,
                'template_count' => is_array($templates) ? count($templates) : 0,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'schedule_emails':
            if (!isset($input['candidate_data']) || !isset($input['job_data']) || !isset($input['triggers'])) {
                Res::fail('Faltan candidate_data, job_data o triggers', 400);
            }

            $scheduledEmails = $communicationService->scheduleAutomaticEmails(
                $input['candidate_data'],
                $input['job_data'],
                $input['triggers']
            );

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('schedule_emails', [
                'action' => 'schedule_emails',
                'scheduled_emails' => $scheduledEmails,
                'total_scheduled' => is_array($scheduledEmails) ? count($scheduledEmails) : 0,
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        case 'bulk_communications':
            if (!isset($input['candidates']) || !isset($input['job_data']) || !isset($input['email_type'])) {
                Res::fail('Faltan candidates, job_data o email_type', 400);
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

            $elapsedMs = (int)round((microtime(true) - $start) * 1000);

            Res::success('bulk_communications', [
                'action' => 'bulk_communications',
                'bulk_emails' => $bulkEmails,
                'total_emails' => count($bulkEmails),
                'timestamp' => date('Y-m-d H:i:s'),
                'processing_info' => [
                    'time_ms' => $elapsedMs,
                    'endpoint' => basename(__FILE__),
                ],
            ]);
            break;

        default:
            Res::fail('Acción no válida', 400, [
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
} catch (\Throwable $e) {
    Res::log('error', 'communication endpoint error', [
        'endpoint' => basename(__FILE__),
        'action' => $action ?? null,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    Res::error('Error interno del servidor', $e, 500);
}
