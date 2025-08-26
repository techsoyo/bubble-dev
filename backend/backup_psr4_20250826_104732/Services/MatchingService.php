<?php declare(strict_types=1);

namespace Services\MatchingService.php\Services;

/**
 * AI-Powered Candidate Matching Service
 *
 * Servicio para scoring automÃ¡tico y matching inteligente de candidatos
 * usando Ollama local para anÃ¡lisis avanzado de compatibilidad.
 *
 * @package Backend\Services
 * @version 2.0.0
 * @since 2025-08-15
 */
class MatchingService
{
  // private $ollamaService;

  public function __construct()
  {
    // $this->ollamaService = new OllamaService();
  }

  /**
   * Calcula el score de matching entre candidato y trabajo
   *
   * @param array $candidateData Datos extraÃ­dos del CV
   * @param array $jobData Datos del trabajo
   * @return array Score y anÃ¡lisis detallado
   */
  public function calculateMatchingScore($candidateData, $jobData)
  {
    try {
      $prompt = $this->buildMatchingPrompt($candidateData, $jobData);
      $response = $this->callOpenAI($prompt);

      if (!$response) {
        return $this->createFallbackScore($candidateData, $jobData);
      }

      $matchingData = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->createFallbackScore($candidateData, $jobData);
      }

      return $this->validateMatchingStructure($matchingData);
    } catch (\Exception $e) {
      error_log('Error en matching score: ' . $e->getMessage());
      return $this->createFallbackScore($candidateData, $jobData);
    }
  }

  /**
   * Analiza mÃºltiples candidatos contra una posiciÃ³n
   *
   * @param array $candidates Lista de candidatos
   * @param array $jobData Datos del trabajo
   * @return array Ranking de candidatos ordenado por score
   */
  public function rankCandidates($candidates, $jobData)
  {
    $rankedCandidates = [];

    foreach ($candidates as $candidate) {
      $matching = $this->calculateMatchingScore($candidate, $jobData);
      $rankedCandidates[] = [
        'candidate_id' => $candidate['id'] ?? null,
        'candidate_data' => $candidate,
        'matching_score' => $matching['overall_score'],
        'matching_analysis' => $matching,
        'ranking_timestamp' => date('Y-m-d H:i:s')
      ];
    }

    // Ordenar por score descendente
    usort($rankedCandidates, function ($a, $b) {
      return $b['matching_score'] <=> $a['matching_score'];
    });

    return $rankedCandidates;
  }

  /**
   * Identifica candidatos sobrecalificados o subcalificados
   */
  public function analyzeQualificationFit($candidateData, $jobData)
  {
    $prompt = 'Analiza si este candidato estÃ¡ sobrecalificado, subcalificado o perfectamente calificado para esta posiciÃ³n.

CANDIDATO:
Experiencia: ' . ($candidateData['puestos_anteriores'] ? count($candidateData['puestos_anteriores']) . ' posiciones anteriores' : 'Sin experiencia registrada') . '
Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
EducaciÃ³n: ' . (isset($candidateData['educacion'][0]['titulo']) ? $candidateData['educacion'][0]['titulo'] : 'No especificada') . '

TRABAJO:
TÃ­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
Nivel requerido: ' . ($jobData['level'] ?? 'No especificado') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Experiencia mÃ­nima: ' . ($jobData['min_experience'] ?? 'No especificada') . '

Responde ÃšNICAMENTE con JSON vÃ¡lido en este formato:
{
  "qualification_level": "perfect_fit|overqualified|underqualified",
  "explanation": "RazÃ³n detallada del anÃ¡lisis",
  "risk_level": "low|medium|high",
  "recommendations": ["lista de recomendaciones"],
  "salary_expectation": "below_range|in_range|above_range|unknown"
}';

    $response = $this->callOpenAI($prompt);

    if ($response) {
      $analysis = json_decode($response, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $analysis;
      }
    }

    return [
      'qualification_level' => 'unknown',
      'explanation' => 'No se pudo analizar el nivel de calificaciÃ³n',
      'risk_level' => 'medium',
      'recommendations' => ['Revisar manualmente'],
      'salary_expectation' => 'unknown'
    ];
  }

  /**
   * Construye el prompt para anÃ¡lisis de matching
   */
  private function buildMatchingPrompt($candidateData, $jobData)
  {
    return 'Analiza la compatibilidad entre este candidato y trabajo. Calcula un score detallado considerando mÃºltiples factores.

DATOS DEL CANDIDATO:
Nombre: ' . ($candidateData['nombre'] ?? 'No especificado') . '
Email: ' . ($candidateData['email'] ?? 'No especificado') . '
UbicaciÃ³n: ' . ($candidateData['ubicacion_actual'] ?? 'No especificada') . '
Experiencia: ' . json_encode($candidateData['puestos_anteriores'] ?? []) . '
EducaciÃ³n: ' . json_encode($candidateData['educacion'] ?? []) . '
Hard Skills: ' . implode(', ', $candidateData['hard_skills'] ?? []) . '
Soft Skills: ' . implode(', ', $candidateData['soft_skills'] ?? []) . '
Idiomas: ' . implode(', ', $candidateData['idiomas'] ?? []) . '
Disponibilidad: ' . ($candidateData['disponibilidad'] ?? 'No especificada') . '

DATOS DEL TRABAJO:
TÃ­tulo: ' . ($jobData['title'] ?? 'No especificado') . '
DescripciÃ³n: ' . ($jobData['description'] ?? 'No especificada') . '
UbicaciÃ³n: ' . ($jobData['location'] ?? 'No especificada') . '
Modalidad: ' . ($jobData['remote_type'] ?? 'No especificada') . '
Salario: ' . ($jobData['salary_range'] ?? 'No especificado') . '
Skills requeridas: ' . implode(', ', $jobData['required_skills'] ?? []) . '
Experiencia mÃ­nima: ' . ($jobData['min_experience'] ?? 'No especificada') . ' aÃ±os
Nivel: ' . ($jobData['level'] ?? 'No especificado') . '

Responde ÃšNICAMENTE con JSON vÃ¡lido en este formato exacto:
{
  "overall_score": 85,
  "breakdown_scores": {
    "technical_skills": 90,
    "experience_level": 80,
    "education_fit": 85,
    "location_compatibility": 95,
    "soft_skills": 75,
    "language_requirements": 100,
    "salary_expectations": 70
  },
  "strengths": [
    "Lista de fortalezas del candidato para este puesto"
  ],
  "concerns": [
    "Lista de posibles preocupaciones o gaps"
  ],
  "recommendation": "strong_match|good_match|potential_match|poor_match",
  "next_steps": [
    "Acciones recomendadas para el proceso"
  ],
  "interview_focus_areas": [
    "Ãreas especÃ­ficas a explorar en entrevista"
  ],
  "estimated_fit_probability": 85,
  "risk_factors": [
    "Factores de riesgo identificados"
  ]
}';
  }

  /**
   * Realiza llamada a OpenAI
   */
  private function callOpenAI($prompt)
  {
    $data = [
      'model' => 'gpt-4',
      'messages' => [
        [
          'role' => 'system',
          'content' => 'Eres un experto en recruitment y talent matching. Analiza candidatos vs trabajos con precisiÃ³n profesional. Responde ÃšNICAMENTE con JSON vÃ¡lido sin explicaciones adicionales.'
        ],
        [
          'role' => 'user',
          'content' => $prompt
        ]
      ],
      'max_tokens' => 2500,
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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
      error_log("OpenAI cURL Error en Matching: {$error}");
      return null;
    }

    if ($httpCode !== 200) {
      error_log("OpenAI API Error en Matching: HTTP {$httpCode} - {$response}");
      return null;
    }

    $responseData = json_decode($response, true);

    if (!isset($responseData['choices'][0]['message']['content'])) {
      error_log('OpenAI Response Error en Matching: ' . $response);
      return null;
    }

    return trim($responseData['choices'][0]['message']['content']);
  }

  /**
   * Crea un score de respaldo si OpenAI falla
   */
  public function createFallbackScore($candidateData, $jobData)
  {
    // AnÃ¡lisis bÃ¡sico por reglas simples
    $score = 50; // Base score

    // Boost por skills match
    $candidateSkills = $candidateData['hard_skills'] ?? [];
    $requiredSkills = $jobData['required_skills'] ?? [];

    if (!empty($candidateSkills) && !empty($requiredSkills)) {
      $matchingSkills = array_intersect(
        array_map('strtolower', $candidateSkills),
        array_map('strtolower', $requiredSkills)
      );
      $skillsScore = (count($matchingSkills) / count($requiredSkills)) * 40;
      $score += $skillsScore;
    }

    return [
      'overall_score' => min(95, max(15, $score)),
      'breakdown_scores' => [
        'technical_skills' => min(95, max(15, $score)),
        'experience_level' => 50,
        'education_fit' => 50,
        'location_compatibility' => 50,
        'soft_skills' => 50,
        'language_requirements' => 50,
        'salary_expectations' => 50
      ],
      'strengths' => ['AnÃ¡lisis automÃ¡tico bÃ¡sico realizado'],
      'concerns' => ['Requiere revisiÃ³n manual detallada'],
      'recommendation' => 'potential_match',
      'next_steps' => ['Revisar manualmente', 'Agendar screening call'],
      'interview_focus_areas' => ['Verificar skills tÃ©cnicas', 'Evaluar fit cultural'],
      'estimated_fit_probability' => min(95, max(15, $score)),
      'risk_factors' => ['Score calculado con algoritmo bÃ¡sico'],
      'fallback_analysis' => true,
      'processed_at' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Valida y enriquece la estructura del matching
   */
  private function validateMatchingStructure($matchingData)
  {
    // Asegurar que todos los campos requeridos existen
    $required_fields = [
      'overall_score',
      'breakdown_scores',
      'strengths',
      'concerns',
      'recommendation',
      'next_steps',
      'interview_focus_areas',
      'estimated_fit_probability',
      'risk_factors'
    ];

    foreach ($required_fields as $field) {
      if (!isset($matchingData[$field])) {
        $matchingData[$field] = $this->getDefaultValue($field);
      }
    }

    // Validar score range
    if ($matchingData['overall_score'] > 100) {
      $matchingData['overall_score'] = 100;
    }
    if ($matchingData['overall_score'] < 0) {
      $matchingData['overall_score'] = 0;
    }

    // Agregar metadata
    $matchingData['processed_with'] = 'OpenAI GPT-4 Matching Service';
    $matchingData['processed_at'] = date('Y-m-d H:i:s');
    $matchingData['version'] = '1.0.0';

    return $matchingData;
  }

  /**
   * Valores por defecto para campos faltantes
   */
  private function getDefaultValue($field)
  {
    $defaults = [
      'overall_score' => 50,
      'breakdown_scores' => [
        'technical_skills' => 50,
        'experience_level' => 50,
        'education_fit' => 50,
        'location_compatibility' => 50,
        'soft_skills' => 50,
        'language_requirements' => 50,
        'salary_expectations' => 50
      ],
      'strengths' => ['Requiere anÃ¡lisis manual'],
      'concerns' => ['Score incompleto'],
      'recommendation' => 'potential_match',
      'next_steps' => ['Revisar manualmente'],
      'interview_focus_areas' => ['EvaluaciÃ³n general'],
      'estimated_fit_probability' => 50,
      'risk_factors' => ['AnÃ¡lisis incompleto']
    ];

    return $defaults[$field] ?? null;
  }
}
