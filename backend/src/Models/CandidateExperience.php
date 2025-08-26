<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para las experiencias profesionales de los candidatos
 * 
 * Este modelo maneja toda la información relacionada con la experiencia laboral
 * de los candidatos, incluyendo posiciones actuales, historial profesional,
 * validaciones de fechas y análisis de experiencia.
 * 
 * @package Models
 * @version 2.0.0
 * @since 2025-08-25
 */
class CandidateExperience extends BaseModel
{
    /**
     * Nombre de la tabla asociada al modelo
     *
     * @var string
     */
    protected string $table = 'candidate_experiences';

    /**
     * Campos que pueden ser asignados masivamente
     * Basados en la estructura real de la tabla bt_candidate_experiences
     *
     * @var array<string>
     */
    protected array $fillable = [
        'candidate_id',
        'company',
        'position',
        'start_date',
        'end_date',
        'current',
        'description',
        'location'
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * MÉTODOS PRINCIPALES DEL MODELO
     */

    /**
     * Obtiene el historial completo de experiencias de un candidato
     * ordenado por fecha de inicio (más reciente primero)
     */
    public function getCandidateExperienceHistory(string $candidateId): array
    {
        return $this->findAll(
            ['candidate_id' => $candidateId],
            1,
            self::MAX_LIMIT,
            ['start_date' => 'DESC']
        );
    }

    /**
     * Obtiene la posición actual del candidato
     * (experiencia marcada como current = true)
     */
    public function getCurrentPosition(string $candidateId): ?array
    {
        return $this->findOneBy('candidate_id', $candidateId, ['current' => 1]);
    }

    /**
     * Obtiene todas las experiencias de un candidato en una empresa específica
     */
    public function getExperienceByCompany(string $candidateId, string $company): array
    {
        return $this->findAll(
            [
                'candidate_id' => $candidateId,
                'company' => $company
            ],
            1,
            self::MAX_LIMIT,
            ['start_date' => 'DESC']
        );
    }

    /**
     * Calcula la experiencia total del candidato en años
     * considerando posiciones concurrentes y gaps
     */
    public function calculateTotalExperience(string $candidateId): array
    {
        $experiences = $this->getCandidateExperienceHistory($candidateId);

        if (empty($experiences)) {
            return [
                'total_years' => 0,
                'total_months' => 0,
                'total_days' => 0,
                'experiences_count' => 0,
                'current_position' => false,
                'longest_position_months' => 0,
                'companies_count' => 0,
                'calculation_date' => date('Y-m-d H:i:s')
            ];
        }

        $totalDays = 0;
        $longestPositionDays = 0;
        $companies = [];
        $hasCurrentPosition = false;

        foreach ($experiences as $exp) {
            $startDate = new \DateTime($exp['start_date']);
            $endDate = $exp['current'] ? new \DateTime() : new \DateTime($exp['end_date']);

            $interval = $startDate->diff($endDate);
            $days = $interval->days;

            $totalDays += $days;

            if ($days > $longestPositionDays) {
                $longestPositionDays = $days;
            }

            if (!empty($exp['company'])) {
                $companies[$exp['company']] = true;
            }

            if ($exp['current']) {
                $hasCurrentPosition = true;
            }
        }

        // Convertir días a años, meses y días
        $totalYears = floor($totalDays / 365);
        $remainingDays = $totalDays % 365;
        $totalMonths = floor($remainingDays / 30);
        $finalDays = $remainingDays % 30;

        return [
            'total_years' => $totalYears,
            'total_months' => $totalMonths,
            'total_days' => $finalDays,
            'total_experience_days' => $totalDays,
            'experiences_count' => count($experiences),
            'current_position' => $hasCurrentPosition,
            'longest_position_months' => round($longestPositionDays / 30, 1),
            'companies_count' => count($companies),
            'companies' => array_keys($companies),
            'calculation_date' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * MÉTODOS DE VALIDACIÓN Y ANÁLISIS
     */

    /**
     * Valida que las fechas de experiencia sean consistentes
     */
    public function validateExperienceDates(array $data): array
    {
        $errors = [];

        // Validar que start_date esté presente
        if (empty($data['start_date'])) {
            $errors[] = 'Start date is required';
        } else {
            $startDate = new \DateTime($data['start_date']);
            $now = new \DateTime();

            // No puede ser fecha futura
            if ($startDate > $now) {
                $errors[] = 'Start date cannot be in the future';
            }
        }

        // Si no es posición actual, validar end_date
        if (!($data['current'] ?? false)) {
            if (empty($data['end_date'])) {
                $errors[] = 'End date is required for non-current positions';
            } else {
                $endDate = new \DateTime($data['end_date']);
                $startDate = new \DateTime($data['start_date']);
                $now = new \DateTime();

                // End date no puede ser anterior a start date
                if ($endDate < $startDate) {
                    $errors[] = 'End date cannot be before start date';
                }

                // End date no puede ser futura (con margen de error)
                if ($endDate > $now->add(new \DateInterval('P1M'))) {
                    $errors[] = 'End date cannot be more than 1 month in the future';
                }
            }
        }

        return $errors;
    }

    /**
     * Valida que un candidato no tenga múltiples posiciones actuales
     */
    public function validateSingleCurrentPosition(string $candidateId, ?string $excludeId = null): bool
    {
        $filters = [
            'candidate_id' => $candidateId,
            'current' => 1
        ];

        $currentPositions = $this->findAll($filters);

        // Si se excluye un ID, filtrarlo
        if ($excludeId) {
            $currentPositions = array_filter($currentPositions, function ($pos) use ($excludeId) {
                return $pos['id'] != $excludeId;
            });
        }

        return count($currentPositions) <= 1;
    }

    /**
     * MÉTODOS DE ANÁLISIS AVANZADO
     */

    /**
     * Analiza la estabilidad laboral del candidato
     */
    public function analyzeJobStability(string $candidateId): array
    {
        $experiences = $this->getCandidateExperienceHistory($candidateId);

        if (count($experiences) < 2) {
            return [
                'stability_score' => 10, // Máxima estabilidad si tiene 1 o menos trabajos
                'average_tenure_months' => 0,
                'job_changes_per_year' => 0,
                'gap_analysis' => [],
                'recommendation' => 'Insufficient data for analysis'
            ];
        }

        $tenures = [];
        $gaps = [];

        for ($i = 0; $i < count($experiences); $i++) {
            $exp = $experiences[$i];
            $startDate = new \DateTime($exp['start_date']);
            $endDate = $exp['current'] ? new \DateTime() : new \DateTime($exp['end_date']);

            $tenure = $startDate->diff($endDate)->days;
            $tenures[] = $tenure;

            // Detectar gaps entre trabajos
            if ($i < count($experiences) - 1) {
                $nextExp = $experiences[$i + 1];
                $nextEndDate = new \DateTime($nextExp['end_date']);

                if ($startDate > $nextEndDate) {
                    $gap = $nextEndDate->diff($startDate)->days;
                    if ($gap > 30) { // Gap mayor a 30 días
                        $gaps[] = [
                            'days' => $gap,
                            'months' => round($gap / 30, 1),
                            'after_company' => $nextExp['company'],
                            'before_company' => $exp['company']
                        ];
                    }
                }
            }
        }

        $averageTenure = array_sum($tenures) / count($tenures);
        $averageTenureMonths = round($averageTenure / 30, 1);

        // Calcular score de estabilidad (1-10)
        $stabilityScore = min(10, max(1, $averageTenureMonths / 6));

        // Calcular cambios por año
        $totalTimeRange = 0;
        if (!empty($experiences)) {
            $firstStart = new \DateTime($experiences[count($experiences) - 1]['start_date']);
            $lastEnd = $experiences[0]['current'] ? new \DateTime() : new \DateTime($experiences[0]['end_date']);
            $totalTimeRange = $firstStart->diff($lastEnd)->days / 365;
        }

        $changesPerYear = $totalTimeRange > 0 ? (count($experiences) - 1) / $totalTimeRange : 0;

        return [
            'stability_score' => round($stabilityScore, 1),
            'average_tenure_months' => $averageTenureMonths,
            'job_changes_per_year' => round($changesPerYear, 2),
            'total_gaps' => count($gaps),
            'gap_analysis' => $gaps,
            'longest_tenure_months' => round(max($tenures) / 30, 1),
            'shortest_tenure_months' => round(min($tenures) / 30, 1),
            'recommendation' => $this->getStabilityRecommendation($stabilityScore, $changesPerYear)
        ];
    }

    /**
     * Obtiene recomendación basada en el análisis de estabilidad
     */
    private function getStabilityRecommendation(float $stabilityScore, float $changesPerYear): string
    {
        if ($stabilityScore >= 8 && $changesPerYear < 0.5) {
            return 'Excellent stability - Long-term commitment likely';
        } elseif ($stabilityScore >= 6 && $changesPerYear < 1) {
            return 'Good stability - Reasonable tenure expectations';
        } elseif ($stabilityScore >= 4 && $changesPerYear < 2) {
            return 'Moderate stability - May require retention strategies';
        } else {
            return 'High turnover risk - Investigate reasons for frequent changes';
        }
    }

    /**
     * MÉTODOS HEREDADOS Y ESPECÍFICOS
     */

    /**
     * Devuelve todas las experiencias asociadas a un candidato
     * (Mantiene compatibilidad con versión anterior)
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->getCandidateExperienceHistory($candidateId);
    }

    /**
     * Crear nueva experiencia con validaciones
     */
    public function store(array $data)
    {
        // Validar fechas
        $dateErrors = $this->validateExperienceDates($data);
        if (!empty($dateErrors)) {
            throw new \InvalidArgumentException('Date validation errors: ' . implode(', ', $dateErrors));
        }

        // Validar posición única actual
        if ($data['current'] ?? false) {
            if (!$this->validateSingleCurrentPosition($data['candidate_id'])) {
                throw new \InvalidArgumentException('Candidate already has a current position');
            }
        }

        return parent::store($data);
    }

    /**
     * Actualizar experiencia con validaciones
     */
    public function update($id, array $data): bool
    {
        // Obtener datos actuales para validaciones
        $current = $this->findById($id);
        if (!$current) {
            throw new \InvalidArgumentException('Experience not found');
        }

        // Validar fechas si se proporcionan
        $dataToValidate = array_merge($current, $data);
        $dateErrors = $this->validateExperienceDates($dataToValidate);
        if (!empty($dateErrors)) {
            throw new \InvalidArgumentException('Date validation errors: ' . implode(', ', $dateErrors));
        }

        // Validar posición única actual si se está marcando como actual
        if (($data['current'] ?? false) && !($current['current'] ?? false)) {
            if (!$this->validateSingleCurrentPosition($current['candidate_id'], $id)) {
                throw new \InvalidArgumentException('Candidate already has a current position');
            }
        }

        return parent::update($id, $data);
    }

    /**
     * Obtiene estadísticas rápidas de experiencia para un candidato
     */
    public function getExperienceStats(string $candidateId): array
    {
        $totalExp = $this->calculateTotalExperience($candidateId);
        $stability = $this->analyzeJobStability($candidateId);

        return [
            'total_experience_years' => $totalExp['total_years'],
            'positions_count' => $totalExp['experiences_count'],
            'companies_count' => $totalExp['companies_count'],
            'has_current_position' => $totalExp['current_position'],
            'stability_score' => $stability['stability_score'],
            'job_changes_per_year' => $stability['job_changes_per_year']
        ];
    }
}
