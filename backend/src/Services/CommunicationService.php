<?php declare(strict_types=1);
namespace Services;

/**
 * AI-Powered Communication Automation Service
 *
 * Servicio para automatizaciÃƒÆ’Ã‚Â³n de comunicaciÃƒÆ’Ã‚Â³n con candidatos:
 * emails personalizados, respuestas automÃƒÆ’Ã‚Â¡ticas, follow-ups, etc.
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-10
 */
class CommunicationService
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

        if (!$this->apiKey) {
            throw new \Exception('OpenAI API key no configurada para CommunicationService');
        }
    }

    /**
     * Genera email personalizado segÃƒÆ’Ã‚Âºn el estado del candidato
     *
     * @param string $emailType Tipo de email a generar
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos del trabajo
     * @param array $additionalData Datos adicionales segÃƒÆ’Ã‚Âºn el contexto
     * @return array Email generado con asunto y cuerpo
     */
    public function generatePersonalizedEmail($emailType, $candidateData, $jobData = [], $additionalData = [])
    {
        try {
            $prompt = $this->buildEmailPrompt($emailType, $candidateData, $jobData, $additionalData);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackEmail($emailType, $candidateData);
            }

            $emailData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackEmail($emailType, $candidateData);
            }

            return $this->validateEmailStructure($emailData, $emailType);
        } catch (\Exception $e) {
            error_log('Error generando email: ' . $e->getMessage());
            return $this->createFallbackEmail($emailType, $candidateData);
        }
    }

    /**
     * Genera respuesta automÃƒÆ’Ã‚Â¡tica para aplicaciÃƒÆ’Ã‚Â³n recibida
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
     * Genera email de invitaciÃƒÆ’Ã‚Â³n a entrevista
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
     * Genera email de actualizaciÃƒÆ’Ã‚Â³n de estado
     */
    public function generateStatusUpdateEmail($candidateData, $jobData, $newStatus, $nextSteps = [])
    {
        return $this->generatePersonalizedEmail('status_update', $candidateData, $jobData, [
          'new_status' => $newStatus,
          'next_steps' => $nextSteps
        ]);
    }

    /**
     * Genera mÃƒÆ’Ã‚Âºltiples templates para diferentes escenarios
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
     * Programa emails automÃƒÆ’Ã‚Â¡ticos basados en triggers
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
     * Construye prompt para generaciÃƒÆ’Ã‚Â³n de emails
     */
    private function buildEmailPrompt($emailType, $candidateData, $jobData, $additionalData)
    {
        $candidateName = $candidateData['nombre'] ?? 'Candidato';
        $jobTitle = $jobData['title'] ?? 'la posiciÃƒÆ’Ã‚Â³n';
        $companyName = $jobData['company'] ?? 'nuestra empresa';

        $basePrompt = "Genera un email profesional y personalizado en espaÃƒÆ’Ã‚Â±ol para un candidato.

CANDIDATO:
Nombre: {$candidateName}
Email: " . ($candidateData['email'] ?? 'No especificado') . "

TRABAJO:
TÃƒÆ’Ã‚Â­tulo: {$jobTitle}
Empresa: {$companyName}
UbicaciÃƒÆ’Ã‚Â³n: " . ($jobData['location'] ?? 'No especificada') . '

';

        switch ($emailType) {
            case 'application_received':
                $basePrompt .= 'TIPO DE EMAIL: ConfirmaciÃƒÆ’Ã‚Â³n de aplicaciÃƒÆ’Ã‚Â³n recibida
OBJETIVO: Confirmar recepciÃƒÆ’Ã‚Â³n, dar timeline aproximado, mantener engagement
TONO: Profesional, cÃƒÆ’Ã‚Â¡lido, informativo';
                break;

            case 'rejection':
                $reason = $additionalData['reason'] ?? '';
                $basePrompt .= "TIPO DE EMAIL: Rechazo con feedback constructivo
OBJETIVO: Comunicar decisiÃƒÆ’Ã‚Â³n profesionalmente, dar feedback ÃƒÆ’Ã‚Âºtil, mantener buena imagen
TONO: Profesional, empÃƒÆ’Ã‚Â¡tico, constructivo
RAZÃƒÆ’Ã¢â‚¬Å“N DE RECHAZO: {$reason}";
                break;

            case 'interview_invitation':
                $basePrompt .= 'TIPO DE EMAIL: InvitaciÃƒÆ’Ã‚Â³n a entrevista
OBJETIVO: Invitar a siguiente etapa, dar detalles, confirmar interÃƒÆ’Ã‚Â©s
TONO: Profesional, entusiasta, claro';
                break;

            case 'follow_up':
                $stage = $additionalData['stage'] ?? 'general';
                $basePrompt .= "TIPO DE EMAIL: Follow-up despuÃƒÆ’Ã‚Â©s de {$stage}
OBJETIVO: Mantener comunicaciÃƒÆ’Ã‚Â³n, verificar interÃƒÆ’Ã‚Â©s, dar updates
TONO: Profesional, amigable, no presionante";
                break;

            case 'status_update':
                $newStatus = $additionalData['new_status'] ?? 'en proceso';
                $basePrompt .= "TIPO DE EMAIL: ActualizaciÃƒÆ’Ã‚Â³n de estado del proceso
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
- MantÃƒÆ’Ã‚Â©n un tono profesional pero humano
- Incluye informaciÃƒÆ’Ã‚Â³n especÃƒÆ’Ã‚Â­fica y ÃƒÆ’Ã‚Âºtil
- AÃƒÆ’Ã‚Â±ade call-to-action claro cuando sea apropiado
- Evita lenguaje genÃƒÆ’Ã‚Â©rico o plantilla obvia

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "subject": "Asunto del email especÃƒÆ’Ã‚Â­fico y atractivo",
  "body": "Cuerpo del email completo con saludo, contenido y cierre",
  "call_to_action": "AcciÃƒÆ’Ã‚Â³n especÃƒÆ’Ã‚Â­fica que debe tomar el candidato",
  "priority": "high|medium|low",
  "send_timing": "immediate|within_24h|within_week",
  "personalization_elements": ["Lista de elementos personalizados incluidos"]
}';

        return $basePrompt;
    }

    /**
     * Genera template genÃƒÆ’Ã‚Â©rico para un tipo de email
     */
    private function generateGenericTemplate($emailType, $jobData)
    {
        $prompt = "Genera un template de email genÃƒÆ’Ã‚Â©rico para '{$emailType}' que pueda ser usado para mÃƒÆ’Ã‚Âºltiples candidatos.

TRABAJO:
TÃƒÆ’Ã‚Â­tulo: " . ($jobData['title'] ?? '[TÃƒÆ’Ã‚ÂTULO_TRABAJO]') . '
Empresa: ' . ($jobData['company'] ?? '[EMPRESA]') . "

Usa placeholders como [NOMBRE_CANDIDATO], [TÃƒÆ’Ã‚ÂTULO_TRABAJO], etc. que puedan ser reemplazados.

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  \"template_name\": \"{$emailType}_template\",
  \"subject_template\": \"Asunto con placeholders\",
  \"body_template\": \"Cuerpo con placeholders\",
  \"placeholders\": [\"Lista de placeholders disponibles\"],
  \"usage_notes\": \"Notas sobre cuÃƒÆ’Ã‚Â¡ndo usar este template\"
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
            'subject' => 'ConfirmaciÃƒÆ’Ã‚Â³n de aplicaciÃƒÆ’Ã‚Â³n recibida',
            'body' => "Estimado/a {$candidateName},\n\nHemos recibido tu aplicaciÃƒÆ’Ã‚Â³n y estamos revisando tu perfil. Te contactaremos pronto con actualizaciones.\n\nSaludos cordiales,\nEquipo de Reclutamiento",
            'call_to_action' => 'Esperar respuesta del equipo',
            'priority' => 'medium',
            'send_timing' => 'immediate'
          ],
          'rejection' => [
            'subject' => 'ActualizaciÃƒÆ’Ã‚Â³n sobre tu aplicaciÃƒÆ’Ã‚Â³n',
            'body' => "Estimado/a {$candidateName},\n\nTe agradecemos tu interÃƒÆ’Ã‚Â©s en nuestra empresa. DespuÃƒÆ’Ã‚Â©s de revisar tu perfil, hemos decidido continuar con otros candidatos.\n\nTe deseamos mucho ÃƒÆ’Ã‚Â©xito en tu bÃƒÆ’Ã‚Âºsqueda laboral.\n\nSaludos cordiales,\nEquipo de Reclutamiento",
            'call_to_action' => 'Continuar bÃƒÆ’Ã‚Âºsqueda laboral',
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
          'subject_template' => 'ActualizaciÃƒÆ’Ã‚Â³n sobre tu aplicaciÃƒÆ’Ã‚Â³n para [TÃƒÆ’Ã‚ÂTULO_TRABAJO]',
          'body_template' => "Estimado/a [NOMBRE_CANDIDATO],\n\n[CONTENIDO_ESPECÃƒÆ’Ã‚ÂFICO]\n\nSaludos cordiales,\n[EMPRESA]",
          'placeholders' => ['[NOMBRE_CANDIDATO]', '[TÃƒÆ’Ã‚ÂTULO_TRABAJO]', '[EMPRESA]', '[CONTENIDO_ESPECÃƒÆ’Ã‚ÂFICO]'],
          'usage_notes' => 'Template genÃƒÆ’Ã‚Â©rico que requiere personalizaciÃƒÆ’Ã‚Â³n manual',
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
          'subject' => 'ActualizaciÃƒÆ’Ã‚Â³n sobre tu aplicaciÃƒÆ’Ã‚Â³n',
          'body' => 'Te contactaremos pronto con mÃƒÆ’Ã‚Â¡s informaciÃƒÆ’Ã‚Â³n.',
          'call_to_action' => 'Esperar respuesta',
          'priority' => 'medium',
          'send_timing' => 'within_24h',
          'personalization_elements' => []
        ];

        return $defaults[$field] ?? '';
    }

    /**
     * Llamada a OpenAI (mismo mÃƒÆ’Ã‚Â©todo base)
     */
    private function callOpenAI($prompt)
    {
        $data = [
          'model' => 'gpt-4',
          'messages' => [
            [
              'role' => 'system',
              'content' => 'Eres un experto en comunicaciÃƒÆ’Ã‚Â³n profesional y recruitment. Generas emails personalizados, profesionales y efectivos para procesos de selecciÃƒÆ’Ã‚Â³n. Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido.'
            ],
            [
              'role' => 'user',
              'content' => $prompt
            ]
          ],
          'max_tokens' => 2000,
          'temperature' => 0.4
        ];

        $headers = [
          'Authorization: Bearer ' . $this->apiKey,
          'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("OpenAI API Error en Communication: HTTP {$httpCode} - {$response}");
            return null;
        }

        $responseData = json_decode($response, true);
        return $responseData['choices'][0]['message']['content'] ?? null;
    }
}
