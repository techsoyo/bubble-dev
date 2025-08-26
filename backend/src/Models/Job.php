<?php declare(strict_types=1);
namespace Models;

use Utils\Logger;

/**
 * Modelo para la entidad Job - Migrado para extender BaseModel
 */
class Job extends BaseModel
{
    /**
     * Nombre de la tabla (BaseModel aplicarÃƒÆ’Ã‚Â¡ prefijo bt_ automÃƒÆ’Ã‚Â¡ticamente)
     * @var string
     */
    protected string $table = 'jobs';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÃƒÆ’Ã¢â‚¬Å“N AUTOMÃƒÆ’Ã‚ÂTICA APLICADA
     * Modelo: Job
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aÃƒÆ’Ã‚Â±adidos: ['employment_type', 'currency', 'posted_date', 'closing_date', 'benefits', 'remote_eligible', 'visa_sponsorship', 'created_by']
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['company_name', 'required_skills', 'preferred_skills', 'salary_range', 'salary_currency', 'salary_period', 'contract_type', 'type', 'level', 'category', 'posted_by', 'posted_at', 'expires_at', 'is_featured']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 16
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Campos que pueden ser asignados masivamente
     * Basado en anÃƒÆ’Ã‚Â¡lisis de BD tabla bt_jobs
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
     * Campos ocultos para proteger informaciÃƒÆ’Ã‚Â³n sensible
     * @var array
     */
    protected array $hidden = [
        'api_keys',
        'internal_notes',
        'hiring_manager_notes'
    ];

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS DE VISTAS - Funcionalidad especÃƒÆ’Ã‚Â­fica de trabajos
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

        // AÃƒÆ’Ã‚Â±adir ordenaciÃƒÆ’Ã‚Â³n por defecto (posted_date reemplaza a posted_at)
        $sql .= ' ORDER BY posted_date DESC';

        // AÃƒÆ’Ã‚Â±adir paginaciÃƒÆ’Ã‚Â³n
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
     * Obtiene candidatos que coinciden con un trabajo especÃƒÆ’Ã‚Â­fico
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
     * Override del mÃƒÆ’Ã‚Â©todo findAll para usar vistas de BD en lugar de datos dummy
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        // Usar la vista con metadatos para obtener informaciÃƒÆ’Ã‚Â³n completa
        $sql = "SELECT * FROM vw_jobs_with_meta";
        // Inicializar parÃƒÆ’Ã‚Â¡metros para binds (evita "undefined variable" si no hay filtros)
        $params = [];
        // Si no se especifica $orderBy, caer a posted_date (nuevo campo)
        if (empty($orderBy)) {
            $orderBy = ['posted_date' => 'DESC'];
        }

        // Construir clÃƒÆ’Ã‚Â¡usula WHERE
        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    // Filtro especial para bÃƒÆ’Ã‚Âºsqueda en habilidades
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

        // Construir clÃƒÆ’Ã‚Â¡usula ORDER BY
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

        // AÃƒÆ’Ã‚Â±adir paginaciÃƒÆ’Ã‚Â³n
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
     * MÃƒÆ’Ã¢â‚¬Â°TODOS ESPECÃƒÆ’Ã‚ÂFICOS MANTENIDOS - Con mejoras para usar BD real
     */

    /**
     * Encuentra trabajos por tÃƒÆ’Ã‚Â­tulo
     *
     * @param string $title TÃƒÆ’Ã‚Â­tulo a buscar
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
     * Encuentra trabajos por ubicaciÃƒÆ’Ã‚Â³n
     *
     * @param string $location UbicaciÃƒÆ’Ã‚Â³n a buscar
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
     * MÃƒÆ’Ã¢â‚¬Â°TODOS CRUD ENCAPSULADOS - Para uso externo
     * Estos mÃƒÆ’Ã‚Â©todos encapsulan todas las operaciones CRUD y lÃƒÆ’Ã‚Â³gica de negocio
     */

    /**
     * Crear nuevo trabajo con validaciones
     */
    public function createJob(array $data): mixed
    {
        // Validaciones especÃƒÆ’Ã‚Â­ficas del dominio
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Title is required');
        }
        if (empty($data['description'])) {
            throw new \InvalidArgumentException('Description is required');
        }
        if (empty($data['department_id'])) {
            throw new \InvalidArgumentException('Department ID is required');
        }
        // Asignar valores por defecto
        $data['status'] = $data['status'] ?? 'active';
        $data['posted_date'] = $data['posted_date'] ?? date('Y-m-d H:i:s');
        $data['employment_type'] = $data['employment_type'] ?? 'full_time';
        $data['currency'] = $data['currency'] ?? 'EUR';
        // Filtrar solo los campos permitidos
        $filtered = array_intersect_key($data, array_flip($this->fillable));
        try {
            $id = $this->store($filtered);
            $this->invalidateJobCache();
            Logger::info('Job created successfully', [
                'id' => $id,
                'title' => $filtered['title']
            ]);
            return $id;
        } catch (\Exception $e) {
            Logger::error('Failed to create job', ['data' => $filtered, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to create job: ' . $e->getMessage());
        }
    }

    /**
     * Obtener trabajo por ID
     */
    public function getJob($id): ?array
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('ID cannot be empty');
        }

        return $this->findById($id);
    }

    /**
     * Actualizar trabajo con validaciones
     */
    public function updateJob($id, array $data): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('ID cannot be empty');
        }
        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }
        // Verificar que el trabajo existe
        $existing = $this->findById($id);
        if (!$existing) {
            throw new \InvalidArgumentException('Job not found');
        }
        // Filtrar solo los campos permitidos
        $filtered = array_intersect_key($data, array_flip($this->fillable));
        try {
            $result = $this->update($id, $filtered);
            $this->invalidateJobCache();
            Logger::info('Job updated successfully', [
                'id' => $id,
                'fields' => array_keys($filtered)
            ]);
            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to update job', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to update job: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar trabajo con validaciones
     */
    public function deleteJob($id): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('ID cannot be empty');
        }

        // Verificar que el trabajo existe
        $existing = $this->findById($id);
        if (!$existing) {
            throw new \InvalidArgumentException('Job not found');
        }

        try {
            $result = $this->delete($id);
            $this->invalidateJobCache();

            Logger::info('Job deleted successfully', ['id' => $id]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to delete job', ['id' => $id, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to delete job: ' . $e->getMessage());
        }
    }

    /**
     * Buscar trabajos con filtros
     */
    public function searchJobs(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->findAll($filters, $page, $limit);
    }

    /**
     * Buscar trabajo por tÃƒÆ’Ã‚Â­tulo exacto
     */
    public function getJobByTitle(string $title): ?array
    {
        if (empty($title)) {
            throw new \InvalidArgumentException('Title cannot be empty');
        }

        return $this->findOneBy('title', $title);
    }

    /**
     * Contar total de trabajos con filtros
     */
    public function countJobs(array $filters = []): int
    {
        return $this->countAll($filters);
    }

    /**
     * Obtener trabajos activos
     */
    public function getActiveJobs(int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->findAll(['status' => 'active'], $page, $limit);
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
            // Usar la vista que incluye skills_text para bÃƒÆ’Ã‚Âºsqueda mÃƒÆ’Ã‚Â¡s eficiente
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
     * MÃƒÆ’Ã¢â‚¬Â°TODOS CON CACHE Y OPTIMIZACIÃƒÆ’Ã¢â‚¬Å“N
     */

    /**
     * Obtener trabajos paginados con informaciÃƒÆ’Ã‚Â³n de aplicaciones (con cache)
     */
    public function getJobsPaginatedWithCache(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, int $cacheTtl = 300): array
    {
        $cacheKey = $this->generateCacheKey('jobs_paginated', array_merge($filters, ['page' => $page, 'limit' => $limit]));

        try {
            // Intentar obtener desde cache si estÃƒÆ’Ã‚Â¡ habilitado
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
     * MÃƒÆ’Ã‚Â©todo auxiliar para ejecutar la query paginada de trabajos
     */
    private function executeJobsPaginatedQuery(array $filters, int $page, int $limit): array
    {
        // Usar vista con metadatos para los datos
        $data = $this->getJobsWithMeta($filters, $page, $limit);

        // Obtener conteo total para paginaciÃƒÆ’Ã‚Â³n
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
     * MÃƒÆ’Ã¢â‚¬Â°TODOS DE UTILIDAD Y CACHE
     */

    /**
     * Invalidar cache especÃƒÆ’Ã‚Â­fico de trabajos
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
     * Generar clave de cache ÃƒÆ’Ã‚Âºnica
     */
    protected function generateCacheKey(string $method, ...$params): string
    {
        $keyData = [
            'table' => $this->table,
            'method' => $method,
            'params' => $params
        ];

        return substr(md5(serialize($keyData)), 0, 250);
    }

    /**
     * MÃƒÆ’Ã‚Â©todo de logging de errores usando la herencia de BaseModel
     */
    protected function logError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        Logger::error($message, array_merge($context, [
            'model' => static::class,
            'table' => $this->table,
            'exception' => $exception ? $exception->getMessage() : null
        ]));
    }

    /**
     * MÃƒÆ’Ã‚Â©todo de logging de debug usando la herencia de BaseModel
     */
    protected function logDebug(string $message, array $context = []): void
    {
        Logger::debug($message, array_merge($context, [
            'model' => static::class,
            'table' => $this->table
        ]));
    }
}
