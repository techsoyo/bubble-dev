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
 * @since 2025-08-23
 */
class CandidateExperience extends BaseModel
{
    /**
     * Nombre de la tabla asociada al modelo
     *
     * @var string
     */
    protected string $table = 'candidate_experience';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: CandidateExperience
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['candidate_id', 'company', 'position', 'start_date', 'end_date', 'is_current', 'description', 'achievements']
     * 📊 Total campos fillable: 0
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    

    /**
     * Campos que pueden ser asignados masivamente
     *
     * @var array<string>
     */
    protected array $fillable = [];

    /**
     * Campos que deben ocultarse en arrays/JSON
     *
     * @var array<string>
     */
    protected array $hidden = [
        'salary_info'
    ];

    /**
     * Conversiones de tipos para los atributos
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'achievements' => 'array'
    ];

    /**
     * MÉTODOS PRINCIPALES DEL MODELO
     */

    /**
     * Obtiene el historial completo de experiencias de un candidato
     * ordenado por fecha de inicio (más reciente primero)
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de experiencias ordenadas por fecha
     *
     * @throws \InvalidArgumentException Si el candidate_id es inválido
     * @throws \RuntimeException Si ocurre un error de base de datos
     */
    public function getCandidateExperienceHistory(string $candidateId): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        try {
            return $this->findAll(
                ['candidate_id' => $candidateId],
                1,
                self::MAX_LIMIT,
                ['start_date' => 'DESC']
            );
        } catch (\Exception $e) {
            Logger::error('Error getting candidate experience history', [
                'model' => static::class,
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get candidate experience history: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene la posición actual del candidato
     * (experiencia marcada como is_current = true)
     *
     * @param string $candidateId ID del candidato
     * @return array|null Experiencia actual o null si no tiene
     *
     * @throws \InvalidArgumentException Si el candidate_id es inválido
     * @throws \RuntimeException Si ocurre un error de base de datos
     */
    public function getCurrentPosition(string $candidateId): ?array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        try {
            return $this->findOneBy('candidate_id', $candidateId, ['is_current' => true]);
        } catch (\Exception $e) {
            Logger::error('Error getting current position', [
                'model' => static::class,
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get current position: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene todas las experiencias de un candidato en una empresa específica
     *
     * @param string $candidateId ID del candidato
     * @param string $company Nombre de la empresa
     * @return array Lista de experiencias en la empresa
     *
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si ocurre un error de base de datos
     */
    public function getExperienceByCompany(string $candidateId, string $company): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        if (empty($company)) {
            throw new \InvalidArgumentException('Company name cannot be empty');
        }

        try {
            return $this->findAll(
                [
                    'candidate_id' => $candidateId,
                    'company' => $company
                ],
                1,
                self::MAX_LIMIT,
                ['start_date' => 'DESC']
            );
        } catch (\Exception $e) {
            Logger::error('Error getting experience by company', [
                'model' => static::class,
                'candidate_id' => $candidateId,
                'company' => $company,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get experience by company: ' . $e->getMessage());
        }
    }

    /**
     * Calcula la experiencia total del candidato en años
     * considerando posiciones concurrentes y gaps
     *
     * @param string $candidateId ID del candidato
     * @return array Información detallada de experiencia total
     *
     * @throws \InvalidArgumentException Si el candidate_id es inválido
     * @throws \RuntimeException Si ocurre un error de base de datos
     */
    public function calculateTotalExperience(string $candidateId): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        try {
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
                $endDate = $exp['is_current'] ? new \DateTime() : new \DateTime($exp['end_date']);

                $interval = $startDate->diff($endDate);
                $days = $interval->days;

                $totalDays += $days;
                
                if ($days > $longestPositionDays) {
                    $longestPositionDays = $days;
                }

                if (!empty($exp['company'])) {
                    $companies[$exp['company']] = true;
                }

                if ($exp['is_current']) {
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
        } catch (\Exception $e) {
            Logger::error('Error calculating total experience', [
                'model' => static::class,
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to calculate total experience: ' . $e->getMessage());
        }
    }

    /**
     * MÉTODOS DE VALIDACIÓN Y ANÁLISIS
     */

    /**
     * Valida que las fechas de experiencia sean consistentes
     *
     * @param array $data Datos de la experiencia a validar
     * @return array Errores de validación encontrados
     */
    public function validateExperienceDates(array $data): array
    {
        $errors = [];

        // Validar que start_date esté presente
        if (empty($data['start_date'])) {
            $errors[] = 'Start date is required';
        } else {
            try {
                $startDate = new \DateTime($data['start_date']);
                $now = new \DateTime();

                // No puede ser fecha futura
                if ($startDate > $now) {
                    $errors[] = 'Start date cannot be in the future';
                }
            } catch (\Exception $e) {
                $errors[] = 'Invalid start date format';
            }
        }

        // Si no es posición actual, validar end_date
        if (!($data['is_current'] ?? false)) {
            if (empty($data['end_date'])) {
                $errors[] = 'End date is required for non-current positions';
            } else {
                try {
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
                } catch (\Exception $e) {
                    $errors[] = 'Invalid end date format';
                }
            }
        }

        return $errors;
    }

    /**
     * Valida que un candidato no tenga múltiples posiciones actuales
     *
     * @param string $candidateId ID del candidato
     * @param string|null $excludeId ID de experiencia a excluir de la validación
     * @return bool True si la validación pasa
     */
    public function validateSingleCurrentPosition(string $candidateId, ?string $excludeId = null): bool
    {
        try {
            $filters = [
                'candidate_id' => $candidateId,
                'is_current' => true
            ];

            $currentPositions = $this->findAll($filters);

            // Si se excluye un ID, filtrarlo
            if ($excludeId) {
                $currentPositions = array_filter($currentPositions, function ($pos) use ($excludeId) {
                    return $pos['id'] != $excludeId;
                });
            }

            return count($currentPositions) <= 1;
        } catch (\Exception $e) {
            Logger::error('Error validating single current position', [
                'model' => static::class,
                'candidate_id' => $candidateId,
                'exclude_id' => $excludeId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * MÉTODOS DE ANÁLISIS AVANZADO
     */

    /**
     * Analiza la estabilidad laboral del candidato
     *
     * @param string $candidateId ID del candidato
     * @return array Métricas de estabilidad laboral
     */
    public function analyzeJobStability(string $candidateId): array
    {
        try {
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
                $endDate = $exp['is_current'] ? new \DateTime() : new \DateTime($exp['end_date']);
                
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
                $lastEnd = $experiences[0]['is_current'] ? new \DateTime() : new \DateTime($experiences[0]['end_date']);
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
        } catch (\Exception $e) {
            Logger::error('Error analyzing job stability', [
                'model' => static::class,
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to analyze job stability: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene recomendación basada en el análisis de estabilidad
     *
     * @param float $stabilityScore Score de estabilidad
     * @param float $changesPerYear Cambios de trabajo por año
     * @return string Recomendación
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
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de experiencias
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->getCandidateExperienceHistory($candidateId);
    }

    /**
     * Crear nueva experiencia con validaciones
     *
     * @param array $data Datos de la nueva experiencia
     * @return mixed ID de la experiencia creada
     *
     * @throws \InvalidArgumentException Si los datos no son válidos
     * @throws \RuntimeException Si ocurre un error de base de datos
     */
    public function store(array $data)
    {
        // Validar fechas
        $dateErrors = $this->validateExperienceDates($data);
        if (!empty($dateErrors)) {
            throw new \InvalidArgumentException('Date validation errors: ' . implode(', ', $dateErrors));
        }

        // Validar posición única actual
        if ($data['is_current'] ?? false) {
            if (!$this->validateSingleCurrentPosition($data['candidate_id'])) {
                throw new \InvalidArgumentException('Candidate already has a current position');
            }
        }

        return parent::store($data);
    }

    /**
     * Actualizar experiencia con validaciones
     *
     * @param mixed $id ID de la experiencia
     * @param array $data Datos a actualizar
     * @return bool True si la actualización fue exitosa
     *
     * @throws \InvalidArgumentException Si los datos no son válidos
     * @throws \RuntimeException Si ocurre un error de base de datos
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
        if (($data['is_current'] ?? false) && !($current['is_current'] ?? false)) {
            if (!$this->validateSingleCurrentPosition($current['candidate_id'], $id)) {
                throw new \InvalidArgumentException('Candidate already has a current position');
            }
        }

        return parent::update($id, $data);
    }

    /**
     * Obtiene estadísticas rápidas de experiencia para un candidato
     *
     * @param string $candidateId ID del candidato
     * @return array Estadísticas resumidas
     */
    public function getExperienceStats(string $candidateId): array
    {
        try {
            $totalExp = $this->calculateTotalExperience($candidateId);
            $stability = $this->analyzeJobStability($candidateId);
            
            return [
                'total_experience_years' => $totalExp['total_years'],
                'positions_count' => $totalExp['experiences_count'],
                'companies_count' => $totalExp['companies_count'],
                'has_current_position' => $totalExp['current_position'],
                'stability_score' => $stability['stability_score'],
                'average_tenure_months' => $stability['average_tenure_months'],
                'last_updated' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting experience stats', [
                'model' => static::class,
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            return [
                'error' => 'Unable to calculate experience stats',
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }
    }
    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo candidate_experience con validaciones
     * @param array $data Datos del nuevo candidate_experience
     * @return mixed ID del nuevo candidate_experience o false en caso de error
     */
    public function createCandidateExperience(array $data): mixed
    {
        try {
            $this->validateCandidateExperienceData($data);
            $id = $this->store($data);
            $this->invalidateCandidateExperienceCache();

            Logger::info('CandidateExperience created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating candidate_experience', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener candidate_experience por ID
     * @param mixed $id ID del candidate_experience
     * @return array|null Datos del candidate_experience o null si no existe
     */
    public function getCandidateExperience($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving candidate_experience', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar candidate_experience con validaciones
     * @param mixed $id ID del candidate_experience a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateCandidateExperience($id, array $data): bool
    {
        try {
            $this->validateCandidateExperienceData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateCandidateExperienceCache();
                Logger::info('CandidateExperience updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating candidate_experience', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar candidate_experience con validaciones
     * @param mixed $id ID del candidate_experience a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteCandidateExperience($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateCandidateExperienceCache();
                Logger::info('CandidateExperience deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting candidate_experience', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar candidate_experiences con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de candidate_experiences
     */
    public function searchCandidateExperiences(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching candidate_experiences', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de candidate_experiences con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de candidate_experiences
     */
    public function countCandidateExperiences(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting candidate_experiences', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ==========================================
    // MÉTODOS DE VALIDACIÓN ESPECÍFICOS
    // ==========================================

    /**
     * Validar datos específicos de candidate_experiences
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    private function validateCandidateExperienceData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones específicas del modelo
    }

    /**
     * Invalidar cache específico de candidate_experiences
     */
    public function invalidateCandidateExperienceCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['candidate_experiences', 'candidate_experience_core', 'candidate_experience_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating candidate_experience cache', [], $e);
            return 0;
        }
    }
}