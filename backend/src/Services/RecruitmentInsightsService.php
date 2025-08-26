<?php declare(strict_types=1);
namespace Services;

/**
 * AI-Powered Recruitment Insights & Analytics Service
 *
 * Servicio para anÃƒÆ’Ã‚Â¡lisis avanzado de mÃƒÆ’Ã‚Â©tricas de reclutamiento,
 * insights del mercado laboral, y optimizaciÃƒÆ’Ã‚Â³n de procesos.
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-10
 */
class RecruitmentInsightsService
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');

        if (!$this->apiKey) {
            throw new \Exception('OpenAI API key no configurada para RecruitmentInsightsService');
        }
    }

    /**
     * AnÃƒÆ’Ã‚Â¡lisis de pipeline de reclutamiento con insights accionables
     *
     * @param array $pipelineData Datos del pipeline actual
     * @param array $historicalData Datos histÃƒÆ’Ã‚Â³ricos de referencia
     * @return array AnÃƒÆ’Ã‚Â¡lisis completo del pipeline
     */
    public function analyzePipeline($pipelineData, $historicalData = [])
    {
        try {
            $prompt = $this->buildPipelineAnalysisPrompt($pipelineData, $historicalData);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackPipelineAnalysis($pipelineData);
            }

            $analysisData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackPipelineAnalysis($pipelineData);
            }

            return $this->validatePipelineStructure($analysisData);
        } catch (\Exception $e) {
            error_log('Error en anÃƒÆ’Ã‚Â¡lisis de pipeline: ' . $e->getMessage());
            return $this->createFallbackPipelineAnalysis($pipelineData);
        }
    }

    /**
     * OptimizaciÃƒÆ’Ã‚Â³n del proceso de reclutamiento
     *
     * @param array $processData Datos del proceso actual
     * @param array $bottlenecks Cuellos de botella identificados
     * @return array Recomendaciones de optimizaciÃƒÆ’Ã‚Â³n
     */
    public function optimizeRecruitmentProcess($processData, $bottlenecks = [])
    {
        try {
            $prompt = $this->buildProcessOptimizationPrompt($processData, $bottlenecks);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackOptimization();
            }

            $optimizationData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackOptimization();
            }

            return $this->validateOptimizationStructure($optimizationData);
        } catch (\Exception $e) {
            error_log('Error en optimizaciÃƒÆ’Ã‚Â³n de proceso: ' . $e->getMessage());
            return $this->createFallbackOptimization();
        }
    }

    /**
     * AnÃƒÆ’Ã‚Â¡lisis de calidad de candidatos y tendencias
     *
     * @param array $candidatesData Datos de candidatos recientes
     * @param array $qualityMetrics MÃƒÆ’Ã‚Â©tricas de calidad a evaluar
     * @return array AnÃƒÆ’Ã‚Â¡lisis de calidad y tendencias
     */
    public function analyzeCandidateQuality($candidatesData, $qualityMetrics = [])
    {
        try {
            $prompt = $this->buildQualityAnalysisPrompt($candidatesData, $qualityMetrics);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackQualityAnalysis();
            }

            $qualityData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackQualityAnalysis();
            }

            return $this->validateQualityStructure($qualityData);
        } catch (\Exception $e) {
            error_log('Error en anÃƒÆ’Ã‚Â¡lisis de calidad: ' . $e->getMessage());
            return $this->createFallbackQualityAnalysis();
        }
    }

    /**
     * PredicciÃƒÆ’Ã‚Â³n de necesidades futuras de reclutamiento
     *
     * @param array $organizationData Datos de la organizaciÃƒÆ’Ã‚Â³n
     * @param array $growthPlans Planes de crecimiento
     * @return array Predicciones de necesidades futuras
     */
    public function predictFutureNeeds($organizationData, $growthPlans = [])
    {
        try {
            $prompt = $this->buildFutureNeedsPrompt($organizationData, $growthPlans);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackFutureNeeds();
            }

            $needsData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackFutureNeeds();
            }

            return $this->validateFutureNeedsStructure($needsData);
        } catch (\Exception $e) {
            error_log('Error en predicciÃƒÆ’Ã‚Â³n de necesidades: ' . $e->getMessage());
            return $this->createFallbackFutureNeeds();
        }
    }

    /**
     * AnÃƒÆ’Ã‚Â¡lisis de competitividad salarial y beneficios
     *
     * @param array $positionData Datos de la posiciÃƒÆ’Ã‚Â³n
     * @param array $marketData Datos del mercado
     * @return array AnÃƒÆ’Ã‚Â¡lisis de competitividad
     */
    public function analyzeCompetitiveness($positionData, $marketData = [])
    {
        try {
            $prompt = $this->buildCompetitivenessPrompt($positionData, $marketData);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackCompetitiveness();
            }

            $competitivenessData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackCompetitiveness();
            }

            return $this->validateCompetitivenessStructure($competitivenessData);
        } catch (\Exception $e) {
            error_log('Error en anÃƒÆ’Ã‚Â¡lisis de competitividad: ' . $e->getMessage());
            return $this->createFallbackCompetitiveness();
        }
    }

    /**
     * GeneraciÃƒÆ’Ã‚Â³n de reportes ejecutivos automÃƒÆ’Ã‚Â¡ticos
     *
     * @param array $recruitmentMetrics MÃƒÆ’Ã‚Â©tricas de reclutamiento
     * @param string $reportPeriod PerÃƒÆ’Ã‚Â­odo del reporte
     * @return array Reporte ejecutivo generado
     */
    public function generateExecutiveReport($recruitmentMetrics, $reportPeriod = 'monthly')
    {
        try {
            $prompt = $this->buildExecutiveReportPrompt($recruitmentMetrics, $reportPeriod);
            $response = $this->callOpenAI($prompt);

            if (!$response) {
                return $this->createFallbackExecutiveReport($recruitmentMetrics);
            }

            $reportData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createFallbackExecutiveReport($recruitmentMetrics);
            }

            return $this->validateExecutiveReportStructure($reportData);
        } catch (\Exception $e) {
            error_log('Error generando reporte ejecutivo: ' . $e->getMessage());
            return $this->createFallbackExecutiveReport($recruitmentMetrics);
        }
    }

    /**
     * ConstrucciÃƒÆ’Ã‚Â³n de prompts especÃƒÆ’Ã‚Â­ficos
     */
    private function buildPipelineAnalysisPrompt($pipelineData, $historicalData)
    {
        $historicalInfo = !empty($historicalData) ?
          "DATOS HISTÃƒÆ’Ã¢â‚¬Å“RICOS:\n" . json_encode($historicalData) . "\n" :
          "No hay datos histÃƒÆ’Ã‚Â³ricos disponibles.\n";

        return 'Analiza este pipeline de reclutamiento y proporciona insights accionables.

PIPELINE ACTUAL:
Total candidatos: ' . ($pipelineData['total_candidates'] ?? 0) . '
Por etapa: ' . json_encode($pipelineData['candidates_by_stage'] ?? []) . '
Tiempo promedio por etapa: ' . json_encode($pipelineData['avg_time_by_stage'] ?? []) . '
Tasa de conversiÃƒÆ’Ã‚Â³n: ' . json_encode($pipelineData['conversion_rates'] ?? []) . '
Posiciones activas: ' . ($pipelineData['active_positions'] ?? 0) . "

{$historicalInfo}

INSTRUCCIONES:
- Identifica tendencias y patrones
- Detecta cuellos de botella y oportunidades
- Proporciona recomendaciones especÃƒÆ’Ã‚Â­ficas y accionables
- Compara con benchmarks de industria cuando sea posible

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  \"pipeline_health\": \"excellent|good|fair|poor\",
  \"key_insights\": [\"Insights principales del anÃƒÆ’Ã‚Â¡lisis\"],
  \"bottlenecks_identified\": [\"Cuellos de botella detectados\"],
  \"conversion_analysis\": {
    \"strongest_stage\": \"Etapa con mejor conversiÃƒÆ’Ã‚Â³n\",
    \"weakest_stage\": \"Etapa con peor conversiÃƒÆ’Ã‚Â³n\",
    \"improvement_potential\": \"Porcentaje de mejora posible\"
  },
  \"time_to_hire_analysis\": {
    \"current_average\": \"Tiempo promedio actual\",
    \"industry_benchmark\": \"Benchmark de industria\",
    \"optimization_target\": \"Objetivo optimizado\"
  },
  \"actionable_recommendations\": [\"Recomendaciones especÃƒÆ’Ã‚Â­ficas\"],
  \"predicted_outcomes\": [\"Resultados esperados de implementar cambios\"],
  \"priority_areas\": [\"ÃƒÆ’Ã‚Âreas prioritarias para mejora\"]
}";
    }

    private function buildProcessOptimizationPrompt($processData, $bottlenecks)
    {
        return 'Optimiza este proceso de reclutamiento identificando mejoras especÃƒÆ’Ã‚Â­ficas.

PROCESO ACTUAL:
Etapas: ' . json_encode($processData['stages'] ?? []) . '
DuraciÃƒÆ’Ã‚Â³n promedio: ' . ($processData['avg_duration'] ?? 'No especificada') . '
Recursos involucrados: ' . json_encode($processData['resources'] ?? []) . '
AutomatizaciÃƒÆ’Ã‚Â³n actual: ' . ($processData['automation_level'] ?? 'No especificada') . '

CUELLOS DE BOTELLA IDENTIFICADOS:
' . implode("\n", $bottlenecks) . '

INSTRUCCIONES:
- Proporciona optimizaciones especÃƒÆ’Ã‚Â­ficas y medibles
- Prioriza por impacto y facilidad de implementaciÃƒÆ’Ã‚Â³n
- Incluye estimaciones de mejora
- Considera aspectos tecnolÃƒÆ’Ã‚Â³gicos y humanos

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "optimization_score": 75,
  "quick_wins": ["Mejoras de implementaciÃƒÆ’Ã‚Â³n rÃƒÆ’Ã‚Â¡pida"],
  "strategic_improvements": ["Mejoras estratÃƒÆ’Ã‚Â©gicas a largo plazo"],
  "automation_opportunities": ["Oportunidades de automatizaciÃƒÆ’Ã‚Â³n"],
  "process_redesign": {
    "recommended_stages": ["Etapas optimizadas del proceso"],
    "eliminated_steps": ["Pasos que se pueden eliminar"],
    "new_checkpoints": ["Nuevos puntos de control"]
  },
  "expected_improvements": {
    "time_reduction": "ReducciÃƒÆ’Ã‚Â³n esperada de tiempo",
    "cost_savings": "Ahorros estimados",
    "quality_increase": "Mejora en calidad esperada"
  },
  "implementation_roadmap": ["Hoja de ruta para implementaciÃƒÆ’Ã‚Â³n"],
  "success_metrics": ["MÃƒÆ’Ã‚Â©tricas para medir ÃƒÆ’Ã‚Â©xito"]
}';
    }

    private function buildQualityAnalysisPrompt($candidatesData, $qualityMetrics)
    {
        return 'Analiza la calidad de estos candidatos y identifica tendencias.

CANDIDATOS ANALIZADOS: ' . count($candidatesData) . '
MÃƒÆ’Ã¢â‚¬Â°TRICAS DE CALIDAD: ' . implode(', ', $qualityMetrics) . '

INSTRUCCIONES:
- EvalÃƒÆ’Ã‚Âºa calidad general del pipeline
- Identifica patrones en experiencia, skills, educaciÃƒÆ’Ã‚Â³n
- Detecta tendencias temporales
- Sugiere mejoras en sourcing y atracciÃƒÆ’Ã‚Â³n

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "overall_quality_score": 80,
  "quality_trends": ["Tendencias observadas en calidad"],
  "skill_gap_analysis": ["Gaps de skills identificados"],
  "experience_distribution": {
    "junior": "Porcentaje junior",
    "mid": "Porcentaje mid-level",
    "senior": "Porcentaje senior"
  },
  "sourcing_effectiveness": ["AnÃƒÆ’Ã‚Â¡lisis de efectividad por canal"],
  "candidate_persona_insights": ["Insights sobre tipos de candidatos"],
  "quality_improvement_suggestions": ["Sugerencias para mejorar calidad"]
}';
    }

    private function buildFutureNeedsPrompt($organizationData, $growthPlans)
    {
        return 'Predice las necesidades futuras de reclutamiento basÃƒÆ’Ã‚Â¡ndote en los datos organizacionales.

ORGANIZACIÃƒÆ’Ã¢â‚¬Å“N:
TamaÃƒÆ’Ã‚Â±o actual: ' . ($organizationData['current_size'] ?? 'No especificado') . '
Sectores/Departamentos: ' . json_encode($organizationData['departments'] ?? []) . '
Crecimiento histÃƒÆ’Ã‚Â³rico: ' . ($organizationData['historical_growth'] ?? 'No especificado') . '

PLANES DE CRECIMIENTO:
' . json_encode($growthPlans) . '

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "hiring_forecast": {
    "next_quarter": "PredicciÃƒÆ’Ã‚Â³n prÃƒÆ’Ã‚Â³ximo trimestre",
    "next_year": "PredicciÃƒÆ’Ã‚Â³n prÃƒÆ’Ã‚Â³ximo aÃƒÆ’Ã‚Â±o",
    "critical_roles": ["Roles crÃƒÆ’Ã‚Â­ticos a contratar"]
  },
  "skill_demand_trends": ["Skills que serÃƒÆ’Ã‚Â¡n mÃƒÆ’Ã‚Â¡s demandadas"],
  "capacity_planning": ["Recomendaciones de planning de capacidad"],
  "budget_implications": ["Implicaciones presupuestarias"],
  "strategic_recommendations": ["Recomendaciones estratÃƒÆ’Ã‚Â©gicas"]
}';
    }

    private function buildCompetitivenessPrompt($positionData, $marketData)
    {
        return 'Analiza la competitividad de esta posiciÃƒÆ’Ã‚Â³n en el mercado actual.

POSICIÃƒÆ’Ã¢â‚¬Å“N:
TÃƒÆ’Ã‚Â­tulo: ' . ($positionData['title'] ?? 'No especificado') . '
Salario ofrecido: ' . ($positionData['salary_range'] ?? 'No especificado') . '
Beneficios: ' . json_encode($positionData['benefits'] ?? []) . '
UbicaciÃƒÆ’Ã‚Â³n: ' . ($positionData['location'] ?? 'No especificada') . '

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "competitiveness_score": 75,
  "salary_analysis": {
    "market_position": "below|at|above market",
    "recommended_range": "Rango recomendado",
    "adjustment_needed": "Ajuste sugerido"
  },
  "benefits_comparison": ["ComparaciÃƒÆ’Ã‚Â³n con mercado"],
  "competitive_advantages": ["Ventajas competitivas"],
  "areas_for_improvement": ["ÃƒÆ’Ã‚Âreas de mejora"],
  "market_intelligence": ["Inteligencia de mercado relevante"]
}';
    }

    private function buildExecutiveReportPrompt($recruitmentMetrics, $reportPeriod)
    {
        return "Genera un reporte ejecutivo conciso y accionable sobre el rendimiento de reclutamiento.

PERÃƒÆ’Ã‚ÂODO: {$reportPeriod}
MÃƒÆ’Ã¢â‚¬Â°TRICAS: " . json_encode($recruitmentMetrics) . '

INSTRUCCIONES:
- EnfÃƒÆ’Ã‚Â³cate en insights de alto nivel
- Incluye recomendaciones estratÃƒÆ’Ã‚Â©gicas
- Destaca logros y ÃƒÆ’Ã‚Â¡reas de mejora
- MantÃƒÆ’Ã‚Â©n formato ejecutivo (conciso pero completo)

Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido:
{
  "executive_summary": "Resumen ejecutivo del perÃƒÆ’Ã‚Â­odo",
  "key_achievements": ["Logros principales"],
  "performance_metrics": {
    "time_to_hire": "Tiempo promedio de contrataciÃƒÆ’Ã‚Â³n",
    "quality_of_hire": "Calidad de contrataciones",
    "cost_per_hire": "Costo promedio por contrataciÃƒÆ’Ã‚Â³n",
    "source_effectiveness": "Efectividad de fuentes"
  },
  "challenges_identified": ["DesafÃƒÆ’Ã‚Â­os identificados"],
  "strategic_recommendations": ["Recomendaciones estratÃƒÆ’Ã‚Â©gicas"],
  "future_outlook": "Perspectiva para prÃƒÆ’Ã‚Â³ximo perÃƒÆ’Ã‚Â­odo",
  "action_items": ["Items de acciÃƒÆ’Ã‚Â³n prioritarios"]
}';
    }

    /**
     * MÃƒÆ’Ã‚Â©todos de fallback
     */
    private function createFallbackPipelineAnalysis($pipelineData)
    {
        return [
          'pipeline_health' => 'fair',
          'key_insights' => ['AnÃƒÆ’Ã‚Â¡lisis requiere datos mÃƒÆ’Ã‚Â¡s detallados'],
          'bottlenecks_identified' => ['Requiere anÃƒÆ’Ã‚Â¡lisis manual'],
          'conversion_analysis' => [
            'strongest_stage' => 'Por determinar',
            'weakest_stage' => 'Por determinar',
            'improvement_potential' => 'Por evaluar'
          ],
          'actionable_recommendations' => ['Recopilar mÃƒÆ’Ã‚Â©tricas mÃƒÆ’Ã‚Â¡s detalladas'],
          'fallback_analysis' => true
        ];
    }

    private function createFallbackOptimization()
    {
        return [
          'optimization_score' => 50,
          'quick_wins' => ['Estandarizar formatos de entrevista'],
          'strategic_improvements' => ['Implementar ATS mÃƒÆ’Ã‚Â¡s robusto'],
          'automation_opportunities' => ['Automatizar screening inicial'],
          'fallback_analysis' => true
        ];
    }

    private function createFallbackQualityAnalysis()
    {
        return [
          'overall_quality_score' => 60,
          'quality_trends' => ['Requiere anÃƒÆ’Ã‚Â¡lisis histÃƒÆ’Ã‚Â³rico mÃƒÆ’Ã‚Â¡s amplio'],
          'sourcing_effectiveness' => ['Evaluar canales de reclutamiento'],
          'quality_improvement_suggestions' => ['Mejorar job descriptions'],
          'fallback_analysis' => true
        ];
    }

    private function createFallbackFutureNeeds()
    {
        return [
          'hiring_forecast' => [
            'next_quarter' => 'Por determinar segÃƒÆ’Ã‚Âºn crecimiento',
            'critical_roles' => ['Roles tÃƒÆ’Ã‚Â©cnicos especializados']
          ],
          'strategic_recommendations' => ['Desarrollar pipeline de talento'],
          'fallback_analysis' => true
        ];
    }

    private function createFallbackCompetitiveness()
    {
        return [
          'competitiveness_score' => 60,
          'salary_analysis' => [
            'market_position' => 'Requiere investigaciÃƒÆ’Ã‚Â³n de mercado',
            'recommended_range' => 'Evaluar con datos locales'
          ],
          'fallback_analysis' => true
        ];
    }

    private function createFallbackExecutiveReport($metrics)
    {
        return [
          'executive_summary' => 'PerÃƒÆ’Ã‚Â­odo de reclutamiento con actividad estÃƒÆ’Ã‚Â¡ndar',
          'key_achievements' => ['Mantener operaciones de reclutamiento'],
          'performance_metrics' => [
            'time_to_hire' => 'Por medir',
            'quality_of_hire' => 'Por evaluar'
          ],
          'strategic_recommendations' => ['Implementar tracking de mÃƒÆ’Ã‚Â©tricas'],
          'fallback_report' => true
        ];
    }

    /**
     * MÃƒÆ’Ã‚Â©todos de validaciÃƒÆ’Ã‚Â³n
     */
    private function validatePipelineStructure($data)
    {
        $data['analyzed_with'] = 'OpenAI GPT-4 Recruitment Insights';
        $data['analyzed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateOptimizationStructure($data)
    {
        $data['optimized_with'] = 'OpenAI GPT-4 Process Optimization';
        $data['optimized_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateQualityStructure($data)
    {
        $data['analyzed_with'] = 'OpenAI GPT-4 Quality Analysis';
        $data['analyzed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateFutureNeedsStructure($data)
    {
        $data['predicted_with'] = 'OpenAI GPT-4 Future Needs Analysis';
        $data['predicted_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateCompetitivenessStructure($data)
    {
        $data['analyzed_with'] = 'OpenAI GPT-4 Competitiveness Analysis';
        $data['analyzed_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    private function validateExecutiveReportStructure($data)
    {
        $data['generated_with'] = 'OpenAI GPT-4 Executive Reporting';
        $data['generated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

    /**
     * Llamada estÃƒÆ’Ã‚Â¡ndar a OpenAI
     */
    private function callOpenAI($prompt)
    {
        $data = [
          'model' => 'gpt-4',
          'messages' => [
            [
              'role' => 'system',
              'content' => 'Eres un experto en anÃƒÆ’Ã‚Â¡lisis de reclutamiento, insights de talento y optimizaciÃƒÆ’Ã‚Â³n de procesos de RRHH. Generas anÃƒÆ’Ã‚Â¡lisis profundos, accionables y basados en datos. Responde ÃƒÆ’Ã…Â¡NICAMENTE con JSON vÃƒÆ’Ã‚Â¡lido.'
            ],
            [
              'role' => 'user',
              'content' => $prompt
            ]
          ],
          'max_tokens' => 3000,
          'temperature' => 0.2
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
            error_log("OpenAI API Error en Recruitment Insights: HTTP {$httpCode} - {$response}");
            return null;
        }

        $responseData = json_decode($response, true);
        return $responseData['choices'][0]['message']['content'] ?? null;
    }
}
