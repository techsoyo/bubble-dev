<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para la entidad Job - Migrado para extender BaseModel
 */
class Job extends BaseModel
{
    /**
     * Nombre de la tabla (BaseModel aplicará prefijo bt_ automáticamente)
     * @var string
     */
    protected string $table = 'jobs';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: Job
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['employment_type', 'currency', 'posted_date', 'closing_date', 'benefits', 'remote_eligible', 'visa_sponsorship', 'created_by']
     * ❌ Campos removidos: ['company_name', 'required_skills', 'preferred_skills', 'salary_range', 'salary_currency', 'salary_period', 'contract_type', 'type', 'level', 'category', 'posted_by', 'posted_at', 'expires_at', 'is_featured']
     * 📊 Total campos fillable: 16
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    

    /**
     * Campos que pueden ser asignados masivamente
     * Basado en análisis de BD tabla bt_jobs
     * @var array
     */
    protected array $fillable = [
        'title',
        'description',
        'department_id',
        'location',
        'employment_type',
        'salary_min',
        'salary_max',
        'currency',
        'status',
        'posted_date',
        'closing_date',
        'requirements',
        'benefits',
        'remote_eligible',
        'visa_sponsorship',
        'created_by',
    ];

    /**
     * Campos ocultos para proteger información sensible
     * @var array
     */
    protected array $hidden = [
        'api_keys',
        'internal_notes', 
        'hiring_manager_notes'
    ];

    /**
     * MÉTODOS DE VISTAS - Funcionalidad específica de trabajos
     */

    /**
     * Obtiene trabajos usando la vista con metadatos (habilidades, requisitos, beneficios)
     */
    public function getJobsWithMeta(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $sql = "SELECT * FROM vw_jobs_with_meta";
        $params = [];

        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        // Añadir ordenación por defecto
        $sql .= ' ORDER BY posted_at DESC';

        // Añadir paginación
        $offset = ($page - 1) * $limit;
        $sql .= ' LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting jobs with meta', [
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get jobs with meta: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene resumen de aplicaciones por trabajo
     */
    public function getJobsApplicationsSummary(array $filters = []): array
    {
        $sql = "SELECT * FROM vw_jobs_applications_summary";
        $params = [];

        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        $sql .= ' ORDER BY applications_total DESC, last_update DESC';

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting jobs applications summary', ['filters' => $filters], $e);
            throw new \RuntimeException('Failed to get jobs applications summary: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene candidatos que coinciden con un trabajo específico
     */
    public function getJobCandidateMatches(string $jobId, int $limit = 20): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100');
        }

        $sql = "SELECT * FROM vw_match_candidates_jobs 
                WHERE job_id = :job_id 
                ORDER BY matched_skills DESC 
                LIMIT :limit";

        try {
            return $this->query($sql, [
                ':job_id' => $jobId,
                ':limit' => $limit
            ]);
        } catch (\Exception $e) {
            $this->logError('Error getting job candidate matches', [
                'job_id' => $jobId,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get job candidate matches: ' . $e->getMessage());
        }
    }

    /**
     * Override del método findAll para usar vistas de BD en lugar de datos dummy
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        // Usar la vista con metadatos para obtener información completa
        $sql = "SELECT * FROM vw_jobs_with_meta";
        $params = [];

        // Construir cláusula WHERE
        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    // Filtro especial para búsqueda en habilidades
                    if ($field === 'skills') {
                        $whereConditions[] = "skills_text LIKE :filter_skills";
                        $params[":filter_skills"] = '%' . $value . '%';
                    } else {
                        $whereConditions[] = "`$field` = :filter_$field";
                        $params[":filter_$field"] = $value;
                    }
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        // Construir cláusula ORDER BY
        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $field => $direction) {
                if ($this->isValidFieldName($field) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                    $orderClauses[] = "`$field` " . strtoupper($direction);
                }
            }
            if (!empty($orderClauses)) {
                $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
            }
        } else {
            $sql .= ' ORDER BY posted_at DESC';
        }

        // Añadir paginación
        $offset = ($page - 1) * $limit;
        $sql .= ' LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            $results = $this->query($sql, $params);
            
            $this->logDebug('Jobs retrieved successfully', [
                'count' => count($results),
                'page' => $page,
                'limit' => $limit
            ]);

            return $this->hideFields($results);
        } catch (\Exception $e) {
            $this->logError('Error in findAll jobs', [
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to retrieve jobs: ' . $e->getMessage());
        }
    }

    /**
     * Contar trabajos con filtros usando la vista de BD
     */
    public function countAll(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM vw_jobs_with_meta";
        $params = [];

        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    if ($field === 'skills') {
                        $whereConditions[] = "skills_text LIKE :filter_skills";
                        $params[":filter_skills"] = '%' . $value . '%';
                    } else {
                        $whereConditions[] = "`$field` = :filter_$field";
                        $params[":filter_$field"] = $value;
                    }
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        try {
            $result = $this->query($sql, $params);
            return (int)($result[0]['total'] ?? 0);
        } catch (\Exception $e) {
            $this->logError('Error counting jobs', ['filters' => $filters], $e);
            return 0;
        }
    }

    /**
     * MÉTODOS ESPECÍFICOS MANTENIDOS - Con mejoras para usar BD real
     */

    /**
     * Encuentra trabajos por título
     *
     * @param string $title Título a buscar
     * @return array Lista de trabajos
     */
    public function findByTitle($title): array
    {
        if (empty($title)) {
            throw new \InvalidArgumentException('Title cannot be empty');
        }

        try {
            return $this->query(
                "SELECT * FROM vw_jobs_with_meta WHERE title LIKE :title ORDER BY posted_at DESC",
                [':title' => '%' . $title . '%']
            );
        } catch (\Exception $e) {
            $this->logError('Error finding jobs by title', ['title' => $title], $e);
            throw new \RuntimeException('Failed to find jobs by title: ' . $e->getMessage());
        }
    }

    /**
     * Encuentra trabajos por ubicación
     *
     * @param string $location Ubicación a buscar
     * @return array Lista de trabajos
     */
    public function findByLocation($location): array
    {
        if (empty($location)) {
            throw new \InvalidArgumentException('Location cannot be empty');
        }

        try {
            return $this->query(
                "SELECT * FROM vw_jobs_with_meta WHERE location LIKE :location ORDER BY posted_at DESC",
                [':location' => '%' . $location . '%']
            );
        } catch (\Exception $e) {
            $this->logError('Error finding jobs by location', ['location' => $location], $e);
            throw new \RuntimeException('Failed to find jobs by location: ' . $e->getMessage());
        }
    }

    /**
     * Encuentra trabajos por habilidades requeridas
     *
     * @param array $skills Lista de habilidades a buscar
     * @return array Lista de trabajos
     */
    public function findBySkills(array $skills): array
    {
        if (empty($skills)) {
            throw new \InvalidArgumentException('Skills array cannot be empty');
        }

        try {
            // Usar la vista que incluye skills_text para búsqueda más eficiente
            $skillConditions = [];
            $params = [];
            
            foreach ($skills as $index => $skill) {
                $paramKey = ":skill_$index";
                $skillConditions[] = "skills_text LIKE $paramKey";
                $params[$paramKey] = '%' . $skill . '%';
            }

            $sql = "SELECT * FROM vw_jobs_with_meta WHERE " . implode(' OR ', $skillConditions) . " ORDER BY posted_at DESC";

            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error finding jobs by skills', ['skills' => $skills], $e);
            throw new \RuntimeException('Failed to find jobs by skills: ' . $e->getMessage());
        }
    }

    /**
     * Encuentra trabajos activos
     *
     * @return array Lista de trabajos activos
     */
    public function findActive(): array
    {
        try {
            return $this->query(
                "SELECT * FROM vw_jobs_with_meta WHERE status = 'active' ORDER BY posted_at DESC"
            );
        } catch (\Exception $e) {
            $this->logError('Error finding active jobs', [], $e);
            throw new \RuntimeException('Failed to find active jobs: ' . $e->getMessage());
        }
    }

    /**
     * MÉTODOS CON CACHE Y OPTIMIZACIÓN
     */

    /**
     * Obtener trabajos paginados con información de aplicaciones (con cache)
     */
    public function getJobsPaginatedWithCache(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, int $cacheTtl = 300): array
    {
        $cacheKey = $this->generateCacheKey('jobs_paginated', array_merge($filters, ['page' => $page, 'limit' => $limit]));

        try {
            // Intentar obtener desde cache si está habilitado
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($filters, $page, $limit) {
                    return $this->executeJobsPaginatedQuery($filters, $page, $limit);
                });
            }

            // Fallback sin cache
            return $this->executeJobsPaginatedQuery($filters, $page, $limit);
        } catch (\Exception $e) {
            $this->logError('Error getting paginated jobs with cache', [
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], $e);
            // Fallback directo a la query
            return $this->executeJobsPaginatedQuery($filters, $page, $limit);
        }
    }

    /**
     * Método auxiliar para ejecutar la query paginada de trabajos
     */
    private function executeJobsPaginatedQuery(array $filters, int $page, int $limit): array
    {
        // Usar vista con metadatos para los datos
        $data = $this->getJobsWithMeta($filters, $page, $limit);

        // Obtener conteo total para paginación
        $total = $this->countAll($filters);

        $totalPages = (int) ceil($total / $limit);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'total' => $total,
                'per_page' => $limit,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }

    /**
     * Obtener trabajos destacados con cache
     */
    public function getFeaturedJobsWithCache(int $limit = 10, int $cacheTtl = 600): array
    {
        $cacheKey = $this->generateCacheKey('featured_jobs', ['limit' => $limit]);

        try {
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($limit) {
                    return $this->query(
                        "SELECT * FROM vw_jobs_with_meta WHERE is_featured = 1 AND status = 'active' ORDER BY posted_at DESC LIMIT :limit",
                        [':limit' => $limit]
                    );
                });
            }

            // Fallback sin cache
            return $this->query(
                "SELECT * FROM vw_jobs_with_meta WHERE is_featured = 1 AND status = 'active' ORDER BY posted_at DESC LIMIT :limit",
                [':limit' => $limit]
            );
        } catch (\Exception $e) {
            $this->logError('Error getting featured jobs', ['limit' => $limit], $e);
            return [];
        }
    }

    /**
     * MÉTODOS DE UTILIDAD Y CACHE
     */

    /**
     * Invalidar cache específico de trabajos
     */
    public function invalidateJobCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['jobs', 'jobs_paginated', 'featured_jobs', 'jobs_with_meta']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating job cache', [], $e);
            return 0;
        }
    }

    /**
     * Generar clave de cache única
     */
    private function generateCacheKey(string $prefix, array $data = []): string
    {
        $key = $prefix . '_' . md5(serialize($data));
        return substr($key, 0, 250); // Limitar longitud de clave
    }

    /**
     * Método de logging de errores usando la herencia de BaseModel
     */
    private function logError(string $message, array $context = [], ?\Exception $exception = null): void
    {
        Logger::error($message, array_merge($context, [
            'model' => static::class,
            'table' => $this->table,
            'exception' => $exception ? $exception->getMessage() : null
        ]));
    }

    /**
     * Método de logging de debug usando la herencia de BaseModel
     */
    private function logDebug(string $message, array $context = []): void
    {
        Logger::debug($message, array_merge($context, [
            'model' => static::class,
            'table' => $this->table
        ]));
    }
}