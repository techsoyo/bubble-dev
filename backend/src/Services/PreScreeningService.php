<?php

declare(strict_types=1);

namespace Services;

use Services\UnifiedAIService;
use Services\Exceptions\AiUnavailableException;

/**
 * AI-Powered Pre-Screening Service
 *
 * Servicio para screening automático de candidatos, generación de preguntas
 * personalizadas y detección de red flags usando el sistema unificado de IA.
 *
 * @package Backend\Services
 * @version 2.0.0
 * @since 2025-08-10
 */
class PreScreeningService
{
  private UnifiedAIService $aiService;

  public function __construct(?string $provider = null)
  {
    try {
      $this->aiService = new UnifiedAIService($provider);
    } catch (AiUnavailableException $e) {
      error_log('Error inicializando PreScreeningService: ' . $e->getMessage());
      throw new \Exception('Servicio de IA no disponible para PreScreeningService');
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
      $data = [
        'candidate' => $candidateData,
        'job' => $jobData,
        'count' => $questionCount
      ];

      $response = $this->aiService->generateContent('interview_questions', $data);

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
      $response = $this->aiService->chatCompletion(
        $this->buildRedFlagsPrompt($candidateData),
        ['json_mode' => true, 'temperature' => 0.3]
      );

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
      $response = $this->aiService->chatCompletion(
        $this->buildRequirementsPrompt($candidateData, $jobRequirements),
        ['json_mode' => true, 'temperature' => 0.1]
      );

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

    // 3. Validar requisitos mí­nimos
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
    return "Genera {$questionCount} preguntas de pre-screening personalizadas para este candidato especí­fico y trabajo.

CANDIDATO:
Nombre: " . ($candidateData['nombre'] ?? 'No especificado') . '
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Educación: ' . json_encode($candidateData['educacion'] ?? []) . '
Ubicación: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '

TRABAJO:
Tí­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Descripción: ' . ($jobData['description'] ?? 'No especificada') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Experiencia mí­nima: ' . ($jobData['min_experience'] ?? 'No especificada') . ' aí±os
Ubicación: ' . ($jobData['location'] ?? 'No especificada') . '

INSTRUCCIONES:
- Genera preguntas ESPECíFICAS para este candidato y puesto
- Enfócate en gaps o í¡reas a clarificar del CV
- Incluye preguntas técnicas relevantes
- Aí±ade preguntas sobre disponibilidad y expectativas
- Evita preguntas genéricas

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "questions": [
    {
      "id": 1,
      "question": "Pregunta especí­fica aquí­",
      "type": "technical|experience|availability|cultural|salary",
      "priority": "high|medium|low",
      "expected_answer_type": "open|multiple_choice|yes_no|numeric",
      "reasoning": "Por qué esta pregunta es relevante para este candidato"
    }
  ],
  "focus_areas": ["Lista de í¡reas principales a explorar"],
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

    return "Analiza este CV y detecta posibles red flags o seí±ales de alerta.

{$cvText}

BUSCA ESPECíFICAMENTE:
- Gaps en experiencia laboral inexplicados
- Inconsistencias en fechas
- Progresión de carrera inusual (regresiones)
- Cambios frecuentes de trabajo
- Sobre-calificación extrema para trabajos bí¡sicos
- Información faltante o vaga
- Skills irreales o exageradas
- Problemas en datos de contacto

Responde ÚNICAMENTE con JSON ví¡lido:
{
  \"red_flags\": [
    {
      \"type\": \"employment_gap|inconsistent_dates|job_hopping|overqualification|missing_info|unrealistic_skills|other\",
      \"severity\": \"high|medium|low\",
      \"description\": \"Descripción especí­fica del problema\",
      \"details\": \"Detalles adicionales\",
      \"recommendation\": \"Qué hacer al respecto\"
    }
  ],
  \"overall_risk_level\": \"high|medium|low\",
  \"verification_needed\": [\"Lista de cosas que necesitan verificación\"],
  \"positive_signals\": [\"Seí±ales positivas encontradas\"],
  \"recommendation\": \"proceed|investigate|reject\",
  \"confidence_score\": 85
}";
  }

  /**
   * Construye prompt para validación de requisitos
   */
  private function buildRequirementsPrompt($candidateData, $jobRequirements)
  {
    return 'Valida si este candidato cumple con los requisitos mí­nimos del trabajo.

CANDIDATO:
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Educación: ' . json_encode($candidateData['educacion'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Idiomas: ' . implode(', ', $candidateData['idiomas'] ?? []) . '
Ubicación: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '

REQUISITOS A VALIDAR:
' . json_encode($jobRequirements) . '

Responde ÚNICAMENTE con JSON ví¡lido:
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
  "critical_missing": ["Lista de requisitos crí­ticos no cumplidos"],
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
          'question' => 'Ãƒâ€šÃ‚Â¿Cuí¡ntos aí±os de experiencia tienes en el rol o similar?',
          'type' => 'experience',
          'priority' => 'high',
          'expected_answer_type' => 'numeric',
          'reasoning' => 'Validar experiencia mí­nima requerida'
        ],
        [
          'id' => 2,
          'question' => 'Ãƒâ€šÃ‚Â¿Estí¡s disponible para comenzar en las próximas 2-4 semanas?',
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
      'positive_signals' => ['Anáslisis automí¡tico limitado'],
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
      'critical_missing' => ['Anáslisis manual necesario'],
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

    $data['processed_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['processed_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validateRedFlagsStructure($data)
  {
    if (!isset($data['red_flags']) || !is_array($data['red_flags'])) {
      return $this->createFallbackRedFlags();
    }

    $data['processed_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['processed_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validateRequirementsStructure($data)
  {
    if (!isset($data['requirements_met']) || !is_array($data['requirements_met'])) {
      return $this->createFallbackValidation();
    }

    $data['processed_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['processed_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  /**
   * Llamada a OpenAI (método obsoleto - usar UnifiedAIService)
   */
  private function callOpenAI($prompt)
  {
    // Este método ha sido reemplazado por UnifiedAIService
    // Se mantiene por compatibilidad pero ya no se usa
    error_log('PreScreeningService::callOpenAI is deprecated. Use UnifiedAIService instead.');
    return null;
  }
}
