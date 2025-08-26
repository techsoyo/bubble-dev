<?php declare(strict_types=1);
namespace Services;

/**
 * AI-Powered Content Generation & Predictive Analysis Service
 *
 * Servicio para generaciÃƒÆ’Ã‚Â³n automÃƒÆ’Ã‚Â¡tica de contenido (job descriptions,
 * preguntas de entrevista) y anÃƒÆ’Ã‚Â¡lisis predictivo bÃƒÆ’Ã‚Â¡sico.
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
     * Genera job description automÃƒÆ’Ã‚Â¡tica basada en inputs mÃƒÆ’Ã‚Â­nimos
     *
     * @param array $jobInputs Datos bÃƒÆ’Ã‚Â¡sicos del trabajo
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
     * AnÃƒÆ’Ã‚Â¡lisis predictivo de ÃƒÆ’Ã‚Â©xito en el puesto
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos del trabajo
     * @param array $historicalData Datos histÃƒÆ’Ã‚Â³ricos de contrataciones (opcional)
     * @return array PredicciÃƒÆ’Ã‚Â³n de ÃƒÆ’Ã‚Â©xito
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
            error_log('Error en anÃƒÆ’Ã‚Â¡lisis predictivo: ' . $e->getMessage());
            return $this->createFallbackPrediction();
        }
    }

    /**
     * Estima tiempo para cubrir la posiciÃƒÆ’Ã‚Â³n
     *
     * @param array $jobData Datos del trabajo
     * @param array $marketData Datos del mercado (opcional)
     * @return array EstimaciÃƒÆ’Ã‚Â³n de tiempo
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
            error_log('Error estimando tiempo de contrataciÃƒÆ’Ã‚Â³n: ' . $e->getMessage());
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
     * AnÃƒÆ’Ã‚Â¡lisis de diversidad automÃƒÆ’Ã‚Â¡tico
     *
     * @param array $candidatesData Lista de candidatos
     * @param array $diversityMetrics MÃƒÆ’Ã‚Â©tricas a analizar
     * @return array AnÃƒÆ’Ã‚Â¡lisis de diversidad
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
            error_log('Error en anÃƒÆ’Ã‚Â¡lisis de diversidad: ' . $e->getMessage());
            return $this->createFallbackDiversityAnalysis();
        }
    }

    /**
     * Construye prompt para job description
     */
    private function buildJobDescriptionPrompt($jobInputs)
    {
        return 'Genera una job description completa y atractiva basada en estos inputs mÃƒÆ’Ã‚Â­nimos.

INPUTS BÃƒÆ’Ã‚ÂSICOS:
TÃƒÆ’Ã‚Â­tulo: ' . ($jobInputs['title'] ?? 'No especificado') . '
Departamento: ' . ($jobInputs['department'] ?? 'No especificado') . '
Nivel: ' . ($jobInputs['level'] ?? 'No especificado') . '
Modalidad: ' . ($jobInputs['remote_type'] ?? 'No especificada') . '
UbicaciÃƒÆ’Ã‚Â³n: ' . ($jobInputs['location'] ?? 'No especificada') . '
Rango salarial: ' . ($jobInputs['salary_range'] ?? 'No especificado') . '
Skills clave: ' . implode(', ', $jobInputs['key_skills'] ?? []) . '
DescripciÃƒÆ’Ã‚Â³n breve: ' . ($jobInputs['brief_description'] ?? 'No especificada') . '

INSTRUCCIONES:
- Crea una descripciÃƒÆ’Ã‚Â³n atractiva y profesional
- Incluye responsabilidades especÃƒÆ’Ã‚Â­ficas y realistas
- Detalla requisitos tÃƒÆ’Ã‚Â©cnicos y experiencia
- AÃƒÆ’Ã‚Â±ade beneficios y cultura de empresa
- Optimiza para atraer candidatos quality

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "title": "TÃƒÆ’Ã‚Â­tulo optimizado del puesto",
  "summary": "Resumen atractivo del rol",
  "responsibilities": ["Lista de responsabilidades principales"],
  "required_skills": ["Skills tÃƒÆ’Ã‚Â©cnicas requeridas"],
  "preferred_skills": ["Skills deseables"],
  "experience_requirements": "Experiencia mÃƒÆ’Ã‚Â­nima requerida",
  "education_requirements": "EducaciÃƒÆ’Ã‚Â³n requerida",
  "benefits": ["Lista de beneficios"],
  "company_culture": "DescripciÃƒÆ’Ã‚Â³n de cultura empresarial",
  "growth_opportunities": ["Oportunidades de crecimiento"],
  "application_process": "Proceso de aplicaciÃƒÆ’Ã‚Â³n",
  "keywords_seo": ["Keywords para SEO y sourcing"],
  "estimated_applications": "NÃƒÆ’Ã‚Âºmero estimado de aplicaciones esperadas"
}';
    }

    /**
     * Construye prompt para preguntas de entrevista
     */
    private function buildInterviewQuestionsPrompt($candidateData, $jobData, $interviewType)
    {
        return 'Genera preguntas de entrevista especÃƒÆ’Ã‚Â­ficas y efectivas para este candidato y posiciÃƒÆ’Ã‚Â³n.

CANDIDATO:
Nombre: ' . ($candidateData['nombre'] ?? 'No especificado') . '
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
EducaciÃƒÆ’Ã‚Â³n: ' . json_encode($candidateData['educacion'] ?? []) . '

TRABAJO:
TÃƒÆ’Ã‚Â­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . "

TIPO DE ENTREVISTA: {$interviewType}

INSTRUCCIONES:
- Genera preguntas especÃƒÆ’Ã‚Â­ficas para este candidato
- Incluye preguntas tÃƒÆ’Ã‚Â©cnicas relevantes
- AÃƒÆ’Ã‚Â±ade preguntas comportamentales (STAR method)
- Incluye preguntas para evaluar fit cultural
- Evita preguntas discriminatorias o ilegales

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  \"technical_questions\": [
    {
      \"question\": \"Pregunta tÃƒÆ’Ã‚Â©cnica especÃƒÆ’Ã‚Â­fica\",
      \"purpose\": \"QuÃƒÆ’Ã‚Â© evalÃƒÆ’Ã‚Âºa esta pregunta\",
      \"follow_up\": \"Pregunta de seguimiento\",
      \"red_flags\": [\"Respuestas que serÃƒÆ’Ã‚Â­an preocupantes\"]
    }
  ],
  \"behavioral_questions\": [
    {
      \"question\": \"Pregunta comportamental\",
      \"star_framework\": \"CÃƒÆ’Ã‚Â³mo aplicar STAR\",
      \"ideal_answer_elements\": [\"Elementos de una respuesta ideal\"]
    }
  ],
  \"cultural_fit_questions\": [\"Preguntas para evaluar fit cultural\"],
  \"candidate_specific_questions\": [\"Preguntas especÃƒÆ’Ã‚Â­ficas basadas en el CV\"],
  \"interview_flow\": [\"Orden sugerido de preguntas\"],
  \"estimated_duration\": \"DuraciÃƒÆ’Ã‚Â³n estimada en minutos\",
  \"evaluation_criteria\": [\"Criterios para evaluar respuestas\"]
}";
    }

    /**
     * Construye prompt para anÃƒÆ’Ã‚Â¡lisis predictivo
     */
    private function buildPredictivePrompt($candidateData, $jobData, $historicalData)
    {
        $historicalInfo = !empty($historicalData) ?
          "DATOS HISTÃƒÆ’Ã¢â‚¬Å“RICOS:\n" . json_encode($historicalData) . "\n" :
          "No hay datos histÃƒÆ’Ã‚Â³ricos disponibles.\n";

        return 'Analiza y predice la probabilidad de ÃƒÆ’Ã‚Â©xito de este candidato en el puesto.

CANDIDATO:
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
EducaciÃƒÆ’Ã‚Â³n: ' . json_encode($candidateData['educacion'] ?? []) . '
UbicaciÃƒÆ’Ã‚Â³n: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '

TRABAJO:
TÃƒÆ’Ã‚Â­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
UbicaciÃƒÆ’Ã‚Â³n: ' . ($jobData['location'] ?? 'No especificada') . "

{$historicalInfo}

INSTRUCCIONES:
- Analiza factores de ÃƒÆ’Ã‚Â©xito predictivos
- Considera experiencia, skills, fit, estabilidad laboral
- EvalÃƒÆ’Ã‚Âºa riesgos y fortalezas
- Proporciona recomendaciones accionables

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  \"success_probability\": 85,
  \"performance_prediction\": \"high|medium|low\",
  \"retention_probability\": 80,
  \"time_to_productivity\": \"Tiempo estimado hasta ser productivo\",
  \"success_factors\": [\"Factores que favorecen el ÃƒÆ’Ã‚Â©xito\"],
  \"risk_factors\": [\"Factores de riesgo identificados\"],
  \"recommendations\": [\"Recomendaciones para maximizar ÃƒÆ’Ã‚Â©xito\"],
  \"confidence_level\": 75,
  \"key_indicators_to_monitor\": [\"Indicadores a seguir post-hiring\"],
  \"similar_profiles_performance\": \"Rendimiento de perfiles similares histÃƒÆ’Ã‚Â³ricos\"
}";
    }

    /**
     * MÃƒÆ’Ã‚Â©todos de fallback y validaciÃƒÆ’Ã‚Â³n
     */
    private function createFallbackJobDescription($jobInputs)
    {
        return [
          'title' => $jobInputs['title'] ?? 'PosiciÃƒÆ’Ã‚Â³n Vacante',
          'summary' => 'Excelente oportunidad de crecimiento profesional',
          'responsibilities' => ['Responsabilidades a definir'],
          'required_skills' => $jobInputs['key_skills'] ?? ['Por definir'],
          'preferred_skills' => ['Habilidades adicionales valoradas'],
          'experience_requirements' => 'Experiencia relevante en el ÃƒÆ’Ã‚Â¡rea',
          'education_requirements' => 'EducaciÃƒÆ’Ã‚Â³n acorde al nivel del puesto',
          'benefits' => ['Beneficios competitivos'],
          'company_culture' => 'Ambiente de trabajo colaborativo',
          'growth_opportunities' => ['Oportunidades de desarrollo'],
          'application_process' => 'Aplicar a travÃƒÆ’Ã‚Â©s de nuestra plataforma',
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
              'question' => 'Ãƒâ€šÃ‚Â¿Puedes describir tu experiencia mÃƒÆ’Ã‚Â¡s relevante para este puesto?',
              'purpose' => 'Evaluar experiencia tÃƒÆ’Ã‚Â©cnica',
              'follow_up' => 'Ãƒâ€šÃ‚Â¿QuÃƒÆ’Ã‚Â© desafÃƒÆ’Ã‚Â­os enfrentaste y cÃƒÆ’Ã‚Â³mo los resolviste?',
              'red_flags' => ['Respuestas vagas', 'Falta de ejemplos concretos']
            ]
          ],
          'behavioral_questions' => [
            [
              'question' => 'CuÃƒÆ’Ã‚Â©ntame sobre un proyecto desafiante que hayas liderado',
              'star_framework' => 'SituaciÃƒÆ’Ã‚Â³n, Tarea, AcciÃƒÆ’Ã‚Â³n, Resultado',
              'ideal_answer_elements' => ['Contexto claro', 'Acciones especÃƒÆ’Ã‚Â­ficas', 'Resultados medibles']
            ]
          ],
          'cultural_fit_questions' => ['Ãƒâ€šÃ‚Â¿CÃƒÆ’Ã‚Â³mo prefieres trabajar en equipo?'],
          'candidate_specific_questions' => ['Preguntas basadas en revisiÃƒÆ’Ã‚Â³n de CV'],
          'interview_flow' => ['Rapport building', 'Preguntas tÃƒÆ’Ã‚Â©cnicas', 'Preguntas comportamentales', 'Q&A'],
          'estimated_duration' => '60',
          'evaluation_criteria' => ['Competencia tÃƒÆ’Ã‚Â©cnica', 'Fit cultural', 'ComunicaciÃƒÆ’Ã‚Â³n'],
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
          'success_factors' => ['Requiere anÃƒÆ’Ã‚Â¡lisis mÃƒÆ’Ã‚Â¡s detallado'],
          'risk_factors' => ['AnÃƒÆ’Ã‚Â¡lisis limitado sin datos histÃƒÆ’Ã‚Â³ricos'],
          'recommendations' => ['Realizar entrevista tÃƒÆ’Ã‚Â©cnica detallada'],
          'confidence_level' => 50,
          'key_indicators_to_monitor' => ['Progreso en primeros 90 dÃƒÆ’Ã‚Â­as'],
          'similar_profiles_performance' => 'Datos insuficientes',
          'fallback_analysis' => true
        ];
    }

    private function createFallbackTimeEstimate()
    {
        return [
          'estimated_days' => 45,
          'confidence_range' => '30-60 dÃƒÆ’Ã‚Â­as',
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
              'name' => 'Template bÃƒÆ’Ã‚Â¡sico ' . $sourceType,
              'subject' => 'Oportunidad profesional que podrÃƒÆ’Ã‚Â­a interesarte',
              'message' => 'Hola [NOMBRE], he visto tu perfil y creo que podrÃƒÆ’Ã‚Â­as estar interesado/a en esta oportunidad...',
              'personalization_tips' => ['Mencionar experiencia especÃƒÆ’Ã‚Â­fica', 'Conectar con intereses del candidato']
            ]
          ],
          'fallback_generated' => true
        ];
    }

    private function createFallbackDiversityAnalysis()
    {
        return [
          'diversity_score' => 50,
          'analysis' => 'AnÃƒÆ’Ã‚Â¡lisis de diversidad requiere datos mÃƒÆ’Ã‚Â¡s especÃƒÆ’Ã‚Â­ficos',
          'recommendations' => ['Ampliar fuentes de reclutamiento', 'Revisar criterios de selecciÃƒÆ’Ã‚Â³n'],
          'fallback_analysis' => true
        ];
    }

    // MÃƒÆ’Ã‚Â©todos de validaciÃƒÆ’Ã‚Â³n de estructura (similares a otros servicios)
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
        return 'Estima el tiempo necesario para cubrir esta posiciÃƒÆ’Ã‚Â³n basÃƒÆ’Ã‚Â¡ndote en las caracterÃƒÆ’Ã‚Â­sticas del rol.

TRABAJO:
TÃƒÆ’Ã‚Â­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Nivel: ' . ($jobData['level'] ?? 'No especificado') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
UbicaciÃƒÆ’Ã‚Â³n: ' . ($jobData['location'] ?? 'No especificada') . '
Modalidad: ' . ($jobData['remote_type'] ?? 'No especificada') . '
Salario: ' . ($jobData['salary_range'] ?? 'No especificado') . '

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "estimated_days": 30,
  "confidence_range": "Rango en dÃƒÆ’Ã‚Â­as",
  "factors_affecting_timeline": ["Factores que afectan el tiempo"],
  "recommendations_to_accelerate": ["CÃƒÆ’Ã‚Â³mo acelerar el proceso"],
  "market_difficulty": "easy|medium|hard",
  "candidate_availability": "high|medium|low"
}';
    }

    private function buildSourcingPrompt($jobData, $sourceType)
    {
        return "Genera templates de outreach efectivos para sourcing pasivo en {$sourceType}.

TRABAJO:
TÃƒÆ’Ã‚Â­tulo: " . ($jobData['title'] ?? 'No especificado') . '
Empresa: ' . ($jobData['company'] ?? 'No especificada') . '
Beneficios clave: ' . implode(', ', $jobData['benefits'] ?? []) . "

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  \"templates\": [
    {
      \"name\": \"Nombre del template\",
      \"subject\": \"Asunto del mensaje\",
      \"message\": \"Mensaje completo con placeholders\",
      \"personalization_tips\": [\"Tips para personalizar\"],
      \"best_practices\": [\"Mejores prÃƒÆ’Ã‚Â¡cticas de uso\"]
    }
  ],
  \"platform_specific_tips\": [\"Tips especÃƒÆ’Ã‚Â­ficos para {$sourceType}\"],
  \"response_rate_expectations\": \"Tasa de respuesta esperada\"
}";
    }

    private function buildDiversityPrompt($candidatesData, $diversityMetrics)
    {
        return 'Analiza la diversidad de este grupo de candidatos de manera objetiva y constructiva.

CANDIDATOS: ' . count($candidatesData) . ' candidatos en el pipeline
MÃƒÆ’Ã¢â‚¬Â°TRICAS A ANALIZAR: ' . implode(', ', $diversityMetrics) . '

INSTRUCCIONES:
- Analiza patrones sin hacer identificaciones especÃƒÆ’Ã‚Â­ficas
- Proporciona insights constructivos
- Sugiere mejoras para aumentar diversidad
- MantÃƒÆ’Ã‚Â©n enfoque profesional y legal

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "diversity_score": 75,
  "analysis": "AnÃƒÆ’Ã‚Â¡lisis general de diversidad",
  "strengths": ["Aspectos positivos de diversidad"],
  "improvement_areas": ["ÃƒÆ’Ã‚Âreas de mejora"],
  "recommendations": ["Recomendaciones especÃƒÆ’Ã‚Â­ficas"],
  "benchmark_comparison": "ComparaciÃƒÆ’Ã‚Â³n con estÃƒÆ’Ã‚Â¡ndares de industria"
}';
    }

    /**
     * Llamada a OpenAI (mÃƒÆ’Ã‚Â©todo estÃƒÆ’Ã‚Â¡ndar)
     */
    private function callOpenAI($prompt)
    {
        $data = [
          'model' => 'gpt-4',
          'messages' => [
            [
              'role' => 'system',
              'content' => 'Eres un experto en recruitment, generaciÃƒÆ’Ã‚Â³n de contenido profesional y anÃƒÆ’Ã‚Â¡lisis predictivo de talento. Generas contenido de alta calidad, ÃƒÆ’Ã‚Âºtil y accionable. Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido.'
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
