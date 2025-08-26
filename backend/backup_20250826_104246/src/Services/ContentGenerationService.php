<?php

namespace Services;

/**
 * AI-Powered Content Generation & Predictive Analysis Service
 *
 * Servicio para generación automática de contenido (job descriptions,
 * preguntas de entrevista) y análisis predictivo básico.
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-10
 */
class ContentGenerationService
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

        if (!$this->apiKey) {
            throw new \Exception('OpenAI API key no configurada para ContentGenerationService');
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
            $prompt = $this->buildJobDescriptionPrompt($jobInputs);
            $response = $this->callOpenAI($prompt);

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
            $prompt = $this->buildInterviewQuestionsPrompt($candidateData, $jobData, $interviewType);
            $response = $this->callOpenAI($prompt);

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
            $prompt = $this->buildPredictivePrompt($candidateData, $jobData, $historicalData);
            $response = $this->callOpenAI($prompt);

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
            $prompt = $this->buildTimeToFillPrompt($jobData, $marketData);
            $response = $this->callOpenAI($prompt);

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
            $prompt = $this->buildSourcingPrompt($jobData, $sourceType);
            $response = $this->callOpenAI($prompt);

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
            $prompt = $this->buildDiversityPrompt($candidatesData, $diversityMetrics);
            $response = $this->callOpenAI($prompt);

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
        return 'Genera una job description completa y atractiva basada en estos inputs mínimos.

INPUTS BÁSICOS:
Título: ' . ($jobInputs['title'] ?? 'No especificado') . '
Departamento: ' . ($jobInputs['department'] ?? 'No especificado') . '
Nivel: ' . ($jobInputs['level'] ?? 'No especificado') . '
Modalidad: ' . ($jobInputs['remote_type'] ?? 'No especificada') . '
Ubicación: ' . ($jobInputs['location'] ?? 'No especificada') . '
Rango salarial: ' . ($jobInputs['salary_range'] ?? 'No especificado') . '
Skills clave: ' . implode(', ', $jobInputs['key_skills'] ?? []) . '
Descripción breve: ' . ($jobInputs['brief_description'] ?? 'No especificada') . '

INSTRUCCIONES:
- Crea una descripción atractiva y profesional
- Incluye responsabilidades específicas y realistas
- Detalla requisitos técnicos y experiencia
- Añade beneficios y cultura de empresa
- Optimiza para atraer candidatos quality

Responde ÚNICAMENTE con JSON válido:
{
  "title": "Título optimizado del puesto",
  "summary": "Resumen atractivo del rol",
  "responsibilities": ["Lista de responsabilidades principales"],
  "required_skills": ["Skills técnicas requeridas"],
  "preferred_skills": ["Skills deseables"],
  "experience_requirements": "Experiencia mínima requerida",
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
        return 'Genera preguntas de entrevista específicas y efectivas para este candidato y posición.

CANDIDATO:
Nombre: ' . ($candidateData['nombre'] ?? 'No especificado') . '
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Educación: ' . json_encode($candidateData['educacion'] ?? []) . '

TRABAJO:
Título: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . "

TIPO DE ENTREVISTA: {$interviewType}

INSTRUCCIONES:
- Genera preguntas específicas para este candidato
- Incluye preguntas técnicas relevantes
- Añade preguntas comportamentales (STAR method)
- Incluye preguntas para evaluar fit cultural
- Evita preguntas discriminatorias o ilegales

Responde ÚNICAMENTE con JSON válido:
{
  \"technical_questions\": [
    {
      \"question\": \"Pregunta técnica específica\",
      \"purpose\": \"Qué evalúa esta pregunta\",
      \"follow_up\": \"Pregunta de seguimiento\",
      \"red_flags\": [\"Respuestas que serían preocupantes\"]
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
  \"candidate_specific_questions\": [\"Preguntas específicas basadas en el CV\"],
  \"interview_flow\": [\"Orden sugerido de preguntas\"],
  \"estimated_duration\": \"Duración estimada en minutos\",
  \"evaluation_criteria\": [\"Criterios para evaluar respuestas\"]
}";
    }

    /**
     * Construye prompt para análisis predictivo
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
Título: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Ubicación: ' . ($jobData['location'] ?? 'No especificada') . "

{$historicalInfo}

INSTRUCCIONES:
- Analiza factores de éxito predictivos
- Considera experiencia, skills, fit, estabilidad laboral
- Evalúa riesgos y fortalezas
- Proporciona recomendaciones accionables

Responde ÚNICAMENTE con JSON válido:
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
          'experience_requirements' => 'Experiencia relevante en el área',
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
              'question' => '¿Puedes describir tu experiencia más relevante para este puesto?',
              'purpose' => 'Evaluar experiencia técnica',
              'follow_up' => '¿Qué desafíos enfrentaste y cómo los resolviste?',
              'red_flags' => ['Respuestas vagas', 'Falta de ejemplos concretos']
            ]
          ],
          'behavioral_questions' => [
            [
              'question' => 'Cuéntame sobre un proyecto desafiante que hayas liderado',
              'star_framework' => 'Situación, Tarea, Acción, Resultado',
              'ideal_answer_elements' => ['Contexto claro', 'Acciones específicas', 'Resultados medibles']
            ]
          ],
          'cultural_fit_questions' => ['¿Cómo prefieres trabajar en equipo?'],
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
          'success_factors' => ['Requiere análisis más detallado'],
          'risk_factors' => ['Análisis limitado sin datos históricos'],
          'recommendations' => ['Realizar entrevista técnica detallada'],
          'confidence_level' => 50,
          'key_indicators_to_monitor' => ['Progreso en primeros 90 días'],
          'similar_profiles_performance' => 'Datos insuficientes',
          'fallback_analysis' => true
        ];
    }

    private function createFallbackTimeEstimate()
    {
        return [
          'estimated_days' => 45,
          'confidence_range' => '30-60 días',
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
              'name' => 'Template básico ' . $sourceType,
              'subject' => 'Oportunidad profesional que podría interesarte',
              'message' => 'Hola [NOMBRE], he visto tu perfil y creo que podrías estar interesado/a en esta oportunidad...',
              'personalization_tips' => ['Mencionar experiencia específica', 'Conectar con intereses del candidato']
            ]
          ],
          'fallback_generated' => true
        ];
    }

    private function createFallbackDiversityAnalysis()
    {
        return [
          'diversity_score' => 50,
          'analysis' => 'Análisis de diversidad requiere datos más específicos',
          'recommendations' => ['Ampliar fuentes de reclutamiento', 'Revisar criterios de selección'],
          'fallback_analysis' => true
        ];
    }

    // Métodos de validación de estructura (similares a otros servicios)
    private function validateJobDescriptionStructure($data)
    {
        $data['generated_with'] = 'OpenAI GPT-4 Content Generation Service';
        $data['generated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateQuestionsStructure($data)
    {
        $data['generated_with'] = 'OpenAI GPT-4 Content Generation Service';
        $data['generated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validatePredictionStructure($data)
    {
        $data['analyzed_with'] = 'OpenAI GPT-4 Predictive Analysis';
        $data['analyzed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateTimeEstimateStructure($data)
    {
        $data['estimated_with'] = 'OpenAI GPT-4 Time Estimation';
        $data['estimated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateSourcingStructure($data)
    {
        $data['generated_with'] = 'OpenAI GPT-4 Sourcing Templates';
        $data['generated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateDiversityStructure($data)
    {
        $data['analyzed_with'] = 'OpenAI GPT-4 Diversity Analysis';
        $data['analyzed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    /**
     * Construye prompts adicionales
     */
    private function buildTimeToFillPrompt($jobData, $marketData)
    {
        return 'Estima el tiempo necesario para cubrir esta posición basándote en las características del rol.

TRABAJO:
Título: ' . ($jobData['title'] ?? 'No especificado') . '
Nivel: ' . ($jobData['level'] ?? 'No especificado') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Ubicación: ' . ($jobData['location'] ?? 'No especificada') . '
Modalidad: ' . ($jobData['remote_type'] ?? 'No especificada') . '
Salario: ' . ($jobData['salary_range'] ?? 'No especificado') . '

Responde ÚNICAMENTE con JSON válido:
{
  "estimated_days": 30,
  "confidence_range": "Rango en días",
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
Título: " . ($jobData['title'] ?? 'No especificado') . '
Empresa: ' . ($jobData['company'] ?? 'No especificada') . '
Beneficios clave: ' . implode(', ', $jobData['benefits'] ?? []) . "

Responde ÚNICAMENTE con JSON válido:
{
  \"templates\": [
    {
      \"name\": \"Nombre del template\",
      \"subject\": \"Asunto del mensaje\",
      \"message\": \"Mensaje completo con placeholders\",
      \"personalization_tips\": [\"Tips para personalizar\"],
      \"best_practices\": [\"Mejores prácticas de uso\"]
    }
  ],
  \"platform_specific_tips\": [\"Tips específicos para {$sourceType}\"],
  \"response_rate_expectations\": \"Tasa de respuesta esperada\"
}";
    }

    private function buildDiversityPrompt($candidatesData, $diversityMetrics)
    {
        return 'Analiza la diversidad de este grupo de candidatos de manera objetiva y constructiva.

CANDIDATOS: ' . count($candidatesData) . ' candidatos en el pipeline
MÉTRICAS A ANALIZAR: ' . implode(', ', $diversityMetrics) . '

INSTRUCCIONES:
- Analiza patrones sin hacer identificaciones específicas
- Proporciona insights constructivos
- Sugiere mejoras para aumentar diversidad
- Mantén enfoque profesional y legal

Responde ÚNICAMENTE con JSON válido:
{
  "diversity_score": 75,
  "analysis": "Análisis general de diversidad",
  "strengths": ["Aspectos positivos de diversidad"],
  "improvement_areas": ["Áreas de mejora"],
  "recommendations": ["Recomendaciones específicas"],
  "benchmark_comparison": "Comparación con estándares de industria"
}';
    }

    /**
     * Llamada a OpenAI (método estándar)
     */
    private function callOpenAI($prompt)
    {
        $data = [
          'model' => 'gpt-4',
          'messages' => [
            [
              'role' => 'system',
              'content' => 'Eres un experto en recruitment, generación de contenido profesional y análisis predictivo de talento. Generas contenido de alta calidad, útil y accionable. Responde ÚNICAMENTE con JSON válido.'
            ],
            [
              'role' => 'user',
              'content' => $prompt
            ]
          ],
          'max_tokens' => 3000,
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
            error_log("OpenAI API Error en Content Generation: HTTP {$httpCode} - {$response}");
            return null;
        }

        $responseData = json_decode($response, true);
        return $responseData['choices'][0]['message']['content'] ?? null;
    }
}
