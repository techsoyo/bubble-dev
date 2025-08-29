<?php

declare(strict_types=1);

namespace Services;

use Services\UnifiedAIService;
use Services\Exceptions\AiUnavailableException;

/**
 * AI-Powered Communication Automation Service
 *
 * Servicio para automatización de comunicación con candidatos:
 * emails personalizados, respuestas automáticas, follow-ups, etc.
 *
 * Ahora usa el servicio unificado de IA para soporte multi-proveedor.
 *
 * @package Backend\Services
 * @version 2.0.0
 * @since 2025-08-10
 */
class CommunicationService
{
    private UnifiedAIService $aiService;

    public function __construct(?string $provider = null)
    {
        try {
            $this->aiService = new UnifiedAIService($provider);
        } catch (AiUnavailableException $e) {
            error_log('Error inicializando CommunicationService: ' . $e->getMessage());
            throw new \Exception('Servicio de IA no disponible para CommunicationService');
        }
    }

    /**
     * Genera email personalizado usando IA
     *
     * @param string $emailType Tipo de email a generar
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos del trabajo
     * @param array $additionalData Datos adicionales según el contexto
     * @return array Email generado con asunto y cuerpo
     */
    public function generatePersonalizedEmail($emailType, $candidateData, $jobData = [], $additionalData = [])
    {
        try {
            $prompt = $this->buildEmailPrompt($emailType, $candidateData, $jobData, $additionalData);

            $response = $this->aiService->chatCompletion(
                $prompt,
                ['json_mode' => true, 'temperature' => 0.7]
            );

            $emailContent = $response['choices'][0]['message']['content'] ?? '';

            if (!$emailContent) {
                return $this->createFallbackEmail($emailType, $candidateData);
            }

            $emailData = json_decode($emailContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackEmail($emailType, $candidateData);
            }

            $result = $this->validateEmailStructure($emailData, $emailType);
            $result['metadata'] = [
                'provider_used' => $this->aiService->getCurrentProvider(),
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'generated_at' => date('Y-m-d H:i:s')
            ];

            return $result;
        } catch (\Exception $e) {
            error_log('Error generando email personalizado: ' . $e->getMessage());
            return $this->createFallbackEmail($emailType, $candidateData);
        }
    }

    /**
     * Genera respuesta automática para aplicación recibida
     */
    public function generateApplicationResponse($candidateData, $jobData)
    {
        return $this->generatePersonalizedEmail('application_received', $candidateData, $jobData);
    }

    /**
     * Genera email de rechazo con feedback constructivo
     */
    public function generateRejectionEmail($candidateData, $jobData, $rejectionReason = '')
    {
        return $this->generatePersonalizedEmail('rejection', $candidateData, $jobData, [
            'reason' => $rejectionReason
        ]);
    }

    /**
     * Genera email de invitación a entrevista
     */
    public function generateInterviewInvitation($candidateData, $jobData, $interviewDetails = [])
    {
        return $this->generatePersonalizedEmail('interview_invitation', $candidateData, $jobData, [
            'interview_details' => $interviewDetails
        ]);
    }

    /**
     * Genera email de seguimiento
     */
    public function generateFollowUpEmail($candidateData, $jobData, $stage = 'general')
    {
        return $this->generatePersonalizedEmail('follow_up', $candidateData, $jobData, [
            'stage' => $stage
        ]);
    }

    /**
     * Genera email de actualización de estado
     */
    public function generateStatusUpdateEmail($candidateData, $jobData, $newStatus, $nextSteps = [])
    {
        return $this->generatePersonalizedEmail('status_update', $candidateData, $jobData, [
            'new_status' => $newStatus,
            'next_steps' => $nextSteps
        ]);
    }

    /**
     * Genera múltiples templates para diferentes escenarios
     */
    public function generateEmailTemplates($jobData)
    {
        $templates = [];
        $emailTypes = [
            'application_received',
            'rejection',
            'interview_invitation',
            'follow_up',
            'status_update'
        ];

        foreach ($emailTypes as $type) {
            $templates[$type] = $this->generateGenericTemplate($type, $jobData);
        }

        return $templates;
    }

    /**
     * Programa emails automáticos basados en triggers
     */
    public function scheduleAutomaticEmails($candidateData, $jobData, $triggers = [])
    {
        $scheduledEmails = [];

        foreach ($triggers as $trigger) {
            $email = null;

            switch ($trigger['type']) {
                case 'application_received':
                    $email = $this->generateApplicationResponse($candidateData, $jobData);
                    break;
                case 'no_response_followup':
                    $email = $this->generateFollowUpEmail($candidateData, $jobData, 'no_response');
                    break;
                case 'interview_reminder':
                    $email = $this->generatePersonalizedEmail('interview_reminder', $candidateData, $jobData, $trigger['data'] ?? []);
                    break;
            }

            if ($email) {
                $scheduledEmails[] = [
                    'trigger' => $trigger,
                    'email' => $email,
                    'scheduled_for' => $trigger['schedule_time'] ?? date('Y-m-d H:i:s'),
                    'status' => 'pending'
                ];
            }
        }

        return $scheduledEmails;
    }

    /**
     * Construye prompt para generación de emails
     */
    private function buildEmailPrompt($emailType, $candidateData, $jobData, $additionalData)
    {
        $candidateName = $candidateData['nombre'] ?? 'Candidato';
        $jobTitle = $jobData['title'] ?? 'la posición';
        $companyName = $jobData['company'] ?? 'nuestra empresa';

        $basePrompt = "Genera un email profesional y personalizado en español para un candidato.

CANDIDATO:
Nombre: {$candidateName}
Email: " . ($candidateData['email'] ?? 'No especificado') . "

TRABAJO:
Título: {$jobTitle}
Empresa: {$companyName}
Ubicación: " . ($jobData['location'] ?? 'No especificada') . '

';

        switch ($emailType) {
            case 'application_received':
                $basePrompt .= 'TIPO DE EMAIL: Confirmación de aplicación recibida
OBJETIVO: Confirmar recepción, dar timeline aproximado, mantener engagement
TONO: Profesional, cálido, informativo';
                break;

            case 'rejection':
                $reason = $additionalData['reason'] ?? '';
                $basePrompt .= "TIPO DE EMAIL: Rechazo con feedback constructivo
OBJETIVO: Comunicar decisiÃƒÆ’Ã‚Â³n profesionalmente, dar feedback ÃƒÆ’Ã‚Âºtil, mantener buena imagen
TONO: Profesional, empÃƒÆ’Ã‚Â¡tico, constructivo
RAZÃƒÆ’Ã¢â‚¬Å“N DE RECHAZO: {$reason}";
                break;

            case 'interview_invitation':
                $basePrompt .= 'TIPO DE EMAIL: Invitación a entrevista
OBJETIVO: Invitar a siguiente etapa, dar detalles, confirmar interés
TONO: Profesional, entusiasta, claro';
                break;

            case 'follow_up':
                $stage = $additionalData['stage'] ?? 'general';
                $basePrompt .= "TIPO DE EMAIL: Follow-up después de {$stage}
OBJETIVO: Mantener comunicación, verificar interés, dar updates
TONO: Profesional, amigable, no presionante";
                break;

            case 'status_update':
                $newStatus = $additionalData['new_status'] ?? 'en proceso';
                $basePrompt .= "TIPO DE EMAIL: Actualización de estado del proceso
NUEVO ESTADO: {$newStatus}
OBJETIVO: Informar progreso, mantener transparencia
TONO: Profesional, transparente, informativo";
                break;

            case 'interview_reminder':
                $basePrompt .= 'TIPO DE EMAIL: Recordatorio de entrevista
OBJETIVO: Recordar fecha/hora, confirmar asistencia, dar detalles finales
TONO: Profesional, claro, ÃƒÆ’Ã‚Âºtil';
                break;
        }

        $basePrompt .= '

INSTRUCCIONES:
- Personaliza usando el nombre del candidato y detalles del trabajo
- Mantén un tono profesional pero humano
- Incluye información específica y útil
- Añade call-to-action claro cuando sea apropiado
- Evita lenguaje genérico o plantilla obvia

Responde ÚNICAMENTE con JSON válido:
{
  "subject": "Asunto del email específico y atractivo",
  "body": "Cuerpo del email completo con saludo, contenido y cierre",
  "call_to_action": "Acción específica que debe tomar el candidato",
  "priority": "high|medium|low",
  "send_timing": "immediate|within_24h|within_week",
  "personalization_elements": ["Lista de elementos personalizados incluidos"]
}';

        return $basePrompt;
    }

    /**
     * Genera template genérico para un tipo de email
     */
    private function generateGenericTemplate($emailType, $jobData)
    {
        $prompt = "Genera un template de email genérico para '{$emailType}' que pueda ser usado para múltiples candidatos.

TRABAJO:
Título: " . ($jobData['title'] ?? '[TÍTULO_TRABAJO]') . '
Empresa: ' . ($jobData['company'] ?? '[EMPRESA]') . "

Usa placeholders como [NOMBRE_CANDIDATO], [TÍTULO_TRABAJO], etc. que puedan ser reemplazados.

Responde ÚNICAMENTE con JSON válido:
{
  \"template_name\": \"{$emailType}_template\",
  \"subject_template\": \"Asunto con placeholders\",
  \"body_template\": \"Cuerpo con placeholders\",
  \"placeholders\": [\"Lista de placeholders disponibles\"],
  \"usage_notes\": \"Notas sobre cuándo usar este template\"
}";

        $response = $this->callOpenAI($prompt);

        if ($response) {
            $template = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $template;
            }
        }

        return $this->createFallbackTemplate($emailType);
    }

    /**
     * Crea email de respaldo si OpenAI falla
     */
    private function createFallbackEmail($emailType, $candidateData)
    {
        $candidateName = $candidateData['nombre'] ?? 'Candidato';

        $fallbackEmails = [
            'application_received' => [
                'subject' => 'Confirmación de aplicación recibida',
                'body' => "Estimado/a {$candidateName},\n\nHemos recibido tu aplicación y estamos revisando tu perfil. Te contactaremos pronto con actualizaciones.\n\nSaludos cordiales,\nEquipo de Reclutamiento",
                'call_to_action' => 'Esperar respuesta del equipo',
                'priority' => 'medium',
                'send_timing' => 'immediate'
            ],
            'rejection' => [
                'subject' => 'Actualización sobre tu aplicación',
                'body' => "Estimado/a {$candidateName},\n\nTe agradecemos tu interés en nuestra empresa. Después de revisar tu perfil, hemos decidido continuar con otros candidatos.\n\nTe deseamos mucho éxito en tu búsqueda laboral.\n\nSaludos cordiales,\nEquipo de Reclutamiento",
                'call_to_action' => 'Continuar búsqueda laboral',
                'priority' => 'medium',
                'send_timing' => 'within_24h'
            ]
        ];

        $email = $fallbackEmails[$emailType] ?? $fallbackEmails['application_received'];
        $email['fallback_generated'] = true;
        $email['personalization_elements'] = ['nombre'];

        return $email;
    }

    private function createFallbackTemplate($emailType)
    {
        return [
            'template_name' => "{$emailType}_template",
            'subject_template' => 'Actualización sobre tu aplicación para [TÍTULO_TRABAJO]',
            'body_template' => "Estimado/a [NOMBRE_CANDIDATO],\n\n[CONTENIDO_ESPECÍFICO]\n\nSaludos cordiales,\n[EMPRESA]",
            'placeholders' => ['[NOMBRE_CANDIDATO]', '[TÍTULO_TRABAJO]', '[EMPRESA]', '[CONTENIDO_ESPECÍFICO]'],
            'usage_notes' => 'Template genérico que requiere personalización manual',
            'fallback_generated' => true
        ];
    }

    /**
     * Valida estructura del email generado
     */
    private function validateEmailStructure($emailData, $emailType)
    {
        $required_fields = ['subject', 'body', 'call_to_action', 'priority', 'send_timing'];

        foreach ($required_fields as $field) {
            if (!isset($emailData[$field])) {
                $emailData[$field] = $this->getDefaultEmailValue($field);
            }
        }

        $emailData['email_type'] = $emailType;
        $emailData['generated_with'] = 'OpenAI GPT-4 Communication Service';
        $emailData['generated_at'] = date('Y-m-d H:i:s');

        return $emailData;
    }

    private function getDefaultEmailValue($field)
    {
        $defaults = [
            'subject' => 'Actualización sobre tu aplicación',
            'body' => 'Te contactaremos pronto con más información.',
            'call_to_action' => 'Esperar respuesta',
            'priority' => 'medium',
            'send_timing' => 'within_24h',
            'personalization_elements' => []
        ];

        return $defaults[$field] ?? '';
    }

    /**
     * Llamada a OpenAI (mismo método base)
     */
    /**
     * Método de compatibilidad para llamadas directas a IA (legacy)
     * @deprecated Usar UnifiedAIService directamente
     */
    private function callOpenAI($prompt)
    {
        // Este método se mantiene por compatibilidad pero usa el servicio unificado
        return $this->aiService->chatCompletion($prompt, ['temperature' => 0.4]);
    }
}
