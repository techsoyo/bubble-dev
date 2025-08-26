<?php declare(strict_types=1);
namespace Models;

use Utils\Logger;

/**
 * Modelo para las habilidades declaradas por los candidatos.
 *
 * Gestiona las asociaciones entre candidatos y habilidades con informaciÃƒÆ’Ã‚Â³n adicional
 * como nivel de competencia, aÃƒÆ’Ã‚Â±os de experiencia y estado de verificaciÃƒÆ’Ã‚Â³n.
 * Utiliza la vista vw_candidate_skills_flat para consultas optimizadas que normalizan
 * habilidades desde mÃƒÆ’Ã‚Âºltiples fuentes (catÃƒÆ’Ã‚Â¡logo + JSON fields).
 *
 * @package Models
 * @version 2.0.0
 * @since 2025-08-23
 */
class CandidateSkill extends BaseModel
{
    /**
     * Tabla de mapeo entre candidatos y habilidades
     *
     * @var string
     */
    protected string $table = 'candidate_skill_map';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÃƒÆ’Ã¢â‚¬Å“N AUTOMÃƒÆ’Ã‚ÂTICA APLICADA
     * Modelo: CandidateSkill
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aÃƒÆ’Ã‚Â±adidos: ninguno
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['verified']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 4
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Clave primaria compuesta lÃƒÆ’Ã‚Â³gica: (candidate_id, skill_id)
     * Para operaciones bÃƒÆ’Ã‚Â¡sicas se utiliza candidate_id como clave primaria
     *
     * @var string
     */
    protected string $primaryKey = 'candidate_id';

    /**
     * Campos permitidos para asignaciÃƒÆ’Ã‚Â³n masiva
     *
     * @var array<string>
     */
    protected array $fillable = [
        'candidate_id',
        'skill_id',
        'proficiency_level',
        'years_experience',
    ];

    /**
     * Campos que no se ocultan especÃƒÆ’Ã‚Â­ficamente para este modelo
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * Niveles de competencia vÃƒÆ’Ã‚Â¡lidos
     *
     * @var array<string>
     */
    protected const PROFICIENCY_LEVELS = ['beginner', 'intermediate', 'advanced', 'expert'];

    /**
     * Cache TTL en segundos para consultas frecuentes
     *
     * @var int
     */
    protected const CACHE_TTL = 300; // 5 minutos

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS USANDO VISTA vw_candidate_skills_flat
     */

    /**
     * Obtiene todas las habilidades normalizadas de un candidato desde mÃƒÆ’Ã‚Âºltiples fuentes
     * Combina habilidades del catÃƒÆ’Ã‚Â¡logo + hard_skills + soft_skills JSON
     *
     * @param string $candidateId ID del candidato
     * @param bool $useCache Usar cache para la consulta
     * @return array Lista normalizada de habilidades
     * @throws \InvalidArgumentException Si el candidate_id es invÃƒÆ’Ã‚Â¡lido
     * @throws \RuntimeException Si la consulta falla
     */
    public function getCandidateSkillsFlat(string $candidateId, bool $useCache = true): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $cacheKey = $this->generateCacheKey('candidate_skills_flat', ['candidate_id' => $candidateId]);

        try {
            if ($useCache && $this->isCacheEnabled()) {
                return $this->getCachedData($cacheKey, function () use ($candidateId) {
                    return $this->executeCandidateSkillsQuery($candidateId);
                });
            }

            return $this->executeCandidateSkillsQuery($candidateId);
        } catch (\Exception $e) {
            $this->logError('Error getting candidate skills flat', ['candidate_id' => $candidateId], $e);
            throw new \RuntimeException('Failed to get candidate skills: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene todas las habilidades de un candidato especÃƒÆ’Ã‚Â­fico agrupadas por tipo
     *
     * @param string $candidateId ID del candidato
     * @param bool $useCache Usar cache para la consulta
     * @return array Habilidades agrupadas por tipo (catalog, hard_skills, soft_skills)
     */
    public function getSkillsByCandidate(string $candidateId, bool $useCache = true): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $cacheKey = $this->generateCacheKey('skills_by_candidate', ['candidate_id' => $candidateId]);

        try {
            if ($useCache && $this->isCacheEnabled()) {
                return $this->getCachedData($cacheKey, function () use ($candidateId) {
                    return $this->executeSkillsByCandidateQuery($candidateId);
                });
            }

            return $this->executeSkillsByCandidateQuery($candidateId);
        } catch (\Exception $e) {
            $this->logError('Error getting skills by candidate', ['candidate_id' => $candidateId], $e);
            throw new \RuntimeException('Failed to get skills by candidate: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene todos los candidatos que poseen una habilidad especÃƒÆ’Ã‚Â­fica
     *
     * @param string $skillName Nombre de la habilidad
     * @param int $limit LÃƒÆ’Ã‚Â­mite de resultados
     * @param bool $useCache Usar cache para la consulta
     * @return array Lista de candidatos con la habilidad especificada
     */
    public function getCandidatesWithSkill(string $skillName, int $limit = 50, bool $useCache = true): array
    {
        if (empty($skillName)) {
            throw new \InvalidArgumentException('Skill name cannot be empty');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $cacheKey = $this->generateCacheKey('candidates_with_skill', [
            'skill_name' => $skillName,
            'limit' => $limit
        ]);

        try {
            if ($useCache && $this->isCacheEnabled()) {
                return $this->getCachedData($cacheKey, function () use ($skillName, $limit) {
                    return $this->executeCandidatesWithSkillQuery($skillName, $limit);
                });
            }

            return $this->executeCandidatesWithSkillQuery($skillName, $limit);
        } catch (\Exception $e) {
            $this->logError('Error getting candidates with skill', [
                'skill_name' => $skillName,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get candidates with skill: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el nivel de competencia de una habilidad especÃƒÆ’Ã‚Â­fica del candidato
     *
     * @param string $candidateId ID del candidato
     * @param int $skillId ID de la habilidad
     * @param string $proficiencyLevel Nuevo nivel de competencia
     * @param int|null $yearsExperience AÃƒÆ’Ã‚Â±os de experiencia (opcional)
     * @return bool True si la actualizaciÃƒÆ’Ã‚Â³n fue exitosa
     * @throws \InvalidArgumentException Si los parÃƒÆ’Ã‚Â¡metros son invÃƒÆ’Ã‚Â¡lidos
     * @throws \RuntimeException Si la actualizaciÃƒÆ’Ã‚Â³n falla
     */
    public function updateSkillProficiency(
        string $candidateId,
        int $skillId,
        string $proficiencyLevel,
        ?int $yearsExperience = null
    ): bool {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        if ($skillId <= 0) {
            throw new \InvalidArgumentException('Skill ID must be greater than 0');
        }

        if (!$this->isValidProficiencyLevel($proficiencyLevel)) {
            throw new \InvalidArgumentException('Invalid proficiency level: ' . $proficiencyLevel);
        }

        if ($yearsExperience !== null && $yearsExperience < 0) {
            throw new \InvalidArgumentException('Years experience cannot be negative');
        }

        try {
            $data = [
                'proficiency_level' => $proficiencyLevel,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($yearsExperience !== null) {
                $data['years_experience'] = $yearsExperience;
            }

            // Intentar actualizar registro existente
            $whereConditions = [
                'candidate_id' => $candidateId,
                'skill_id' => $skillId
            ];

            $updated = $this->update($data, $whereConditions);

            // Si no se actualizÃƒÆ’Ã‚Â³ ningÃƒÆ’Ã‚Âºn registro, crear uno nuevo
            if (!$updated) {
                $insertData = array_merge($data, [
                    'candidate_id' => $candidateId,
                    'skill_id' => $skillId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                return $this->store($insertData) !== false;
            }

            // Limpiar cache relacionado
            $this->clearSkillsCache($candidateId);

            return true;
        } catch (\Exception $e) {
            $this->logError('Error updating skill proficiency', [
                'candidate_id' => $candidateId,
                'skill_id' => $skillId,
                'proficiency_level' => $proficiencyLevel
            ], $e);
            throw new \RuntimeException('Failed to update skill proficiency: ' . $e->getMessage());
        }
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS DE FUNCIONALIDAD EXISTENTE MANTENIDOS
     */

    /**
     * Devuelve todas las habilidades asociadas a un candidato
     * Mantiene compatibilidad con el mÃƒÆ’Ã‚Â©todo original
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de habilidades
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->findAll(['candidate_id' => $candidateId], 1, self::MAX_LIMIT);
    }

    /**
     * MÃƒÆ’Ã¢â‚¬Â°TODOS AUXILIARES PRIVADOS
     */

    /**
     * Ejecuta la consulta para obtener habilidades normalizadas del candidato
     */
    private function executeCandidateSkillsQuery(string $candidateId): array
    {
        $sql = "SELECT * FROM vw_candidate_skills_flat WHERE candidate_id = :candidate_id ORDER BY skill_name";
        return $this->query($sql, [':candidate_id' => $candidateId]);
    }

    /**
     * Ejecuta la consulta para obtener habilidades agrupadas por tipo
     */
    private function executeSkillsByCandidateQuery(string $candidateId): array
    {
        $sql = "SELECT 
                    skill_type,
                    skill_name,
                    skill_name_norm,
                    proficiency_level,
                    years_experience,
                    verified,
                    source
                FROM vw_candidate_skills_flat 
                WHERE candidate_id = :candidate_id 
                ORDER BY skill_type, skill_name";

        $results = $this->query($sql, [':candidate_id' => $candidateId]);

        // Agrupar por tipo de habilidad
        $grouped = [];
        foreach ($results as $skill) {
            $type = $skill['skill_type'] ?? 'other';
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $skill;
        }

        return $grouped;
    }

    /**
     * Ejecuta la consulta para obtener candidatos con una habilidad especÃƒÆ’Ã‚Â­fica
     */
    private function executeCandidatesWithSkillQuery(string $skillName, int $limit): array
    {
        $sql = "SELECT DISTINCT
                    candidate_id,
                    skill_name,
                    skill_name_norm,
                    proficiency_level,
                    years_experience,
                    verified
                FROM vw_candidate_skills_flat 
                WHERE skill_name_norm = :skill_name OR skill_name LIKE :skill_like
                ORDER BY 
                    CASE 
                        WHEN proficiency_level = 'expert' THEN 4
                        WHEN proficiency_level = 'advanced' THEN 3
                        WHEN proficiency_level = 'intermediate' THEN 2
                        WHEN proficiency_level = 'beginner' THEN 1
                        ELSE 0
                    END DESC,
                    years_experience DESC,
                    verified DESC
                LIMIT :limit";

        return $this->query($sql, [
            ':skill_name' => strtolower(trim($skillName)),
            ':skill_like' => '%' . strtolower(trim($skillName)) . '%',
            ':limit' => $limit
        ]);
    }

    /**
     * Valida si un nivel de competencia es vÃƒÆ’Ã‚Â¡lido
     */
    private function isValidProficiencyLevel(string $level): bool
    {
        return in_array(strtolower($level), self::PROFICIENCY_LEVELS);
    }

    /**
     * Limpia el cache relacionado con las habilidades de un candidato
     */
    private function clearSkillsCache(string $candidateId): void
    {
        if ($this->isCacheEnabled()) {
            try {
                $keysToDelete = [
                    $this->generateCacheKey('candidate_skills_flat', ['candidate_id' => $candidateId]),
                    $this->generateCacheKey('skills_by_candidate', ['candidate_id' => $candidateId])
                ];

                foreach ($keysToDelete as $key) {
                    if (isset($this->cache[$key])) {
                        unset($this->cache[$key]);
                    }
                }
            } catch (\Exception $e) {
                Logger::warning('Failed to clear skills cache', [
                    'candidate_id' => $candidateId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Verifica si el cache estÃƒÆ’Ã‚Â¡ habilitado
     */
    private function isCacheEnabled(): bool
    {
        return class_exists('\Utils\Cache') || !empty($this->cache);
    }

    /**
     * Obtiene datos del cache o ejecuta el callback
     */
    private function getCachedData(string $cacheKey, callable $callback): array
    {
        // Verificar cache local primero
        if (isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
            unset($this->cache[$cacheKey]);
        }

        // Ejecutar callback y cachear resultado
        $data = $callback();

        $this->cache[$cacheKey] = [
            'data' => $data,
            'expires' => time() + self::CACHE_TTL
        ];

        return $data;
    }

    // ==========================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS CRUD ENCAPSULADOS ESTÃƒÆ’Ã‚ÂNDAR
    // ==========================================

    /**
     * Crear nuevo candidate_skill con validaciones
     * @param array $data Datos del nuevo candidate_skill
     * @return mixed ID del nuevo candidate_skill o false en caso de error
     */
    public function createCandidateSkill(array $data): mixed
    {
        try {
            $this->validateCandidateSkillData($data);
            $id = $this->store($data);
            $this->invalidateCandidateSkillCache();

            Logger::info('CandidateSkill created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating candidate_skill', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener candidate_skill por ID
     * @param mixed $id ID del candidate_skill
     * @return array|null Datos del candidate_skill o null si no existe
     */
    public function getCandidateSkill($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving candidate_skill', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar candidate_skill con validaciones
     * @param mixed $id ID del candidate_skill a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualizaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function updateCandidateSkill($id, array $data): bool
    {
        try {
            $this->validateCandidateSkillData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateCandidateSkillCache();
                Logger::info('CandidateSkill updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating candidate_skill', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar candidate_skill con validaciones
     * @param mixed $id ID del candidate_skill a eliminar
     * @return bool True si la eliminaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function deleteCandidateSkill($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateCandidateSkillCache();
                Logger::info('CandidateSkill deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting candidate_skill', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar candidate_skills con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @param int $page PÃƒÆ’Ã‚Â¡gina actual
     * @param int $limit Registros por pÃƒÆ’Ã‚Â¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de candidate_skills
     */
    public function searchCandidateSkills(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching candidate_skills', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de candidate_skills con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @return int NÃƒÆ’Ã‚Âºmero total de candidate_skills
     */
    public function countCandidateSkills(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting candidate_skills', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ==========================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS DE VALIDACIÃƒÆ’Ã¢â‚¬Å“N ESPECÃƒÆ’Ã‚ÂFICOS
    // ==========================================

    /**
     * Validar datos especÃƒÆ’Ã‚Â­ficos de candidate_skills
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualizaciÃƒÆ’Ã‚Â³n (opcional)
     * @throws \InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    private function validateCandidateSkillData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones especÃƒÆ’Ã‚Â­ficas del modelo
    }

    /**
     * Invalidar cache especÃƒÆ’Ã‚Â­fico de candidate_skills
     */
    public function invalidateCandidateSkillCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['candidate_skills', 'candidate_skill_core', 'candidate_skill_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating candidate_skill cache', [], $e);
            return 0;
        }
    }
}
