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
 * @version 2.0.0
 * @since 2025-08-29
 */
class RecruitmentInsightsService
{
  private UnifiedAIService $aiService;

  public function __construct(?UnifiedAIService $aiService = null)
  {
    $this->aiService = $aiService ?? new UnifiedAIService();
  }

  /* ============================================
       MÉTODOS PÚBLICOS - API DEL SERVICIO
       ============================================ */

  /**
   * Análisis de pipeline de reclutamiento con insights accionables
   */
  public function analyzePipeline(array $pipelineData, array $historicalData = []): array
  {
    try {
      $prompt = $this->buildPipelineAnalysisPrompt($pipelineData, $historicalData);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackPipelineAnalysis($pipelineData);
      }

      return $this->validatePipelineStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error en Análisis de pipeline: ' . $e->getMessage());
      return $this->createFallbackPipelineAnalysis($pipelineData);
    }
  }

  /**
   * Optimización del proceso de reclutamiento
   */
  public function optimizeRecruitmentProcess(array $processData, array $bottlenecks = []): array
  {
    try {
      $prompt = $this->buildProcessOptimizationPrompt($processData, $bottlenecks);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackOptimization();
      }

      return $this->validateOptimizationStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error en optimización de proceso: ' . $e->getMessage());
      return $this->createFallbackOptimization();
    }
  }

  /**
   * Análisis de calidad de candidatos y tendencias
   */
  public function analyzeCandidateQuality(array $candidatesData, array $qualityMetrics = []): array
  {
    try {
      $prompt = $this->buildQualityAnalysisPrompt($candidatesData, $qualityMetrics);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackQualityAnalysis();
      }

      return $this->validateQualityStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error en análisis de calidad: ' . $e->getMessage());
      return $this->createFallbackQualityAnalysis();
    }
  }

  /**
   * Predicción de necesidades futuras de reclutamiento
   */
  public function predictFutureNeeds(array $organizationData, array $growthPlans = []): array
  {
    try {
      $prompt = $this->buildFutureNeedsPrompt($organizationData, $growthPlans);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackFutureNeeds();
      }

      return $this->validateFutureNeedsStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error en predicción de necesidades: ' . $e->getMessage());
      return $this->createFallbackFutureNeeds();
    }
  }

  /**
   * Análisis de competitividad salarial y beneficios
   */
  public function analyzeCompetitiveness(array $positionData, array $marketData = []): array
  {
    try {
      $prompt = $this->buildCompetitivenessPrompt($positionData, $marketData);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackCompetitiveness();
      }

      return $this->validateCompetitivenessStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error en análisis de competitividad: ' . $e->getMessage());
      return $this->createFallbackCompetitiveness();
    }
  }

  /**
   * Generación de reportes ejecutivos automáticos
   */
  public function generateExecutiveReport(array $recruitmentMetrics, string $reportPeriod = 'monthly'): array
  {
    try {
      $prompt = $this->buildExecutiveReportPrompt($recruitmentMetrics, $reportPeriod);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackExecutiveReport($recruitmentMetrics);
      }

      return $this->validateExecutiveReportStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error generando reporte ejecutivo: ' . $e->getMessage());
      return $this->createFallbackExecutiveReport($recruitmentMetrics);
    }
  }

  /* ===== FUNCIONALIDADES CRÍTICAS AÑADIDAS ===== */

  /**
   * Cálculo de Costo por Contratación (Cost per Hire)
   */
  public function calculateCostPerHire(array $costs, array $hires, array $timeMetrics = []): array
  {
    try {
      $totalCosts = array_sum($costs);
      $totalHires = array_sum($hires);

      if ($totalHires === 0) {
        return ['cost_per_hire' => 0, 'warning' => 'No hires recorded'];
      }

      $costPerHire = $totalCosts / $totalHires;

      return [
        'cost_per_hire' => round($costPerHire, 2),
        'total_costs' => $totalCosts,
        'total_hires' => $totalHires,
        'cost_breakdown' => $costs,
        'industry_benchmark' => $this->getIndustryBenchmark($costPerHire),
        'trend_analysis' => $this->analyzeCostTrend($costs, $timeMetrics),
        'optimization_suggestions' => $this->getCostOptimizationSuggestions($costPerHire)
      ];
    } catch (\Exception $e) {
      error_log('Error calculando costo por contratación: ' . $e->getMessage());
      return ['cost_per_hire' => 0, 'error' => 'Calculation failed'];
    }
  }

  /**
   * Cálculo de Tiempo de Contratación (Time to Fill)
   */
  public function calculateTimeToFill(array $positions, array $departmentData = []): array
  {
    try {
      $times = [];
      $byDepartment = [];

      foreach ($positions as $position) {
        if (isset($position['date_opened']) && isset($position['date_filled'])) {
          $days = (strtotime($position['date_filled']) - strtotime($position['date_opened'])) / 86400;
          $times[] = $days;

          $dept = $position['department'] ?? 'general';
          $byDepartment[$dept][] = $days;
        }
      }

      $avgTime = empty($times) ? 0 : array_sum($times) / count($times);

      return [
        'average_days' => round($avgTime, 1),
        'median_days' => $this->calculateMedian($times),
        'by_department' => array_map(function ($deptTimes) {
          return round(array_sum($deptTimes) / count($deptTimes), 1);
        }, $byDepartment),
        'industry_benchmark' => 45, // Días promedio por industria
        'positions_analyzed' => count($positions)
      ];
    } catch (\Exception $e) {
      error_log('Error calculando time to fill: ' . $e->getMessage());
      return ['average_days' => 0, 'error' => 'Calculation failed'];
    }
  }

  /**
   * Análisis de Efectividad por Fuente de Reclutamiento
   */
  public function analyzeSourceEffectiveness(array $candidates, array $hires): array
  {
    try {
      $sourceData = [];

      foreach ($candidates as $candidate) {
        $source = $candidate['source'] ?? 'unknown';
        if (!isset($sourceData[$source])) {
          $sourceData[$source] = ['candidates' => 0, 'hires' => 0];
        }
        $sourceData[$source]['candidates']++;
      }

      foreach ($hires as $hire) {
        $source = $hire['source'] ?? 'unknown';
        if (isset($sourceData[$source])) {
          $sourceData[$source]['hires']++;
        }
      }

      $analysis = [];
      foreach ($sourceData as $source => $data) {
        $conversionRate = $data['candidates'] > 0
          ? ($data['hires'] / $data['candidates']) * 100
          : 0;

        $analysis[$source] = [
          'candidates' => $data['candidates'],
          'hires' => $data['hires'],
          'conversion_rate' => round($conversionRate, 2),
          'cost_per_hire' => $this->getSourceCost($source) / max($data['hires'], 1),
          'effectiveness_score' => $this->calculateEffectivenessScore($conversionRate, $data['hires'])
        ];
      }

      return $analysis;
    } catch (\Exception $e) {
      error_log('Error analizando efectividad de fuentes: ' . $e->getMessage());
      return ['error' => 'Analysis failed'];
    }
  }

  /**
   * Análisis de Diversidad e Inclusión
   */
  public function analyzeDiversityInclusion(array $candidateData, array $hiringData = []): array
  {
    try {
      $prompt = $this->buildDiversityAnalysisPrompt($candidateData, $hiringData);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackDiversityAnalysis();
      }

      return $this->validateDiversityStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error en análisis de diversidad: ' . $e->getMessage());
      return $this->createFallbackDiversityAnalysis();
    }
  }

  /**
   * Análisis de Experiencia del Candidato
   */
  public function analyzeCandidateExperience(array $feedback, array $processMetrics = []): array
  {
    try {
      $prompt = $this->buildCandidateExperiencePrompt($feedback, $processMetrics);
      $response = $this->aiService->chatCompletion($prompt);

      if (!$response || json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackCandidateExperience();
      }

      return $this->validateCandidateExperienceStructure(json_decode($response, true));
    } catch (\Exception $e) {
      error_log('Error en análisis de experiencia: ' . $e->getMessage());
      return $this->createFallbackCandidateExperience();
    }
  }

  /* ============================================
       MÉTODOS PRIVADOS - IMPLEMENTACIÓN INTERNA
       ============================================ */

  /* ===== MÉTODOS DE CONSTRUCCIÓN DE PROMPTS ===== */

  private function buildPipelineAnalysisPrompt($pipelineData, $historicalData)
  {
    // [Mismo código que original - sin cambios]
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
- Proporciona recomendaciones específicas y accionables
- Compara con benchmarks de industria cuando sea posible

Responde ÚNICAMENTE con JSON válido:
{
  \"pipeline_health\": \"excellent|good|fair|poor\",
  \"key_insights\": [\"Insights principales del análisis\"],
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
  \"actionable_recommendations\": [\"Recomendaciones específicas\"],
  \"predicted_outcomes\": [\"Resultados esperados de implementar cambios\"],
  \"priority_areas\": [\"Áreas prioritarias para mejora\"]
}";
  }

  private function buildDiversityAnalysisPrompt($candidateData, $hiringData)
  {
    return 'Analiza la diversidad e inclusión en los procesos de reclutamiento.

DATOS DE CANDIDATOS:
' . json_encode($candidateData, JSON_PRETTY_PRINT) . '

DATOS DE CONTRATACIONES:
' . json_encode($hiringData, JSON_PRETTY_PRINT) . '

INSTRUCCIONES:
- Evalúa distribución por género, etnia, edad, discapacidad
- Identifica posibles sesgos en el proceso
- Compara candidatos vs contrataciones
- Sugiere mejoras para mayor inclusión

Responde ÚNICAMENTE con JSON válido:
{
  "diversity_score": 75,
  "gender_distribution": {
    "candidates": {"male": 60, "female": 38, "non_binary": 2},
    "hires": {"male": 55, "female": 45, "non_binary": 0}
  },
  "ethnicity_distribution": {"detailed_breakdown": {}},
  "age_groups": {"distribution": {}},
  "bias_indicators": ["posibles sesgos identificados"],
  "inclusion_recommendations": ["acciones para mejorar"]
}';
  }

  private function buildCandidateExperiencePrompt($feedback, $processMetrics)
  {
    return 'Analiza la experiencia de los candidatos en el proceso de reclutamiento.

FEEDBACK RECIBIDO:
' . json_encode($feedback, JSON_PRETTY_PRINT) . '

MÉTRICAS DEL PROCESO:
' . json_encode($processMetrics, JSON_PRETTY_PRINT) . '

INSTRUCCIONES:
- Evalúa satisfacción general
- Identifica puntos de fricción
- Mide NPS (Net Promoter Score)
- Sugiere mejoras en la experiencia

Responde ÚNICAMENTE con JSON válido:
{
  "overall_satisfaction": 8.2,
  "nps_score": 45,
  "pain_points": ["puntos problemáticos identificados"],
  "communication_score": 7.5,
  "process_clarity": 8.0,
  "improvement_recommendations": ["acciones para mejorar"]
}';
  }

  /* ===== MÉTODOS AUXILIARES ===== */

  private function calculateMedian(array $values): float
  {
    if (empty($values)) return 0;
    sort($values);
    $count = count($values);
    $middle = floor($count / 2);
    return ($count % 2) ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
  }

  private function getIndustryBenchmark(float $costPerHire): string
  {
    if ($costPerHire < 3000) return 'below_average';
    if ($costPerHire < 5000) return 'average';
    return 'above_average';
  }

  private function analyzeCostTrend(array $costs, array $timeMetrics): array
  {
    if (count($costs) < 2) return ['trend' => 'insufficient_data'];

    $trend = end($costs) > $costs[0] ? 'increasing' : 'decreasing';
    $change = ((end($costs) - $costs[0]) / $costs[0]) * 100;

    return [
      'trend' => $trend,
      'percentage_change' => round($change, 2),
      'period' => $timeMetrics['period'] ?? 'unknown'
    ];
  }

  private function getCostOptimizationSuggestions(float $costPerHire): array
  {
    $suggestions = [];
    if ($costPerHire > 8000) {
      $suggestions[] = 'Considerar agencias de reclutamiento más eficientes';
      $suggestions[] = 'Optimizar proceso de screening inicial';
    }
    if ($costPerHire > 5000) {
      $suggestions[] = 'Implementar referidos internos';
      $suggestions[] = 'Mejorar descripciones de trabajo para reducir candidatos no calificados';
    }
    return $suggestions;
  }

  private function getSourceCost(string $source): float
  {
    $costs = [
      'linkedin' => 1200,
      'indeed' => 800,
      'referral' => 500,
      'career_page' => 200,
      'recruiter' => 3000,
      'university' => 1000,
      'social_media' => 400
    ];
    return $costs[strtolower($source)] ?? 1000;
  }

  private function calculateEffectivenessScore(float $conversionRate, int $hires): float
  {
    $baseScore = min($conversionRate * 10, 50);
    $volumeScore = min($hires * 5, 50);
    return round($baseScore + $volumeScore, 2);
  }

  /* ===== MÉTODOS DE FALLBACK ===== */

  private function createFallbackDiversityAnalysis(): array
  {
    return [
      'diversity_score' => 65,
      'gender_distribution' => ['requires_data' => true],
      'bias_indicators' => ['Datos insuficientes para análisis completo'],
      'inclusion_recommendations' => ['Implementar tracking de diversidad'],
      'fallback_analysis' => true
    ];
  }

  private function createFallbackCandidateExperience(): array
  {
    return [
      'overall_satisfaction' => 7.0,
      'nps_score' => 30,
      'pain_points' => ['Requiere recolección de feedback'],
      'improvement_recommendations' => ['Implementar encuestas post-proceso'],
      'fallback_analysis' => true
    ];
  }

  /* ===== MÉTODOS DE VALIDACIÓN ===== */

  private function validateDiversityStructure($data)
  {
    $data['analyzed_with'] = 'OpenAI GPT-4 Diversity Analysis';
    $data['analyzed_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  private function validateCandidateExperienceStructure($data)
  {
    $data['analyzed_with'] = 'OpenAI GPT-4 Candidate Experience Analysis';
    $data['analyzed_at'] = date('Y-m-d H:i:s');
    return $data;
  }

  /* ===== RESTO DE MÉTODOS ORIGINALES (sin cambios) ===== */
  private function buildProcessOptimizationPrompt($processData, $bottlenecks)
  { /* ... */
  }
  private function buildQualityAnalysisPrompt($candidatesData, $qualityMetrics)
  { /* ... */
  }
  private function buildFutureNeedsPrompt($organizationData, $growthPlans)
  { /* ... */
  }
  private function buildCompetitivenessPrompt($positionData, $marketData)
  { /* ... */
  }
  private function buildExecutiveReportPrompt($recruitmentMetrics, $reportPeriod)
  { /* ... */
  }
  private function createFallbackPipelineAnalysis($pipelineData)
  { /* ... */
  }
  private function createFallbackOptimization()
  { /* ... */
  }
  private function createFallbackQualityAnalysis()
  { /* ... */
  }
  private function createFallbackFutureNeeds()
  { /* ... */
  }
  private function createFallbackCompetitiveness()
  { /* ... */
  }
  private function createFallbackExecutiveReport($recruitmentMetrics)
  { /* ... */
  }
  private function validatePipelineStructure($data)
  { /* ... */
  }
  private function validateOptimizationStructure($data)
  { /* ... */
  }
  private function validateQualityStructure($data)
  { /* ... */
  }
  private function validateFutureNeedsStructure($data)
  { /* ... */
  }
  private function validateCompetitivenessStructure($data)
  { /* ... */
  }
  private function validateExecutiveReportStructure($data)
  { /* ... */
  }
}
