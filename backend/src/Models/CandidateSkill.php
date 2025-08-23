<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para las habilidades declaradas por los candidatos.
 *
 * Gestiona las asociaciones entre candidatos y habilidades con información adicional
 * como nivel de competencia, años de experiencia y estado de verificación.
 * Utiliza la vista vw_candidate_skills_flat para consultas optimizadas que normalizan
 * habilidades desde múltiples fuentes (catálogo + JSON fields).
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
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: CandidateSkill
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['verified']
     * 📊 Total campos fillable: 4
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    

    /**
     * Clave primaria compuesta lógica: (candidate_id, skill_id)
     * Para operaciones básicas se utiliza candidate_id como clave primaria
     *
     * @var string
     */
    protected string $primaryKey = 'candidate_id';

    /**
     * Campos permitidos para asignación masiva
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
     * Campos que no se ocultan específicamente para este modelo
     *
     * @var array<string>
     */
    protected array $hidden = [];

    /**
     * Niveles de competencia válidos
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
     * MÉTODOS USANDO VISTA vw_candidate_skills_flat
     */

    /**
     * Obtiene todas las habilidades normalizadas de un candidato desde múltiples fuentes
     * Combina habilidades del catálogo + hard_skills + soft_skills JSON
     *
     * @param string $candidateId ID del candidato
     * @param bool $useCache Usar cache para la consulta
     * @return array Lista normalizada de habilidades
     * @throws \InvalidArgumentException Si el candidate_id es inválido
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
                return $this->getCachedData($cacheKey, function() use ($candidateId) {
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
     * Obtiene todas las habilidades de un candidato específico agrupadas por tipo
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
                return $this->getCachedData($cacheKey, function() use ($candidateId) {
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
     * Obtiene todos los candidatos que poseen una habilidad específica
     *
     * @param string $skillName Nombre de la habilidad
     * @param int $limit Límite de resultados
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
                return $this->getCachedData($cacheKey, function() use ($skillName, $limit) {
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
     * Actualiza el nivel de competencia de una habilidad específica del candidato
     *
     * @param string $candidateId ID del candidato
     * @param int $skillId ID de la habilidad
     * @param string $proficiencyLevel Nuevo nivel de competencia
     * @param int|null $yearsExperience Años de experiencia (opcional)
     * @return bool True si la actualización fue exitosa
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si la actualización falla
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
            $sql = "UPDATE `{$this->table}` SET ";
            $setClause = [];
            $params = [];

            foreach ($data as $field => $value) {
                $setClause[] = "`$field` = :$field";
                $params[":$field"] = $value;
            }

            $sql .= implode(', ', $setClause);
            $sql .= " WHERE `candidate_id` = :candidate_id AND `skill_id` = :skill_id";
            
            $params[':candidate_id'] = $candidateId;
            $params[':skill_id'] = $skillId;

            $result = $this->query($sql, $params);
            
            // Si no se actualizó ningún registro, crear uno nuevo
            if ($this->db->rowCount() === 0) {
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
     * MÉTODOS DE FUNCIONALIDAD EXISTENTE MANTENIDOS
     */

    /**
     * Devuelve todas las habilidades asociadas a un candidato
     * Mantiene compatibilidad con el método original
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de habilidades
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->findAll(['candidate_id' => $candidateId], 1, self::MAX_LIMIT);
    }

    /**
     * MÉTODOS AUXILIARES PRIVADOS
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
     * Ejecuta la consulta para obtener candidatos con una habilidad específica
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
     * Valida si un nivel de competencia es válido
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
     * Verifica si el cache está habilitado
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

    /**
     * Genera clave de cache consistente
     */
    private function generateCacheKey(string $type, array $params): string
    {
        ksort($params);
        return sprintf('%s_%s_%s', 
            static::class, 
            $type, 
            md5(serialize($params))
        );
    }

    /**
     * Ejecuta consulta SQL con parámetros seguros
     */
    private function query(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }
            
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            Logger::error('SQL execution error in CandidateSkill', [
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtiene el tipo PDO apropiado para un valor
     */
    private function getPdoType($value): int
    {
        if (is_int($value)) {
            return \PDO::PARAM_INT;
        }
        if (is_bool($value)) {
            return \PDO::PARAM_BOOL;
        }
        if (is_null($value)) {
            return \PDO::PARAM_NULL;
        }
        return \PDO::PARAM_STR;
    }

    /**
     * Log de errores usando Logger
     */
    private function logError(string $message, array $context, \Exception $e): void
    {
        Logger::error($message, array_merge($context, [
            'model' => static::class,
            'table' => $this->table,
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]));
    }
}