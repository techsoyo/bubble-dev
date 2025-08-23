<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo CandidateEducation - Gestión de educación de candidatos
 *
 * Gestiona información educativa de candidatos con validaciones de fechas,
 * cache de historiales educativos y métodos especializados para consultas
 * educativas complejas.
 *
 * @package Models
 * @version 2.0.0
 */
class CandidateEducation extends BaseModel
{
    protected string $table = 'candidate_education';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: CandidateEducation
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['is_current', 'description', 'education_level']
     * 📊 Total campos fillable: 7
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'candidate_id',
        'degree',
        'field_of_study',
        'institution',
        'start_date',
        'end_date',
        'gpa',
    ];

    protected array $hidden = [
        'gpa' // Información académica sensible
    ];

    /**
     * Cache para historiales educativos
     */
    private array $educationHistoryCache = [];

    /**
     * MÉTODO EXISTENTE MANTENIDO - Buscar educación por candidato
     */
    public function findByCandidate(int $candidateId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE candidate_id = ? ORDER BY start_date DESC, end_date DESC";
        return $this->query($sql, [$candidateId]);
    }

    /**
     * Obtener historial educativo completo de un candidato con cache
     *
     * @param string|int $candidateId ID del candidato
     * @param bool $useCache Usar cache (default: true)
     * @param int $cacheTtl TTL del cache en segundos (default: 300)
     * @return array Historial educativo ordenado por fechas
     *
     * @throws \InvalidArgumentException Si el ID del candidato está vacío
     * @throws \RuntimeException Si falla la consulta
     */
    public function getCandidateEducationHistory($candidateId, bool $useCache = true, int $cacheTtl = 300): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $cacheKey = "education_history_{$candidateId}";

        // Intentar obtener desde cache interno primero
        if ($useCache && isset($this->educationHistoryCache[$cacheKey])) {
            return $this->educationHistoryCache[$cacheKey];
        }

        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE candidate_id = :candidate_id 
                    ORDER BY 
                        CASE WHEN is_current = 1 THEN 0 ELSE 1 END,
                        COALESCE(end_date, start_date) DESC,
                        start_date DESC";
            
            $params = [':candidate_id' => $candidateId];
            $results = $this->query($sql, $params);

            // Procesar datos para mejorar la información
            $processedResults = array_map(function($education) {
                // Calcular duración si hay fechas
                if ($education['start_date'] && $education['end_date']) {
                    $start = new \DateTime($education['start_date']);
                    $end = new \DateTime($education['end_date']);
                    $education['duration_years'] = $start->diff($end)->y;
                }

                // Marcar educación actual si no tiene fecha de fin
                if (empty($education['end_date']) && !empty($education['start_date'])) {
                    $education['is_current'] = true;
                }

                return $education;
            }, $results);

            // Guardar en cache interno
            if ($useCache) {
                $this->educationHistoryCache[$cacheKey] = $processedResults;
            }

            // Intentar cache externo si está disponible
            if ($useCache && $cacheTtl > 0 && class_exists('\Utils\Cache')) {
                try {
                    \Utils\Cache::set($cacheKey, $processedResults, $cacheTtl);
                } catch (\Exception $e) {
                    Logger::warning('Failed to set education history cache', [
                        'candidate_id' => $candidateId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Logger::debug('Education history retrieved successfully', [
                'candidate_id' => $candidateId,
                'count' => count($processedResults)
            ]);

            return $processedResults;

        } catch (\Exception $e) {
            Logger::error('Error getting candidate education history', [
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get education history: ' . $e->getMessage());
        }
    }

    /**
     * Obtener educación actual del candidato
     *
     * @param string|int $candidateId ID del candidato
     * @return array|null Educación actual o null si no tiene
     */
    public function getCurrentEducation($candidateId): ?array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE candidate_id = :candidate_id 
                    AND (is_current = 1 OR end_date IS NULL OR end_date > CURDATE())
                    ORDER BY start_date DESC
                    LIMIT 1";
            
            $params = [':candidate_id' => $candidateId];
            $results = $this->query($sql, $params);

            return $results[0] ?? null;

        } catch (\Exception $e) {
            Logger::error('Error getting current education', [
                'candidate_id' => $candidateId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get current education: ' . $e->getMessage());
        }
    }

    /**
     * Obtener educación por nivel específico
     *
     * @param string|int $candidateId ID del candidato
     * @param string $level Nivel educativo (bachelor, master, phd, etc.)
     * @return array Educación del nivel especificado
     */
    public function getEducationByLevel($candidateId, string $level): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        if (empty($level)) {
            throw new \InvalidArgumentException('Education level cannot be empty');
        }

        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE candidate_id = :candidate_id 
                    AND (education_level = :level OR degree LIKE :degree_pattern)
                    ORDER BY start_date DESC";
            
            $params = [
                ':candidate_id' => $candidateId,
                ':level' => $level,
                ':degree_pattern' => "%{$level}%"
            ];

            $results = $this->query($sql, $params);

            Logger::debug('Education by level retrieved', [
                'candidate_id' => $candidateId,
                'level' => $level,
                'count' => count($results)
            ]);

            return $results;

        } catch (\Exception $e) {
            Logger::error('Error getting education by level', [
                'candidate_id' => $candidateId,
                'level' => $level,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get education by level: ' . $e->getMessage());
        }
    }

    /**
     * Validar rango de fechas de educación
     *
     * @param string|null $startDate Fecha de inicio
     * @param string|null $endDate Fecha de fin
     * @param bool $isCurrent Es educación actual
     * @return array Array con 'valid' (bool) y 'errors' (array)
     */
    public function validateDateRange(?string $startDate, ?string $endDate, bool $isCurrent = false): array
    {
        $errors = [];
        $valid = true;

        try {
            // Validar formato de fechas
            if ($startDate && !$this->isValidDate($startDate)) {
                $errors[] = 'Invalid start date format';
                $valid = false;
            }

            if ($endDate && !$this->isValidDate($endDate)) {
                $errors[] = 'Invalid end date format';
                $valid = false;
            }

            // Si ambas fechas son válidas, validar lógica
            if ($startDate && $endDate && $this->isValidDate($startDate) && $this->isValidDate($endDate)) {
                $start = new \DateTime($startDate);
                $end = new \DateTime($endDate);

                // La fecha de fin debe ser posterior a la de inicio
                if ($end <= $start) {
                    $errors[] = 'End date must be after start date';
                    $valid = false;
                }

                // Validar que no sean fechas futuras irreales
                $today = new \DateTime();
                if ($end > $today->add(new \DateInterval('P10Y'))) {
                    $errors[] = 'End date cannot be more than 10 years in the future';
                    $valid = false;
                }
            }

            // Si es educación actual, no debe tener fecha de fin
            if ($isCurrent && $endDate) {
                $errors[] = 'Current education cannot have an end date';
                $valid = false;
            }

            // Si no es actual, debería tener fecha de fin
            if (!$isCurrent && !$endDate && $startDate) {
                // Solo advertencia, no error crítico
                Logger::info('Education without end date but not marked as current', [
                    'start_date' => $startDate
                ]);
            }

        } catch (\Exception $e) {
            $errors[] = 'Error validating dates: ' . $e->getMessage();
            $valid = false;
            Logger::error('Date validation error', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }

    /**
     * Validar formato de fecha
     */
    private function isValidDate(string $date): bool
    {
        $formats = ['Y-m-d', 'Y-m-d H:i:s'];
        
        foreach ($formats as $format) {
            $d = \DateTime::createFromFormat($format, $date);
            if ($d && $d->format($format) === $date) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Limpiar cache de historial educativo
     *
     * @param string|int|null $candidateId ID específico o null para limpiar todo
     * @return int Número de entradas eliminadas del cache
     */
    public function clearEducationHistoryCache($candidateId = null): int
    {
        $cleared = 0;

        if ($candidateId !== null) {
            $cacheKey = "education_history_{$candidateId}";
            if (isset($this->educationHistoryCache[$cacheKey])) {
                unset($this->educationHistoryCache[$cacheKey]);
                $cleared++;
            }

            // Limpiar cache externo si está disponible
            if (class_exists('\Utils\Cache')) {
                try {
                    \Utils\Cache::delete($cacheKey);
                } catch (\Exception $e) {
                    Logger::warning('Failed to clear external education cache', [
                        'candidate_id' => $candidateId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            // Limpiar todo el cache interno
            $cleared = count($this->educationHistoryCache);
            $this->educationHistoryCache = [];

            // Limpiar cache externo por tags si está disponible
            if (class_exists('\Utils\Cache')) {
                try {
                    \Utils\Cache::deleteByTags(['candidate_education', 'education_history']);
                } catch (\Exception $e) {
                    Logger::warning('Failed to clear external education cache by tags', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        Logger::debug('Education cache cleared', [
            'candidate_id' => $candidateId,
            'cleared_count' => $cleared
        ]);

        return $cleared;
    }

    /**
     * Override del método store para validación automática de fechas
     */
    public function store(array $data)
    {
        // Validar fechas antes de almacenar
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $validation = $this->validateDateRange(
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['is_current'] ?? false
            );

            if (!$validation['valid']) {
                throw new \InvalidArgumentException('Date validation failed: ' . implode(', ', $validation['errors']));
            }
        }

        // Limpiar cache si existe candidate_id
        if (isset($data['candidate_id'])) {
            $this->clearEducationHistoryCache($data['candidate_id']);
        }

        return parent::store($data);
    }

    /**
     * Override del método update para validación automática de fechas
     */
    public function update($id, array $data): bool
    {
        // Validar fechas antes de actualizar
        if (isset($data['start_date']) || isset($data['end_date'])) {
            // Obtener datos actuales para validación completa
            $current = $this->findById($id);
            if ($current) {
                $startDate = $data['start_date'] ?? $current['start_date'] ?? null;
                $endDate = $data['end_date'] ?? $current['end_date'] ?? null;
                $isCurrent = $data['is_current'] ?? $current['is_current'] ?? false;

                $validation = $this->validateDateRange($startDate, $endDate, $isCurrent);

                if (!$validation['valid']) {
                    throw new \InvalidArgumentException('Date validation failed: ' . implode(', ', $validation['errors']));
                }

                // Limpiar cache
                if (isset($current['candidate_id'])) {
                    $this->clearEducationHistoryCache($current['candidate_id']);
                }
            }
        }

        return parent::update($id, $data);
    }

    /**
     * Override del método delete para limpiar cache
     */
    public function delete($id): bool
    {
        // Obtener candidate_id antes de eliminar para limpiar cache
        $record = $this->findById($id);
        
        $result = parent::delete($id);

        if ($result && $record && isset($record['candidate_id'])) {
            $this->clearEducationHistoryCache($record['candidate_id']);
        }

        return $result;
    }

    /**
     * Obtener estadísticas educativas por institución
     */
    public function getEducationStatsByInstitution(): array
    {
        try {
            $sql = "SELECT 
                        institution,
                        COUNT(*) as total_records,
                        COUNT(DISTINCT candidate_id) as unique_candidates,
                        AVG(CASE WHEN gpa IS NOT NULL THEN gpa END) as avg_gpa
                    FROM {$this->table} 
                    WHERE institution IS NOT NULL AND institution != ''
                    GROUP BY institution 
                    ORDER BY total_records DESC
                    LIMIT 50";

            return $this->query($sql, []);

        } catch (\Exception $e) {
            Logger::error('Error getting education stats by institution', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtener candidatos con educación en progreso
     */
    public function getCandidatesWithCurrentEducation(): array
    {
        try {
            $sql = "SELECT DISTINCT candidate_id, institution, degree, field_of_study
                    FROM {$this->table} 
                    WHERE is_current = 1 OR (end_date IS NULL AND start_date IS NOT NULL)
                    ORDER BY start_date DESC";

            return $this->query($sql, []);

        } catch (\Exception $e) {
            Logger::error('Error getting candidates with current education', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo candidate_education con validaciones
     * @param array $data Datos del nuevo candidate_education
     * @return mixed ID del nuevo candidate_education o false en caso de error
     */
    public function createCandidateEducation(array $data): mixed
    {
        try {
            $this->validateCandidateEducationData($data);
            $id = $this->store($data);
            $this->invalidateCandidateEducationCache();

            Logger::info('CandidateEducation created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating candidate_education', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener candidate_education por ID
     * @param mixed $id ID del candidate_education
     * @return array|null Datos del candidate_education o null si no existe
     */
    public function getCandidateEducation($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving candidate_education', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar candidate_education con validaciones
     * @param mixed $id ID del candidate_education a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateCandidateEducation($id, array $data): bool
    {
        try {
            $this->validateCandidateEducationData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateCandidateEducationCache();
                Logger::info('CandidateEducation updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating candidate_education', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar candidate_education con validaciones
     * @param mixed $id ID del candidate_education a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteCandidateEducation($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateCandidateEducationCache();
                Logger::info('CandidateEducation deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting candidate_education', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar candidate_educations con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de candidate_educations
     */
    public function searchCandidateEducations(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching candidate_educations', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de candidate_educations con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de candidate_educations
     */
    public function countCandidateEducations(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting candidate_educations', [
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
     * Validar datos específicos de candidate_educations
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    private function validateCandidateEducationData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones específicas del modelo
    }

    /**
     * Invalidar cache específico de candidate_educations
     */
    public function invalidateCandidateEducationCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['candidate_educations', 'candidate_education_core', 'candidate_education_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating candidate_education cache', [], $e);
            return 0;
        }
    }
}