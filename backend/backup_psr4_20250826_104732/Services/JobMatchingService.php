<?php declare(strict_types=1);

namespace Services\JobMatchingService.php\Services;

use Utils\TranslationService as T;

/**
 * Servicio para la comparaciÃ³n y matching entre candidatos y ofertas de trabajo
 */
class JobMatchingService
{
    /**
    //  * Servicio de Ollama
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
     * EvalÃºa la coincidencia entre un candidato y una oferta de trabajo
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos de la oferta de trabajo
     * @return array Resultado de la evaluaciÃ³n
     */
    public function evaluateMatch($candidateData, $jobData)
    {
        // TODO: Implementar lÃ³gica de matching con IA
        // return $this->ollamaService->calculateMatching(
        //     $candidateData['id'] ?? 1,
        //     $jobData['id'] ?? 1
        // );

        // ImplementaciÃ³n temporal para el MVP
        return [
            'match_percentage' => 75, // Valor por defecto para pruebas
            'confidence' => 0.8,
            'strengths' => ['Experiencia relevante', 'Habilidades tÃ©cnicas'],
            'weaknesses' => ['Falta experiencia especÃ­fica'],
            'explanation' => 'Coincidencia basada en perfil general'
        ];
    }

    /**
     * Filtra candidatos segÃºn su coincidencia con una oferta de trabajo
     *
     * @param array $candidates Lista de candidatos
     * @param array $jobData Datos de la oferta de trabajo
     * @param int $threshold Umbral mÃ­nimo de coincidencia (0-100)
     * @return array Candidatos filtrados con puntuaciÃ³n
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
     * Recomienda trabajos para un candidato especÃ­fico
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobs Lista de ofertas de trabajo
     * @param int $limit NÃºmero mÃ¡ximo de recomendaciones
     * @param int $threshold Umbral mÃ­nimo de coincidencia (0-100)
     * @return array Trabajos recomendados con puntuaciÃ³n
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

            // Limitar el nÃºmero de evaluaciones para el MVP
            if (count($recommendations) >= $limit * 2) {
                break;
            }
        }

        // Ordenar por coincidencia (de mayor a menor)
        usort($recommendations, function ($a, $b) {
            return $b['match']['match_percentage'] - $a['match']['match_percentage'];
        });

        // Limitar el nÃºmero de recomendaciones
        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Genera una explicaciÃ³n detallada de por quÃ© un candidato coincide con un trabajo
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos de la oferta de trabajo
     * @return string ExplicaciÃ³n detallada
     */
    public function explainMatching($candidateData, $jobData)
    {
        // Crear un prompt para generar la explicaciÃ³n
        $prompt = 'Explica detalladamente por quÃ© este candidato coincide o no con esta oferta de trabajo. ' .
            'Analiza punto por punto las habilidades, experiencia, educaciÃ³n y otros requisitos. ' .
            "Proporciona una explicaciÃ³n clara y completa.\n\n" .
            "InformaciÃ³n del candidato:\n" . json_encode($candidateData, JSON_PRETTY_PRINT) . "\n\n" .
            "InformaciÃ³n del trabajo:\n" . json_encode($jobData, JSON_PRETTY_PRINT);

        // EvaluaciÃ³n basada en datos recibidos (implementaciÃ³n del proveedor IA)
        $matchResult = $this->evaluateMatch($candidateData, $jobData);
        $percentage = $matchResult['match_percentage'];

        // Generar una explicaciÃ³n basada en el porcentaje
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
