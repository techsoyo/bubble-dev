<?php declare(strict_types=1);

namespace Services\ContentGenerationService.php\Services;

/**
 * AI-Powered Content Generation & Predictive Analysis Service
 *
 * Servicio para generaciÃ³n automÃ¡tica de contenido (job descriptions,
 * preguntas de entrevista) y anÃ¡lisis predictivo bÃ¡sico.
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
     * Genera job description automÃ¡tica basada en inputs mÃ­nimos
     *
     * @param array $jobInputs Datos bÃ¡sicos del trabajo
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
     * AnÃ¡lisis predictivo de Ã©xito en el puesto
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos del trabajo
     * @param array $historicalData Datos histÃ³ricos de contrataciones (opcional)
     * @return array PredicciÃ³n de Ã©xito
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
            error_log('Error en anÃ¡lisis predictivo: ' . $e->getMessage());
            return $this->createFallbackPrediction();
        }
    }

    /**
     * Estima tiempo para cubrir la posiciÃ³n
     *
     * @param array $jobData Datos del trabajo
     * @param array $marketData Datos del mercado (opcional)
     * @return array EstimaciÃ³n de tiempo
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
            error_log('Error estimando tiempo de contrataciÃ³n: ' . $e->getMessage());
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
     * AnÃ¡lisis de diversidad automÃ¡tico
     *
     * @param array $candidatesData Lista de candidatos
     * @param array $diversityMetrics MÃ©tricas a analizar
     * @return array AnÃ¡lisis de diversidad
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
            error_log('Error en anÃ¡lisis de diversidad: ' . $e->getMessage());
            return $this->createFallbackDiversityAnalysis();
        }
    }

    /**
     * Construye prompt para job description
     */
    private function buildJobDescriptionPrompt($jobInputs)
    {
        return 'Genera una job description completa y atractiva basada en estos inputs mÃ­nimos.

INPUTS BÃSICOS:
TÃ­tulo: ' . ($jobInputs['title'] ?? 'No especificado') . '
Departamento: ' . ($jobInputs['department'] ?? 'No especificado') . '
Nivel: ' . ($jobInputs['level'] ?? 'No especificado') . '
Modalidad: ' . ($jobInputs['remote_type'] ?? 'No especificada') . '
UbicaciÃ³n: ' . ($jobInputs['location'] ?? 'No especificada') . '
Rango salarial: ' . ($jobInputs['salary_range'] ?? 'No especificado') . '
Skills clave: ' . implode(', ', $jobInputs['key_skills'] ?? []) . '
DescripciÃ³n breve: ' . ($jobInputs['brief_description'] ?? 'No especificada') . '

INSTRUCCIONES:
- Crea una descripciÃ³n atractiva y profesional
- Incluye responsabilidades especÃ­ficas y realistas
- Detalla requisitos tÃ©cnicos y experiencia
- AÃ±ade beneficios y cultura de empresa
- Optimiza para atraer candidatos quality

Responde ÃšNICAMENTE con JSON vÃ¡lido:
{
  "title": "TÃ­tulo optimizado del puesto",
  "summary": "Resumen atractivo del rol",
  "responsibilities": ["Lista de responsabilidades principales"],
  "required_skills": ["Skills tÃ©cnicas requeridas"],
  "preferred_skills": ["Skills deseables"],
  "experience_requirements": "Experiencia mÃ­nima requerida",
  "education_requirements": "EducaciÃ³n requerida",
  "benefits": ["Lista de beneficios"],
  "company_culture": "DescripciÃ³n de cultura empresarial",
  "growth_opportunities": ["Oportunidades de crecimiento"],
  "application_process": "Proceso de aplicaciÃ³n",
  "keywords_seo": ["Keywords para SEO y sourcing"],
  "estimated_applications": "NÃºmero estimado de aplicaciones esperadas"
}';
    }

    /**
     * Construye prompt para preguntas de entrevista
     */
    private function buildInterviewQuestionsPrompt($candidateData, $jobData, $interviewType)
    {
        return 'Genera preguntas de entrevista especÃ­ficas y efectivas para este candidato y posiciÃ³n.

CANDIDATO:
Nombre: ' . ($candidateData['nombre'] ?? 'No especificado') . '
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
EducaciÃ³n: ' . json_encode($candidateData['educacion'] ?? []) . '

TRABAJO:
TÃ­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . "

TIPO DE ENTREVISTA: {$interviewType}

INSTRUCCIONES:
- Genera preguntas especÃ­ficas para este candidato
- Incluye preguntas tÃ©cnicas relevantes
- AÃ±ade preguntas comportamentales (STAR method)
- Incluye preguntas para evaluar fit cultural
- Evita preguntas discriminatorias o ilegales

Responde ÃšNICAMENTE con JSON vÃ¡lido:
{
  \"technical_questions\": [
    {
      \"question\": \"Pregunta tÃ©cnica especÃ­fica\",
      \"purpose\": \"QuÃ© evalÃºa esta pregunta\",
      \"follow_up\": \"Pregunta de seguimiento\",
      \"red_flags\": [\"Respuestas que serÃ­an preocupantes\"]
    }
  ],
  \"behavioral_questions\": [
    {
      \"question\": \"Pregunta comportamental\",
      \"star_framework\": \"CÃ³mo aplicar STAR\",
      \"ideal_answer_elements\": [\"Elementos de una respuesta ideal\"]
    }
  ],
  \"cultural_fit_questions\": [\"Preguntas para evaluar fit cultural\"],
  \"candidate_specific_questions\": [\"Preguntas especÃ­ficas basadas en el CV\"],
  \"interview_flow\": [\"Orden sugerido de preguntas\"],
  \"estimated_duration\": \"DuraciÃ³n estimada en minutos\",
  \"evaluation_criteria\": [\"Criterios para evaluar respuestas\"]
}";
    }

    /**
     * Construye prompt para anÃ¡lisis predictivo
     */
    private function buildPredictivePrompt($candidateData, $jobData, $historicalData)
    {
        $historicalInfo = !empty($historicalData) ?
          "DATOS HISTÃ“RICOS:\n" . json_encode($historicalData) . "\n" :
          "No hay datos histÃ³ricos disponibles.\n";

        return 'Analiza y predice la probabilidad de Ã©xito de este candidato en el puesto.

CANDIDATO:
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
EducaciÃ³n: ' . json_encode($candidateData['educacion'] ?? []) . '
UbicaciÃ³n: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '

TRABAJO:
TÃ­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Responsabilidades: ' . implode(', ', $jobData['responsibilities'] ?? []) . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
UbicaciÃ³n: ' . ($jobData['location'] ?? 'No especificada') . "

{$historicalInfo}

INSTRUCCIONES:
- Analiza factores de Ã©xito predictivos
- Considera experiencia, skills, fit, estabilidad laboral
- EvalÃºa riesgos y fortalezas
- Proporciona recomendaciones accionables

Responde ÃšNICAMENTE con JSON vÃ¡lido:
{
  \"success_probability\": 85,
  \"performance_prediction\": \"high|medium|low\",
  \"retention_probability\": 80,
  \"time_to_productivity\": \"Tiempo estimado hasta ser productivo\",
  \"success_factors\": [\"Factores que favorecen el Ã©xito\"],
  \"risk_factors\": [\"Factores de riesgo identificados\"],
  \"recommendations\": [\"Recomendaciones para maximizar Ã©xito\"],
  \"confidence_level\": 75,
  \"key_indicators_to_monitor\": [\"Indicadores a seguir post-hiring\"],
  \"similar_profiles_performance\": \"Rendimiento de perfiles similares histÃ³ricos\"
}";
    }

    /**
     * MÃ©todos de fallback y validaciÃ³n
     */
    private function createFallbackJobDescription($jobInputs)
    {
        return [
          'title' => $jobInputs['title'] ?? 'PosiciÃ³n Vacante',
          'summary' => 'Excelente oportunidad de crecimiento profesional',
          'responsibilities' => ['Responsabilidades a definir'],
          'required_skills' => $jobInputs['key_skills'] ?? ['Por definir'],
          'preferred_skills' => ['Habilidades adicionales valoradas'],
          'experience_requirements' => 'Experiencia relevante en el Ã¡rea',
          'education_requirements' => 'EducaciÃ³n acorde al nivel del puesto',
          'benefits' => ['Beneficios competitivos'],
          'company_culture' => 'Ambiente de trabajo colaborativo',
          'growth_opportunities' => ['Oportunidades de desarrollo'],
          'application_process' => 'Aplicar a travÃ©s de nuestra plataforma',
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
              'question' => 'Â¿Puedes describir tu experiencia mÃ¡s relevante para este puesto?',
              'purpose' => 'Evaluar experiencia tÃ©cnica',
              'follow_up' => 'Â¿QuÃ© desafÃ­os enfrentaste y cÃ³mo los resolviste?',
              'red_flags' => ['Respuestas vagas', 'Falta de ejemplos concretos']
            ]
          ],
          'behavioral_questions' => [
            [
              'question' => 'CuÃ©ntame sobre un proyecto desafiante que hayas liderado',
              'star_framework' => 'SituaciÃ³n, Tarea, AcciÃ³n, Resultado',
              'ideal_answer_elements' => ['Contexto claro', 'Acciones especÃ­ficas', 'Resultados medibles']
            ]
          ],
          'cultural_fit_questions' => ['Â¿CÃ³mo prefieres trabajar en equipo?'],
          'candidate_specific_questions' => ['Preguntas basadas en revisiÃ³n de CV'],
          'interview_flow' => ['Rapport building', 'Preguntas tÃ©cnicas', 'Preguntas comportamentales', 'Q&A'],
          'estimated_duration' => '60',
          'evaluation_criteria' => ['Competencia tÃ©cnica', 'Fit cultural', 'ComunicaciÃ³n'],
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
          'success_factors' => ['Requiere anÃ¡lisis mÃ¡s detallado'],
          'risk_factors' => ['AnÃ¡lisis limitado sin datos histÃ³ricos'],
          'recommendations' => ['Realizar entrevista tÃ©cnica detallada'],
          'confidence_level' => 50,
          'key_indicators_to_monitor' => ['Progreso en primeros 90 dÃ­as'],
          'similar_profiles_performance' => 'Datos insuficientes',
          'fallback_analysis' => true
        ];
    }

    private function createFallbackTimeEstimate()
    {
        return [
          'estimated_days' => 45,
          'confidence_range' => '30-60 dÃ­as',
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
              'name' => 'Template bÃ¡sico ' . $sourceType,
              'subject' => 'Oportunidad profesional que podrÃ­a interesarte',
              'message' => 'Hola [NOMBRE], he visto tu perfil y creo que podrÃ­as estar interesado/a en esta oportunidad...',
              'personalization_tips' => ['Mencionar experiencia especÃ­fica', 'Conectar con intereses del candidato']
            ]
          ],
          'fallback_generated' => true
        ];
    }

    private function createFallbackDiversityAnalysis()
    {
        return [
          'diversity_score' => 50,
          'analysis' => 'AnÃ¡lisis de diversidad requiere datos mÃ¡s especÃ­ficos',
          'recommendations' => ['Ampliar fuentes de reclutamiento', 'Revisar criterios de selecciÃ³n'],
          'fallback_analysis' => true
        ];
    }

    // MÃ©todos de validaciÃ³n de estructura (similares a otros servicios)
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
        return 'Estima el tiempo necesario para cubrir esta posiciÃ³n basÃ¡ndote en las caracterÃ­sticas del rol.

TRABAJO:
TÃ­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Nivel: ' . ($jobData['level'] ?? 'No especificado') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
UbicaciÃ³n: ' . ($jobData['location'] ?? 'No especificada') . '
Modalidad: ' . ($jobData['remote_type'] ?? 'No especificada') . '
Salario: ' . ($jobData['salary_range'] ?? 'No especificado') . '

Responde ÃšNICAMENTE con JSON vÃ¡lido:
{
  "estimated_days": 30,
  "confidence_range": "Rango en dÃ­as",
  "factors_affecting_timeline": ["Factores que afectan el tiempo"],
  "recommendations_to_accelerate": ["CÃ³mo acelerar el proceso"],
  "market_difficulty": "easy|medium|hard",
  "candidate_availability": "high|medium|low"
}';
    }

    private function buildSourcingPrompt($jobData, $sourceType)
    {
        return "Genera templates de outreach efectivos para sourcing pasivo en {$sourceType}.

TRABAJO:
TÃ­tulo: " . ($jobData['title'] ?? 'No especificado') . '
Empresa: ' . ($jobData['company'] ?? 'No especificada') . '
Beneficios clave: ' . implode(', ', $jobData['benefits'] ?? []) . "

Responde ÃšNICAMENTE con JSON vÃ¡lido:
{
  \"templates\": [
    {
      \"name\": \"Nombre del template\",
      \"subject\": \"Asunto del mensaje\",
      \"message\": \"Mensaje completo con placeholders\",
      \"personalization_tips\": [\"Tips para personalizar\"],
      \"best_practices\": [\"Mejores prÃ¡cticas de uso\"]
    }
  ],
  \"platform_specific_tips\": [\"Tips especÃ­ficos para {$sourceType}\"],
  \"response_rate_expectations\": \"Tasa de respuesta esperada\"
}";
    }

    private function buildDiversityPrompt($candidatesData, $diversityMetrics)
    {
        return 'Analiza la diversidad de este grupo de candidatos de manera objetiva y constructiva.

CANDIDATOS: ' . count($candidatesData) . ' candidatos en el pipeline
MÃ‰TRICAS A ANALIZAR: ' . implode(', ', $diversityMetrics) . '

INSTRUCCIONES:
- Analiza patrones sin hacer identificaciones especÃ­ficas
- Proporciona insights constructivos
- Sugiere mejoras para aumentar diversidad
- MantÃ©n enfoque profesional y legal

Responde ÃšNICAMENTE con JSON vÃ¡lido:
{
  "diversity_score": 75,
  "analysis": "AnÃ¡lisis general de diversidad",
  "strengths": ["Aspectos positivos de diversidad"],
  "improvement_areas": ["Ãreas de mejora"],
  "recommendations": ["Recomendaciones especÃ­ficas"],
  "benchmark_comparison": "ComparaciÃ³n con estÃ¡ndares de industria"
}';
    }

    /**
     * Llamada a OpenAI (mÃ©todo estÃ¡ndar)
     */
    private function callOpenAI($prompt)
    {
        $data = [
          'model' => 'gpt-4',
          'messages' => [
            [
              'role' => 'system',
              'content' => 'Eres un experto en recruitment, generaciÃ³n de contenido profesional y anÃ¡lisis predictivo de talento. Generas contenido de alta calidad, Ãºtil y accionable. Responde ÃšNICAMENTE con JSON vÃ¡lido.'
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
