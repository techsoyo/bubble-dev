<?php

declare(strict_types=1);

namespace Services;

use Services\UnifiedAIService;
use Services\Interfaces\AIProviderInterface;

/**
 * AI-Powered Recruitment Insights & Analytics Service
 *
 * Servicio para análisis avanzado de métricas de reclutamiento,
 * insights del mercado laboral, y optimización de procesos.
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-10
 */
class RecruitmentInsightsService
{
  private UnifiedAIService $aiService;

  public function __construct(?UnifiedAIService $aiService = null)
  {
    $this->aiService = $aiService ?? new UnifiedAIService();
  }

  /**
   * Anáslisis de pipeline de reclutamiento con insights accionables
   *
   * @param array $pipelineData Datos del pipeline actual
   * @param array $historicalData Datos históricos de referencia
   * @return array Anáslisis completo del pipeline
   */
  public function analyzePipeline($pipelineData, $historicalData = [])
  {
    try {
      $prompt = $this->buildPipelineAnalysisPrompt($pipelineData, $historicalData);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response) {
        return $this->createFallbackPipelineAnalysis($pipelineData);
      }

      $analysisData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackPipelineAnalysis($pipelineData);
      }

      return $this->validatePipelineStructure($analysisData);
    } catch (\Exception $e) {
      error_log('Error en Anáslisis de pipeline: ' . $e->getMessage());
      return $this->createFallbackPipelineAnalysis($pipelineData);
    }
  }

  /**
   * Optimización del proceso de reclutamiento
   *
   * @param array $processData Datos del proceso actual
   * @param array $bottlenecks Cuellos de botella identificados
   * @return array Recomendaciones de optimización
   */
  public function optimizeRecruitmentProcess($processData, $bottlenecks = [])
  {
    try {
      $prompt = $this->buildProcessOptimizationPrompt($processData, $bottlenecks);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response) {
        return $this->createFallbackOptimization();
      }

      $optimizationData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackOptimization();
      }

      return $this->validateOptimizationStructure($optimizationData);
    } catch (\Exception $e) {
      error_log('Error en optimizacion de proceso: ' . $e->getMessage());
      return $this->createFallbackOptimization();
    }
  }

  /**
   * Analisis de calidad de candidatos y tendencias
   *
   * @param array $candidatesData Datos de candidatos recientes
   * @param array $qualityMetrics Metrica de calidad a evaluar
   * @return array Anáslisis de calidad y tendencias
   */
  public function analyzeCandidateQuality($candidatesData, $qualityMetrics = [])
  {
    try {
      $prompt = $this->buildQualityAnalysisPrompt($candidatesData, $qualityMetrics);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response) {
        return $this->createFallbackQualityAnalysis();
      }

      $qualityData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackQualityAnalysis();
      }

      return $this->validateQualityStructure($qualityData);
    } catch (\Exception $e) {
      error_log('Error en analisis de calidad: ' . $e->getMessage());
      return $this->createFallbackQualityAnalysis();
    }
  }

  /**
   * Prediccion de necesidades futuras de reclutamiento
   *
   * @param array $organizationData Datos de la organizacion
   * @param array $growthPlans Planes de crecimiento
   * @return array Predicciones de necesidades futuras
   */
  public function predictFutureNeeds($organizationData, $growthPlans = [])
  {
    try {
      $prompt = $this->buildFutureNeedsPrompt($organizationData, $growthPlans);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response) {
        return $this->createFallbackFutureNeeds();
      }

      $needsData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackFutureNeeds();
      }

      return $this->validateFutureNeedsStructure($needsData);
    } catch (\Exception $e) {
      error_log('Error en prediccion de necesidades: ' . $e->getMessage());
      return $this->createFallbackFutureNeeds();
    }
  }

  /**
   * Analisis de competitividad salarial y beneficios
   *
   * @param array $positionData Datos de la posicion
   * @param array $marketData Datos del mercado
   * @return array Analisis de competitividad
   */
  public function analyzeCompetitiveness($positionData, $marketData = [])
  {
    try {
      $prompt = $this->buildCompetitivenessPrompt($positionData, $marketData);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response) {
        return $this->createFallbackCompetitiveness();
      }

      $competitivenessData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackCompetitiveness();
      }

      return $this->validateCompetitivenessStructure($competitivenessData);
    } catch (\Exception $e) {
      error_log('Error en analisis de competitividad: ' . $e->getMessage());
      return $this->createFallbackCompetitiveness();
    }
  }

  /**
   * Generación de reportes ejecutivos automáticos
   *
   * @param array $recruitmentMetrics Métricas de reclutamiento
   * @param string $reportPeriod Período del reporte
   * @return array Reporte ejecutivo generado
   */
  public function generateExecutiveReport($recruitmentMetrics, $reportPeriod = 'monthly')
  {
    try {
      $prompt = $this->buildExecutiveReportPrompt($recruitmentMetrics, $reportPeriod);
      $response = $this->aiService->chatCompletion($prompt);

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
   * Construcción de prompts específicos
   */
  private function buildPipelineAnalysisPrompt($pipelineData, $historicalData)
  {
    $historicalInfo = !empty($historicalData) ?
      "DATOS HISTÓRICOS:\n" . json_encode($historicalData) . "\n" :
      "No hay datos históricos disponibles.\n";

    return 'Analiza este pipeline de reclutamiento y proporciona insights accionables.

PIPELINE ACTUAL:
Total candidatos: ' . ($pipelineData['total_candidates'] ?? 0) . '
Por etapa: ' . json_encode($pipelineData['candidates_by_stage'] ?? []) . '
Tiempo promedio por etapa: ' . json_encode($pipelineData['avg_time_by_stage'] ?? []) . '
Tasa de conversión: ' . json_encode($pipelineData['conversion_rates'] ?? []) . '
Posiciones activas: ' . ($pipelineData['active_positions'] ?? 0) . "

{$historicalInfo}

INSTRUCCIONES:
- Identifica tendencias y patrones
- Detecta cuellos de botella y oportunidades
- Proporciona recomendaciones especí­ficas y accionables
- Compara con benchmarks de industria cuando sea posible

Responde ÚNICAMENTE con JSON ví¡lido:
{
  \"pipeline_health\": \"excellent|good|fair|poor\",
  \"key_insights\": [\"Insights principales del Anáslisis\"],
  \"bottlenecks_identified\": [\"Cuellos de botella detectados\"],
  \"conversion_analysis\": {
    \"strongest_stage\": \"Etapa con mejor conversión\",
    \"weakest_stage\": \"Etapa con peor conversión\",
    \"improvement_potential\": \"Porcentaje de mejora posible\"
  },
  \"time_to_hire_analysis\": {
    \"current_average\": \"Tiempo promedio actual\",
    \"industry_benchmark\": \"Benchmark de industria\",
    \"optimization_target\": \"Objetivo optimizado\"
  },
  \"actionable_recommendations\": [\"Recomendaciones especí­ficas\"],
  \"predicted_outcomes\": [\"Resultados esperados de implementar cambios\"],
  \"priority_areas\": [\"Áreas prioritarias para mejora\"]
}";
  }

  private function buildProcessOptimizationPrompt($processData, $bottlenecks)
  {
    return 'Optimiza este proceso de reclutamiento identificando mejoras especí­ficas.

PROCESO ACTUAL:
Etapas: ' . json_encode($processData['stages'] ?? []) . '
Duración promedio: ' . ($processData['avg_duration'] ?? 'No especificada') . '
Recursos involucrados: ' . json_encode($processData['resources'] ?? []) . '
Automatización actual: ' . ($processData['automation_level'] ?? 'No especificada') . '

CUELLOS DE BOTELLA IDENTIFICADOS:
' . implode("\n", $bottlenecks) . '

INSTRUCCIONES:
- Proporciona optimizaciones especí­ficas y medibles
- Prioriza por impacto y facilidad de implementación
- Incluye estimaciones de mejora
- Considera aspectos tecnológicos y humanos

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "optimization_score": 75,
  "quick_wins": ["Mejoras de implementación rí¡pida"],
  "strategic_improvements": ["Mejoras estratégicas a largo plazo"],
  "automation_opportunities": ["Oportunidades de automatización"],
  "process_redesign": {
    "recommended_stages": ["Etapas optimizadas del proceso"],
    "eliminated_steps": ["Pasos que se pueden eliminar"],
    "new_checkpoints": ["Nuevos puntos de control"]
  },
  "expected_improvements": {
    "time_reduction": "Reducción esperada de tiempo",
    "cost_savings": "Ahorros estimados",
    "quality_increase": "Mejora en calidad esperada"
  },
  "implementation_roadmap": ["Hoja de ruta para implementación"],
  "success_metrics": ["Métricas para medir éxito"]
}';
  }

  private function buildQualityAnalysisPrompt($candidatesData, $qualityMetrics)
  {
    return 'Analiza la calidad de estos candidatos y identifica tendencias.

CANDIDATOS ANALIZADOS: ' . count($candidatesData) . '
MÉTRICAS DE CALIDAD: ' . implode(', ', $qualityMetrics) . '

INSTRUCCIONES:
- Evalúa calidad general del pipeline
- Identifica patrones en experiencia, skills, educación
- Detecta tendencias temporales
- Sugiere mejoras en sourcing y atracción

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "overall_quality_score": 80,
  "quality_trends": ["Tendencias observadas en calidad"],
  "skill_gap_analysis": ["Gaps de skills identificados"],
  "experience_distribution": {
    "junior": "Porcentaje junior",
    "mid": "Porcentaje mid-level",
    "senior": "Porcentaje senior"
  },
  "sourcing_effectiveness": ["Anáslisis de efectividad por canal"],
  "candidate_persona_insights": ["Insights sobre tipos de candidatos"],
  "quality_improvement_suggestions": ["Sugerencias para mejorar calidad"]
}';
  }

  private function buildFutureNeedsPrompt($organizationData, $growthPlans)
  {
    return 'Predice las necesidades futuras de reclutamiento basí¡ndote en los datos organizacionales.

ORGANIZACIÓN:
Tamaí±o actual: ' . ($organizationData['current_size'] ?? 'No especificado') . '
Sectores/Departamentos: ' . json_encode($organizationData['departments'] ?? []) . '
Crecimiento histórico: ' . ($organizationData['historical_growth'] ?? 'No especificado') . '

PLANES DE CRECIMIENTO:
' . json_encode($growthPlans) . '

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "hiring_forecast": {
    "next_quarter": "Predicción próximo trimestre",
    "next_year": "Predicción próximo aí±o",
    "critical_roles": ["Roles crí­ticos a contratar"]
  },
  "skill_demand_trends": ["Skills que serí¡n  más demandadas"],
  "capacity_planning": ["Recomendaciones de planning de capacidad"],
  "budget_implications": ["Implicaciones presupuestarias"],
  "strategic_recommendations": ["Recomendaciones estratégicas"]
}';
  }

  private function buildCompetitivenessPrompt($positionData, $marketData)
  {
    return 'Analiza la competitividad de esta posición en el mercado actual.

POSICIÓN:
Tí­tulo: ' . ($positionData['title'] ?? 'No especificado') . '
Salario ofrecido: ' . ($positionData['salary_range'] ?? 'No especificado') . '
Beneficios: ' . json_encode($positionData['benefits'] ?? []) . '
Ubicación: ' . ($positionData['location'] ?? 'No especificada') . '

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "competitiveness_score": 75,
  "salary_analysis": {
    "market_position": "below|at|above market",
    "recommended_range": "Rango recomendado",
    "adjustment_needed": "Ajuste sugerido"
  },
  "benefits_comparison": ["Comparación con mercado"],
  "competitive_advantages": ["Ventajas competitivas"],
  "areas_for_improvement": ["Áreas de mejora"],
  "market_intelligence": ["Inteligencia de mercado relevante"]
}';
  }

  private function buildExecutiveReportPrompt($recruitmentMetrics, $reportPeriod)
  {
    return "Genera un reporte ejecutivo conciso y accionable sobre el rendimiento de reclutamiento.

PERíODO: {$reportPeriod}
MÉTRICAS: " . json_encode($recruitmentMetrics) . '

INSTRUCCIONES:
- Enfócate en insights de alto nivel
- Incluye recomendaciones estratégicas
- Destaca logros y í¡reas de mejora
- Mantén formato ejecutivo (conciso pero completo)

Responde ÚNICAMENTE con JSON ví¡lido:
{
  "executive_summary": "Resumen ejecutivo del perí­odo",
  "key_achievements": ["Logros principales"],
  "performance_metrics": {
    "time_to_hire": "Tiempo promedio de contratación",
    "quality_of_hire": "Calidad de contrataciones",
    "cost_per_hire": "Costo promedio por contratación",
    "source_effectiveness": "Efectividad de fuentes"
  },
  "challenges_identified": ["Desafí­os identificados"],
  "strategic_recommendations": ["Recomendaciones estratégicas"],
  "future_outlook": "Perspectiva para próximo perí­odo",
  "action_items": ["Items de acción prioritarios"]
}';
  }

  /**
   * Métodos de fallback
   */
  private function createFallbackPipelineAnalysis($pipelineData)
  {
    return [
      'pipeline_health' => 'fair',
      'key_insights' => ['Anáslisis requiere datos  más detallados'],
      'bottlenecks_identified' => ['Requiere Anáslisis manual'],
      'conversion_analysis' => [
        'strongest_stage' => 'Por determinar',
        'weakest_stage' => 'Por determinar',
        'improvement_potential' => 'Por evaluar'
      ],
      'actionable_recommendations' => ['Recopilar métricas  más detalladas'],
      'fallback_analysis' => true
    ];
  }

  private function createFallbackOptimization()
  {
    return [
      'optimization_score' => 50,
      'quick_wins' => ['Estandarizar formatos de entrevista'],
      'strategic_improvements' => ['Implementar ATS  más robusto'],
      'automation_opportunities' => ['Automatizar screening inicial'],
      'fallback_analysis' => true
    ];
  }

  private function createFallbackQualityAnalysis()
  {
    return [
      'overall_quality_score' => 60,
      'quality_trends' => ['Requiere Anáslisis histórico  más amplio'],
      'sourcing_effectiveness' => ['Evaluar canales de reclutamiento'],
      'quality_improvement_suggestions' => ['Mejorar job descriptions'],
      'fallback_analysis' => true
    ];
  }

  private function createFallbackFutureNeeds()
  {
    return [
      'hiring_forecast' => [
        'next_quarter' => 'Por determinar según crecimiento',
        'critical_roles' => ['Roles técnicos especializados']
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
        'market_position' => 'Requiere investigación de mercado',
        'recommended_range' => 'Evaluar con datos locales'
      ],
      'fallback_analysis' => true
    ];
  }

  private function createFallbackExecutiveReport($metrics)
  {
    return [
      'executive_summary' => 'Perí­odo de reclutamiento con actividad estí¡ndar',
      'key_achievements' => ['Mantener operaciones de reclutamiento'],
      'performance_metrics' => [
        'time_to_hire' => 'Por medir',
        'quality_of_hire' => 'Por evaluar'
      ],
      'strategic_recommendations' => ['Implementar tracking de métricas'],
      'fallback_report' => true
    ];
  }

  /**
   * Métodos de validación
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
    $data['generated_with'] = 'Unified AI Service - Multi-Provider Executive Reporting';
    $data['generated_at'] = date('Y-m-d H:i:s');
    return $data;
  }
}
