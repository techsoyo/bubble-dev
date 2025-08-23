<?php

namespace Services;

use Utils\TranslationService as T;

/**
 * Servicio para la comparación y matching entre candidatos y ofertas de trabajo
 */
class JobMatchingService
{
    /**
     * Servicio de Ollama
    //  * @var OllamaService
    //  */
    // private $ollamaService;

    /**
     * Constructor
     */
    public function __construct()
    {
        // $this->ollamaService = new OllamaService();
    }

    /**
     * Evalúa la coincidencia entre un candidato y una oferta de trabajo
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos de la oferta de trabajo
     * @return array Resultado de la evaluación
     */
    public function evaluateMatch($candidateData, $jobData)
    {
        // return $this->ollamaService->calculateMatching(
            $candidateData['id'] ?? 1,
            $jobData['id'] ?? 1
        );
    }

    /**
     * Filtra candidatos según su coincidencia con una oferta de trabajo
     *
     * @param array $candidates Lista de candidatos
     * @param array $jobData Datos de la oferta de trabajo
     * @param int $threshold Umbral mínimo de coincidencia (0-100)
     * @return array Candidatos filtrados con puntuación
     */
    public function filterCandidatesByMatch($candidates, $jobData, $threshold = 60)
    {
        $results = [];

        foreach ($candidates as $candidate) {
            $matchResult = $this->evaluateMatch($candidate, $jobData);

            // Solo incluir candidatos que superen el umbral
            if ($matchResult['match_percentage'] >= $threshold) {
                $results[] = [
                  'candidate' => $candidate,
                  'match' => $matchResult
                ];
            }
        }

        // Ordenar por coincidencia (de mayor a menor)
        usort($results, function ($a, $b) {
            return $b['match']['match_percentage'] - $a['match']['match_percentage'];
        });

        return $results;
    }

    /**
     * Recomienda trabajos para un candidato específico
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobs Lista de ofertas de trabajo
     * @param int $limit Número máximo de recomendaciones
     * @param int $threshold Umbral mínimo de coincidencia (0-100)
     * @return array Trabajos recomendados con puntuación
     */
    public function recommendJobs($candidateData, $jobs, $limit = 5, $threshold = 50)
    {
        $recommendations = [];

        foreach ($jobs as $job) {
            $matchResult = $this->evaluateMatch($candidateData, $job);

            // Solo incluir trabajos que superen el umbral
            if ($matchResult['match_percentage'] >= $threshold) {
                $recommendations[] = [
                  'job' => $job,
                  'match' => $matchResult
                ];
            }

            // Limitar el número de evaluaciones para el MVP
            if (count($recommendations) >= $limit * 2) {
                break;
            }
        }

        // Ordenar por coincidencia (de mayor a menor)
        usort($recommendations, function ($a, $b) {
            return $b['match']['match_percentage'] - $a['match']['match_percentage'];
        });

        // Limitar el número de recomendaciones
        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Genera una explicación detallada de por qué un candidato coincide con un trabajo
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos de la oferta de trabajo
     * @return string Explicación detallada
     */
    public function explainMatching($candidateData, $jobData)
    {
        // Crear un prompt para generar la explicación
        $prompt = 'Explica detalladamente por qué este candidato coincide o no con esta oferta de trabajo. ' .
          'Analiza punto por punto las habilidades, experiencia, educación y otros requisitos. ' .
          "Proporciona una explicación clara y completa.\n\n" .
          "Información del candidato:\n" . json_encode($candidateData, JSON_PRETTY_PRINT) . "\n\n" .
          "Información del trabajo:\n" . json_encode($jobData, JSON_PRETTY_PRINT);

        // Evaluación basada en datos recibidos (implementación del proveedor IA)
        $matchResult = $this->evaluateMatch($candidateData, $jobData);
        $percentage = $matchResult['match_percentage'];

        // Generar una explicación basada en el porcentaje
        if ($percentage >= 80) {
            $skills = implode(', ', array_slice($candidateData['skills'] ?? [T::t('habilidades_relevantes')], 0, 3));
            $position = $candidateData['experience'][0]['position'] ?? T::t('el_sector');
            return T::t('coincidencia_excelente', [$skills, $position]);
        } elseif ($percentage >= 60) {
            return T::t('coincidencia_buena');
        } else {
            $missingSkills = implode(', ', array_slice($jobData['requirements']['skills'] ?? [T::t('habilidades_especificas')], 0, 2));
            return T::t('coincidencia_baja', [$missingSkills]);
        }
    }
}
