<?php

declare(strict_types=1);

namespace Services;

use Services\UnifiedAIService;
use Services\Exceptions\AiUnavailableException;

/**
 * AI-Powered Content Generation & Predictive Analysis Service
 *
 * Servicio para generación automática de contenido (job descriptions,
 * preguntas de entrevista) y análisis predictivo básico.
 * Ahora usa el servicio unificado de IA para soporte multi-proveedor.
 *
 * @package Backend\Services
 * @version 2.0.0
 * @since 2025-08-10
 */
class ContentGenerationService
{
  private UnifiedAIService $aiService;

  public function __construct(?string $provider = null)
  {
    try {
      $this->aiService = new UnifiedAIService($provider);
    } catch (AiUnavailableException $e) {
      error_log('Error inicializando ContentGenerationService: ' . $e->getMessage());
      throw new \Exception('Servicio de IA no disponible para ContentGenerationService');
    }
  }

  /**
   * Genera job description automática basada en inputs mínimos
   *
   * @param array $jobInputs Datos básicos del trabajo
   * @return array Job description completa generada
   */
  public function generateJobDescription($jobInputs)
  {
    try {
      $response = $this->aiService->generateContent('job_description', $jobInputs);

      if (!$response) {
        return $this->createFallbackJobDescription($jobInputs);
      }

      $jobData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackJobDescription($jobInputs);
      }

      return $this->validateJobDescriptionStructure($jobData);
    } catch (\Exception $e) {
      error_log('Error generando job description: ' . $e->getMessage());
      return $this->createFallbackJobDescription($jobInputs);
    }
  }

  /**
   * Genera preguntas de entrevista personalizadas
   *
   * @param array $candidateData Datos del candidato
   * @param array $jobData Datos del trabajo
   * @param string $interviewType Tipo de entrevista
   * @return array Lista de preguntas personalizadas
   */
  public function generateInterviewQuestions($candidateData, $jobData, $interviewType = 'general')
  {
    try {
      $data = [
        'candidate' => $candidateData,
        'job' => $jobData,
        'type' => $interviewType
      ];

      $response = $this->aiService->generateContent('interview_questions', $data);

      if (!$response) {
        return $this->createFallbackQuestions($interviewType);
      }

      $questionsData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackQuestions($interviewType);
      }

      return $this->validateQuestionsStructure($questionsData);
    } catch (\Exception $e) {
      error_log('Error generando preguntas entrevista: ' . $e->getMessage());
      return $this->createFallbackQuestions($interviewType);
    }
  }

  /**
   * Análisis predictivo de éxito en el puesto
   *
   * @param array $candidateData Datos del candidato
   * @param array $jobData Datos del trabajo
   * @param array $historicalData Datos históricos de contrataciones (opcional)
   * @return array Predicción de éxito
   */
  public function predictJobSuccess($candidateData, $jobData, $historicalData = [])
  {
    try {
      $data = [
        'candidate' => $candidateData,
        'job' => $jobData,
        'historical' => $historicalData
      ];

      $response = $this->aiService->chatCompletion(
        $this->buildPredictivePrompt($candidateData, $jobData, $historicalData),
        ['json_mode' => true, 'temperature' => 0.3]
      );

      if (!$response) {
        return $this->createFallbackPrediction();
      }

      $predictionData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackPrediction();
      }

      return $this->validatePredictionStructure($predictionData);
    } catch (\Exception $e) {
      error_log('Error en análisis predictivo: ' . $e->getMessage());
      return $this->createFallbackPrediction();
    }
  }

  /**
   * Estima tiempo para cubrir la posición
   *
   * @param array $jobData Datos del trabajo
   * @param array $marketData Datos del mercado (opcional)
   * @return array Estimación de tiempo
   */
  public function estimateTimeToFill($jobData, $marketData = [])
  {
    try {
      $response = $this->aiService->chatCompletion(
        $this->buildTimeToFillPrompt($jobData, $marketData),
        ['json_mode' => true, 'temperature' => 0.3]
      );

      if (!$response) {
        return $this->createFallbackTimeEstimate();
      }

      $estimateData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackTimeEstimate();
      }

      return $this->validateTimeEstimateStructure($estimateData);
    } catch (\Exception $e) {
      error_log('Error estimando tiempo de contratación: ' . $e->getMessage());
      return $this->createFallbackTimeEstimate();
    }
  }

  /**
   * Genera email templates para outreach/sourcing
   *
   * @param array $jobData Datos del trabajo
   * @param string $sourceType Tipo de sourcing (linkedin, email, etc.)
   * @return array Templates de outreach
   */
  public function generateSourceingTemplates($jobData, $sourceType = 'linkedin')
  {
    try {
      $data = [
        'job' => $jobData,
        'source_type' => $sourceType
      ];

      $response = $this->aiService->generateContent('email_template', $data);

      if (!$response) {
        return $this->createFallbackSourcingTemplates($sourceType);
      }

      $templatesData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackSourcingTemplates($sourceType);
      }

      return $this->validateSourcingStructure($templatesData);
    } catch (\Exception $e) {
      error_log('Error generando templates sourcing: ' . $e->getMessage());
      return $this->createFallbackSourcingTemplates($sourceType);
    }
  }

  /**
   * Análisis de diversidad automático
   *
   * @param array $candidatesData Lista de candidatos
   * @param array $diversityMetrics Métricas a analizar
   * @return array Análisis de diversidad
   */
  public function analyzeDiversity($candidatesData, $diversityMetrics = [])
  {
    try {
      $response = $this->aiService->chatCompletion(
        $this->buildDiversityPrompt($candidatesData, $diversityMetrics),
        ['json_mode' => true, 'temperature' => 0.3]
      );

      if (!$response) {
        return $this->createFallbackDiversityAnalysis();
      }

      $diversityData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackDiversityAnalysis();
      }

      return $this->validateDiversityStructure($diversityData);
    } catch (\Exception $e) {
      error_log('Error en análisis de diversidad: ' . $e->getMessage());
      return $this->createFallbackDiversityAnalysis();
    }
  }

  /**
   * Construye prompt para job description
   */
  private function buildJobDescriptionPrompt($jobInputs)
  {
    return 'Genera una job description completa y atractiva basada en estos inputs mí­nimos.

INPUTS BÁSICOS:
Tí­tulo: ' . ($jobInputs['title'] ?? 'No especificado') . '
Departamento: ' . ($jobInputs['department'] ?? 'No especificado') . '
Nivel: ' . ($jobInputs['level'] ?? 'No especificado') . '
Modalidad: ' . ($jobInputs['remote_type'] ?? 'No especificada') . '
Ubicación: ' . ($jobInputs['location'] ?? 'No especificada') . '
Rango salarial: ' . ($jobInputs['salary_range'] ?? 'No especificado') . '
Skills clave: ' . implode(', ', $jobInputs['key_skills'] ?? []) . '
Descripción breve: ' . ($jobInputs['brief_description'] ?? 'No especificada') . '

INSTRUCCIONES:
- Crea una descripción atractiva y profesional
- Incluye responsabilidades especí­ficas y realistas
- Detalla requisitos técnicos y experiencia
- Aí±ade beneficios y cultura de empresa
- Optimiza para atraer candidatos quality

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "title": "Tí­tulo optimizado del puesto",
  "summary": "Resumen atractivo del rol",
  "responsibilities": ["Lista de responsabilidades principales"],
  "required_skills": ["Skills técnicas requeridas"],
  "preferred_skills": ["Skills deseables"],
  "experience_requirements": "Experiencia mí­nima requerida",
  "education_requirements": "Educación requerida",
  "benefits": ["Lista de beneficios"],
  "company_culture": "Descripción de cultura empresarial",
  "growth_opportunities": ["Oportunidades de crecimiento"],
  "application_process": "Proceso de aplicación",
  "keywords_seo": ["Keywords para SEO y sourcing"],
  "estimated_applications": "Número estimado de aplicaciones esperadas"
}';
  }

  /**
   * Construye prompt para preguntas de entrevista
   */
  private function buildInterviewQuestionsPrompt($candidateData, $jobData, $interviewType)
  {
    return 'Genera preguntas de entrevista especí­ficas y efectivas para este candidato y posición.

CANDIDATO:
Nombre: ' . ($candidateData['nombre'] ?? 'No especificado') . '
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Educación: ' . json_encode($candidateData['educacion'] ?? []) . '

TRABAJO:
Tí­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . "

TIPO DE ENTREVISTA: {$interviewType}

INSTRUCCIONES:
- Genera preguntas especí­ficas para este candidato
- Incluye preguntas técnicas relevantes
- Aí±ade preguntas comportamentales (STAR method)
- Incluye preguntas para evaluar fit cultural
- Evita preguntas discriminatorias o ilegales

Responde ÚNICAMENTE con JSON ví¡lido:
{
  \"technical_questions\": [
    {
      \"question\": \"Pregunta técnica especí­fica\",
      \"purpose\": \"Qué evalúa esta pregunta\",
      \"follow_up\": \"Pregunta de seguimiento\",
      \"red_flags\": [\"Respuestas que serí­an preocupantes\"]
    }
  ],
  \"behavioral_questions\": [
    {
      \"question\": \"Pregunta comportamental\",
      \"star_framework\": \"Cómo aplicar STAR\",
      \"ideal_answer_elements\": [\"Elementos de una respuesta ideal\"]
    }
  ],
  \"cultural_fit_questions\": [\"Preguntas para evaluar fit cultural\"],
  \"candidate_specific_questions\": [\"Preguntas especí­ficas basadas en el CV\"],
  \"interview_flow\": [\"Orden sugerido de preguntas\"],
  \"estimated_duration\": \"Duración estimada en minutos\",
  \"evaluation_criteria\": [\"Criterios para evaluar respuestas\"]
}";
  }

  /**
   * Construye prompt para Anáslisis predictivo
   */
  private function buildPredictivePrompt($candidateData, $jobData, $historicalData)
  {
    $historicalInfo = !empty($historicalData) ?
      "DATOS HISTÓRICOS:\n" . json_encode($historicalData) . "\n" :
      "No hay datos históricos disponibles.\n";

    return 'Analiza y predice la probabilidad de éxito de este candidato en el puesto.

CANDIDATO:
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Educación: ' . json_encode($candidateData['educacion'] ?? []) . '
Ubicación: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '

TRABAJO:
Tí­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Ubicación: ' . ($jobData['location'] ?? 'No especificada') . "

{$historicalInfo}

INSTRUCCIONES:
- Analiza factores de éxito predictivos
- Considera experiencia, skills, fit, estabilidad laboral
- Evalúa riesgos y fortalezas
- Proporciona recomendaciones accionables

Responde ÚNICAMENTE con JSON ví¡lido:
{
  \"success_probability\": 85,
  \"performance_prediction\": \"high|medium|low\",
  \"retention_probability\": 80,
  \"time_to_productivity\": \"Tiempo estimado hasta ser productivo\",
  \"success_factors\": [\"Factores que favorecen el éxito\"],
  \"risk_factors\": [\"Factores de riesgo identificados\"],
  \"recommendations\": [\"Recomendaciones para maximizar éxito\"],
  \"confidence_level\": 75,
  \"key_indicators_to_monitor\": [\"Indicadores a seguir post-hiring\"],
  \"similar_profiles_performance\": \"Rendimiento de perfiles similares históricos\"
}";
  }

  /**
   * Métodos de fallback y validación
   */
  private function createFallbackJobDescription($jobInputs)
  {
    return [
      'title' => $jobInputs['title'] ?? 'Posición Vacante',
      'summary' => 'Excelente oportunidad de crecimiento profesional',
      'responsibilities' => ['Responsabilidades a definir'],
      'required_skills' => $jobInputs['key_skills'] ?? ['Por definir'],
      'preferred_skills' => ['Habilidades adicionales valoradas'],
      'experience_requirements' => 'Experiencia relevante en el í¡rea',
      'education_requirements' => 'Educación acorde al nivel del puesto',
      'benefits' => ['Beneficios competitivos'],
      'company_culture' => 'Ambiente de trabajo colaborativo',
      'growth_opportunities' => ['Oportunidades de desarrollo'],
      'application_process' => 'Aplicar a través de nuestra plataforma',
      'keywords_seo' => [$jobInputs['title'] ?? 'trabajo'],
      'estimated_applications' => 'Por determinar',
      'fallback_generated' => true
    ];
  }

  private function createFallbackQuestions($interviewType)
  {
    return [
      'technical_questions' => [
        [
          'question' => 'Ãƒâ€šÃ‚Â¿Puedes describir tu experiencia  más relevante para este puesto?',
          'purpose' => 'Evaluar experiencia técnica',
          'follow_up' => 'Ãƒâ€šÃ‚Â¿Qué desafí­os enfrentaste y cómo los resolviste?',
          'red_flags' => ['Respuestas vagas', 'Falta de ejemplos concretos']
        ]
      ],
      'behavioral_questions' => [
        [
          'question' => 'Cuéntame sobre un proyecto desafiante que hayas liderado',
          'star_framework' => 'Situación, Tarea, Acción, Resultado',
          'ideal_answer_elements' => ['Contexto claro', 'Acciones especí­ficas', 'Resultados medibles']
        ]
      ],
      'cultural_fit_questions' => ['Ãƒâ€šÃ‚Â¿Cómo prefieres trabajar en equipo?'],
      'candidate_specific_questions' => ['Preguntas basadas en revisión de CV'],
      'interview_flow' => ['Rapport building', 'Preguntas técnicas', 'Preguntas comportamentales', 'Q&A'],
      'estimated_duration' => '60',
      'evaluation_criteria' => ['Competencia técnica', 'Fit cultural', 'Comunicación'],
      'fallback_generated' => true
    ];
  }

  private function createFallbackPrediction()
  {
    return [
      'success_probability' => 70,
      'performance_prediction' => 'medium',
      'retention_probability' => 70,
      'time_to_productivity' => '3-6 meses',
      'success_factors' => ['Requiere Anáslisis  más detallado'],
      'risk_factors' => ['Anáslisis limitado sin datos históricos'],
      'recommendations' => ['Realizar entrevista técnica detallada'],
      'confidence_level' => 50,
      'key_indicators_to_monitor' => ['Progreso en primeros 90 dí­as'],
      'similar_profiles_performance' => 'Datos insuficientes',
      'fallback_analysis' => true
    ];
  }

  private function createFallbackTimeEstimate()
  {
    return [
      'estimated_days' => 45,
      'confidence_range' => '30-60 dí­as',
      'factors_affecting_timeline' => ['Disponibilidad de candidatos', 'Complejidad del rol'],
      'recommendations_to_accelerate' => ['Optimizar job posting', 'Ampliar canales de sourcing'],
      'fallback_analysis' => true
    ];
  }

  private function createFallbackSourcingTemplates($sourceType)
  {
    return [
      'templates' => [
        [
          'name' => 'Template bí¡sico ' . $sourceType,
          'subject' => 'Oportunidad profesional que podrí­a interesarte',
          'message' => 'Hola [NOMBRE], he visto tu perfil y creo que podrí­as estar interesado/a en esta oportunidad...',
          'personalization_tips' => ['Mencionar experiencia especí­fica', 'Conectar con intereses del candidato']
        ]
      ],
      'fallback_generated' => true
    ];
  }

  private function createFallbackDiversityAnalysis()
  {
    return [
      'diversity_score' => 50,
      'analysis' => 'Anáslisis de diversidad requiere datos  más especí­ficos',
      'recommendations' => ['Ampliar fuentes de reclutamiento', 'Revisar criterios de selección'],
      'fallback_analysis' => true
    ];
  }

  // Métodos de validación de estructura (similares a otros servicios)
  private function validateJobDescriptionStructure($data)
  {
    $data['generated_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['generated_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validateQuestionsStructure($data)
  {
    $data['generated_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['generated_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validatePredictionStructure($data)
  {
    $data['analyzed_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['analyzed_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validateTimeEstimateStructure($data)
  {
    $data['estimated_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['estimated_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validateSourcingStructure($data)
  {
    $data['generated_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['generated_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validateDiversityStructure($data)
  {
    $data['analyzed_with'] = 'Unified AI Service - ' . $this->aiService->getCurrentProvider()->getProviderName();
    $data['analyzed_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  /**
   * Construye prompts adicionales
   */
  private function buildTimeToFillPrompt($jobData, $marketData)
  {
    return 'Estima el tiempo necesario para cubrir esta posición basí¡ndote en las caracterí­sticas del rol.

TRABAJO:
Tí­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Nivel: ' . ($jobData['level'] ?? 'No especificado') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Ubicación: ' . ($jobData['location'] ?? 'No especificada') . '
Modalidad: ' . ($jobData['remote_type'] ?? 'No especificada') . '
Salario: ' . ($jobData['salary_range'] ?? 'No especificado') . '

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "estimated_days": 30,
  "confidence_range": "Rango en dí­as",
  "factors_affecting_timeline": ["Factores que afectan el tiempo"],
  "recommendations_to_accelerate": ["Cómo acelerar el proceso"],
  "market_difficulty": "easy|medium|hard",
  "candidate_availability": "high|medium|low"
}';
  }

  private function buildSourcingPrompt($jobData, $sourceType)
  {
    return "Genera templates de outreach efectivos para sourcing pasivo en {$sourceType}.

TRABAJO:
Tí­tulo: " . ($jobData['title'] ?? 'No especificado') . '
Empresa: ' . ($jobData['company'] ?? 'No especificada') . '
Beneficios clave: ' . implode(', ', $jobData['benefits'] ?? []) . "

Responde ÚNICAMENTE con JSON ví¡lido:
{
  \"templates\": [
    {
      \"name\": \"Nombre del template\",
      \"subject\": \"Asunto del mensaje\",
      \"message\": \"Mensaje completo con placeholders\",
      \"personalization_tips\": [\"Tips para personalizar\"],
      \"best_practices\": [\"Mejores prí¡cticas de uso\"]
    }
  ],
  \"platform_specific_tips\": [\"Tips especí­ficos para {$sourceType}\"],
  \"response_rate_expectations\": \"Tasa de respuesta esperada\"
}";
  }

  private function buildDiversityPrompt($candidatesData, $diversityMetrics)
  {
    return 'Analiza la diversidad de este grupo de candidatos de manera objetiva y constructiva.

CANDIDATOS: ' . count($candidatesData) . ' candidatos en el pipeline
MÉTRICAS A ANALIZAR: ' . implode(', ', $diversityMetrics) . '

INSTRUCCIONES:
- Analiza patrones sin hacer identificaciones especí­ficas
- Proporciona insights constructivos
- Sugiere mejoras para aumentar diversidad
- Mantén enfoque profesional y legal

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "diversity_score": 75,
  "analysis": "Anáslisis general de diversidad",
  "strengths": ["Aspectos positivos de diversidad"],
  "improvement_areas": ["Áreas de mejora"],
  "recommendations": ["Recomendaciones especí­ficas"],
  "benchmark_comparison": "Comparación con estí¡ndares de industria"
}';
  }

  /**
   * Llamada a OpenAI (método estí¡ndar)
   */
  private function callOpenAI($prompt)
  {
    // Este método ha sido reemplazado por UnifiedAIService
    // Se mantiene por compatibilidad pero ya no se usa
    error_log('ContentGenerationService::callOpenAI is deprecated. Use UnifiedAIService instead.');
    return null;
  }
}
