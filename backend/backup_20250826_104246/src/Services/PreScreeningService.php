<?php

namespace Services;

/**
 * AI-Powered Pre-Screening Service
 *
 * Servicio para screening automático de candidatos, generación de preguntas
 * personalizadas y detección de red flags usando OpenAI.
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-10
 */
class PreScreeningService
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

        if (!$this->apiKey) {
            throw new \Exception('OpenAI API key no configurada para PreScreeningService');
        }
    }

    /**
     * Genera preguntas de pre-screening personalizadas
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos del trabajo
     * @param int $questionCount Número de preguntas a generar
     * @return array Lista de preguntas personalizadas
     */
    public function generateScreeningQuestions($candidateData, $jobData, $questionCount = 5)
    {
        try {
            $prompt = $this->buildScreeningPrompt($candidateData, $jobData, $questionCount);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackQuestions($jobData);
            }

            $questionsData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackQuestions($jobData);
            }

            return $this->validateQuestionsStructure($questionsData);
        } catch (\Exception $e) {
            error_log('Error generando preguntas screening: ' . $e->getMessage());
            return $this->createFallbackQuestions($jobData);
        }
    }

    /**
     * Detecta red flags en el CV del candidato
     *
     * @param array $candidateData Datos extraídos del CV
     * @return array Análisis de red flags
     */
    public function detectRedFlags($candidateData)
    {
        try {
            $prompt = $this->buildRedFlagsPrompt($candidateData);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackRedFlags();
            }

            $flagsData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackRedFlags();
            }

            return $this->validateRedFlagsStructure($flagsData);
        } catch (\Exception $e) {
            error_log('Error detectando red flags: ' . $e->getMessage());
            return $this->createFallbackRedFlags();
        }
    }

    /**
     * Valida requisitos mínimos automáticamente
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobRequirements Requisitos del trabajo
     * @return array Resultado de validación
     */
    public function validateMinimumRequirements($candidateData, $jobRequirements)
    {
        try {
            $prompt = $this->buildRequirementsPrompt($candidateData, $jobRequirements);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackValidation();
            }

            $validationData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackValidation();
            }

            return $this->validateRequirementsStructure($validationData);
        } catch (\Exception $e) {
            error_log('Error validando requisitos: ' . $e->getMessage());
            return $this->createFallbackValidation();
        }
    }

    /**
     * Realiza screening completo del candidato
     */
    public function performCompleteScreening($candidateData, $jobData)
    {
        $results = [
          'candidate_id' => $candidateData['id'] ?? null,
          'job_id' => $jobData['id'] ?? null,
          'screening_timestamp' => date('Y-m-d H:i:s')
        ];

        // 1. Generar preguntas personalizadas
        $results['screening_questions'] = $this->generateScreeningQuestions($candidateData, $jobData);

        // 2. Detectar red flags
        $results['red_flags_analysis'] = $this->detectRedFlags($candidateData);

        // 3. Validar requisitos mínimos
        $results['requirements_validation'] = $this->validateMinimumRequirements(
            $candidateData,
            $jobData['requirements'] ?? []
        );

        // 4. Calcular score general de screening
        $results['screening_score'] = $this->calculateScreeningScore($results);

        // 5. Recomendación final
        $results['recommendation'] = $this->generateScreeningRecommendation($results);

        return $results;
    }

    /**
     * Construye prompt para generación de preguntas
     */
    private function buildScreeningPrompt($candidateData, $jobData, $questionCount)
    {
        return "Genera {$questionCount} preguntas de pre-screening personalizadas para este candidato específico y trabajo.

CANDIDATO:
Nombre: " . ($candidateData['nombre'] ?? 'No especificado') . '
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Educación: ' . json_encode($candidateData['educacion'] ?? []) . '
Ubicación: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '

TRABAJO:
Título: ' . ($jobData['title'] ?? 'No especificado') . '
Descripción: ' . ($jobData['description'] ?? 'No especificada') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Experiencia mínima: ' . ($jobData['min_experience'] ?? 'No especificada') . ' años
Ubicación: ' . ($jobData['location'] ?? 'No especificada') . '

INSTRUCCIONES:
- Genera preguntas ESPECÍFICAS para este candidato y puesto
- Enfócate en gaps o áreas a clarificar del CV
- Incluye preguntas técnicas relevantes
- Añade preguntas sobre disponibilidad y expectativas
- Evita preguntas genéricas

Responde ÚNICAMENTE con JSON válido:
{
  "questions": [
    {
      "id": 1,
      "question": "Pregunta específica aquí",
      "type": "technical|experience|availability|cultural|salary",
      "priority": "high|medium|low",
      "expected_answer_type": "open|multiple_choice|yes_no|numeric",
      "reasoning": "Por qué esta pregunta es relevante para este candidato"
    }
  ],
  "focus_areas": ["Lista de áreas principales a explorar"],
  "estimated_duration": "Tiempo estimado del screening en minutos"
}';
    }

    /**
     * Construye prompt para detección de red flags
     */
    private function buildRedFlagsPrompt($candidateData)
    {
        $cvText = "DATOS DEL CANDIDATO:\n";
        $cvText .= 'Nombre: ' . ($candidateData['nombre'] ?? 'No especificado') . "\n";
        $cvText .= 'Email: ' . ($candidateData['email'] ?? 'No especificado') . "\n";
        $cvText .= 'Ubicación: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . "\n";
        $cvText .= 'Experiencia Laboral: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . "\n";
        $cvText .= 'Educación: ' . json_encode($candidateData['educacion'] ?? []) . "\n";
        $cvText .= 'Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . "\n";

        return "Analiza este CV y detecta posibles red flags o señales de alerta.

{$cvText}

BUSCA ESPECÍFICAMENTE:
- Gaps en experiencia laboral inexplicados
- Inconsistencias en fechas
- Progresión de carrera inusual (regresiones)
- Cambios frecuentes de trabajo
- Sobre-calificación extrema para trabajos básicos
- Información faltante o vaga
- Skills irreales o exageradas
- Problemas en datos de contacto

Responde ÚNICAMENTE con JSON válido:
{
  \"red_flags\": [
    {
      \"type\": \"employment_gap|inconsistent_dates|job_hopping|overqualification|missing_info|unrealistic_skills|other\",
      \"severity\": \"high|medium|low\",
      \"description\": \"Descripción específica del problema\",
      \"details\": \"Detalles adicionales\",
      \"recommendation\": \"Qué hacer al respecto\"
    }
  ],
  \"overall_risk_level\": \"high|medium|low\",
  \"verification_needed\": [\"Lista de cosas que necesitan verificación\"],
  \"positive_signals\": [\"Señales positivas encontradas\"],
  \"recommendation\": \"proceed|investigate|reject\",
  \"confidence_score\": 85
}";
    }

    /**
     * Construye prompt para validación de requisitos
     */
    private function buildRequirementsPrompt($candidateData, $jobRequirements)
    {
        return 'Valida si este candidato cumple con los requisitos mínimos del trabajo.

CANDIDATO:
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Educación: ' . json_encode($candidateData['educacion'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Idiomas: ' . implode(', ', $candidateData['idiomas'] ?? []) . '
Ubicación: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '

REQUISITOS A VALIDAR:
' . json_encode($jobRequirements) . '

Responde ÚNICAMENTE con JSON válido:
{
  "requirements_met": [
    {
      "requirement": "Nombre del requisito",
      "status": "met|not_met|partially_met|unclear",
      "evidence": "Evidencia en el CV",
      "confidence": 90
    }
  ],
  "overall_compliance": "full|partial|minimal|none",
  "critical_missing": ["Lista de requisitos críticos no cumplidos"],
  "strengths": ["Requisitos que supera"],
  "final_recommendation": "approve|conditional|reject",
  "percentage_met": 85
}';
    }

    /**
     * Calcula score general de screening
     */
    private function calculateScreeningScore($screeningResults)
    {
        $score = 100;

        // Penalizar por red flags
        foreach ($screeningResults['red_flags_analysis']['red_flags'] as $flag) {
            if ($flag['severity'] === 'high') {
                $score -= 20;
            } elseif ($flag['severity'] === 'medium') {
                $score -= 10;
            } elseif ($flag['severity'] === 'low') {
                $score -= 5;
            }
        }

        // Ajustar por cumplimiento de requisitos
        $requirementScore = $screeningResults['requirements_validation']['percentage_met'] ?? 70;
        $score = ($score + $requirementScore) / 2;

        return max(0, min(100, round($score)));
    }

    /**
     * Genera recomendación de screening
     */
    private function generateScreeningRecommendation($screeningResults)
    {
        $score = $screeningResults['screening_score'];
        $redFlagsLevel = $screeningResults['red_flags_analysis']['overall_risk_level'] ?? 'medium';
        $requirementsStatus = $screeningResults['requirements_validation']['overall_compliance'] ?? 'partial';

        if ($score >= 80 && $redFlagsLevel === 'low' && in_array($requirementsStatus, ['full', 'partial'])) {
            return [
              'decision' => 'proceed',
              'confidence' => 'high',
              'next_step' => 'Schedule phone/video interview',
              'notes' => 'Candidate passed automatic screening successfully'
            ];
        } elseif ($score >= 60 && $redFlagsLevel !== 'high') {
            return [
              'decision' => 'conditional',
              'confidence' => 'medium',
              'next_step' => 'Manual review recommended',
              'notes' => 'Candidate has potential but needs human validation'
            ];
        } else {
            return [
              'decision' => 'reject',
              'confidence' => 'high',
              'next_step' => 'Send automated rejection email',
              'notes' => 'Candidate does not meet minimum requirements'
            ];
        }
    }

    /**
     * Métodos de fallback y validación similares a MatchingService
     */
    private function createFallbackQuestions($jobData)
    {
        return [
          'questions' => [
            [
              'id' => 1,
              'question' => '¿Cuántos años de experiencia tienes en el rol o similar?',
              'type' => 'experience',
              'priority' => 'high',
              'expected_answer_type' => 'numeric',
              'reasoning' => 'Validar experiencia mínima requerida'
            ],
            [
              'id' => 2,
              'question' => '¿Estás disponible para comenzar en las próximas 2-4 semanas?',
              'type' => 'availability',
              'priority' => 'high',
              'expected_answer_type' => 'yes_no',
              'reasoning' => 'Confirmar disponibilidad'
            ]
          ],
          'focus_areas' => ['Experiencia', 'Disponibilidad'],
          'estimated_duration' => '10',
          'fallback_generated' => true
        ];
    }

    private function createFallbackRedFlags()
    {
        return [
          'red_flags' => [],
          'overall_risk_level' => 'medium',
          'verification_needed' => ['Revisar manualmente'],
          'positive_signals' => ['Análisis automático limitado'],
          'recommendation' => 'investigate',
          'confidence_score' => 50,
          'fallback_analysis' => true
        ];
    }

    private function createFallbackValidation()
    {
        return [
          'requirements_met' => [],
          'overall_compliance' => 'unclear',
          'critical_missing' => ['Análisis manual necesario'],
          'strengths' => [],
          'final_recommendation' => 'conditional',
          'percentage_met' => 50,
          'fallback_analysis' => true
        ];
    }

    // Métodos de validación de estructura
    private function validateQuestionsStructure($data)
    {
        if (!isset($data['questions']) || !is_array($data['questions'])) {
            return $this->createFallbackQuestions([]);
        }

        $data['processed_with'] = 'OpenAI GPT-4 PreScreening Service';
        $data['processed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateRedFlagsStructure($data)
    {
        if (!isset($data['red_flags']) || !is_array($data['red_flags'])) {
            return $this->createFallbackRedFlags();
        }

        $data['processed_with'] = 'OpenAI GPT-4 PreScreening Service';
        $data['processed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateRequirementsStructure($data)
    {
        if (!isset($data['requirements_met']) || !is_array($data['requirements_met'])) {
            return $this->createFallbackValidation();
        }

        $data['processed_with'] = 'OpenAI GPT-4 PreScreening Service';
        $data['processed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    /**
     * Llamada a OpenAI (mismo método que MatchingService)
     */
    private function callOpenAI($prompt)
    {
        $data = [
          'model' => 'gpt-4',
          'messages' => [
            [
              'role' => 'system',
              'content' => 'Eres un experto en pre-screening y evaluación de candidatos. Analiza CVs con precisión profesional y genera contenido útil para recruiters. Responde ÚNICAMENTE con JSON válido.'
            ],
            [
              'role' => 'user',
              'content' => $prompt
            ]
          ],
          'max_tokens' => 2500,
          'temperature' => 0.3
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
            error_log("OpenAI API Error en PreScreening: HTTP {$httpCode} - {$response}");
            return null;
        }

        $responseData = json_decode($response, true);
        return $responseData['choices'][0]['message']['content'] ?? null;
    }
}
