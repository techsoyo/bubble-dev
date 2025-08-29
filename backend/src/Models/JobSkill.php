<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;
use PDO;

/**
 * Modelo para las habilidades requeridas en ofertas de trabajo.
 * 
 * Proporciona funcionalidades avanzadas para Anáslisis de mercado de habilidades,
 * matching de candidatos, y Anáslisis de demanda/oferta usando vistas optimizadas.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class JobSkill extends BaseModel
{
    protected string $table = 'job_skills';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: JobSkill
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aí±adidos: ['skill_id', 'required_level', 'weight']
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['skill', 'proficiency_level', 'years_experience']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 5
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */

    protected string $primaryKey = 'job_id';

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'job_id',
        'skill_id',
        'required_level',
        'is_required',
        'weight',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     */
    protected array $hidden = [];

    /**
     * Cache para Anáslisis de mercado (TTL: 1 hora)
     */
    private const CACHE_TTL = 3600;

    /**
     * MÉTODOS BÁSICOS - Funcionalidad original mejorada
     */

    /**
     * Devuelve todas las habilidades asociadas a un puesto de trabajo.
     *
     * @param string $jobId ID del puesto
     * @param array $orderBy Ordenamiento opcional
     * @return array Lista de habilidades con datos completos
     */
    public function findByJobId(string $jobId, array $orderBy = ['is_required' => 'DESC', 'proficiency_level' => 'DESC']): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        try {
            return $this->findAll(['job_id' => $jobId], 1, self::MAX_LIMIT, $orderBy);
        } catch (\Exception $e) {
            $this->logError('Error getting job skills', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * MÉTODOS DE ANÁLISIS DE DEMANDA Y OFERTA - Usando vistas optimizadas
     */

    /**
     * Obtiene Anáslisis de demanda de habilidades usando vw_skill_demand
     * 
     * @param array $filters Filtros opcionales
     * @param int $limit Lí­mite de resultados
     * @return array Anáslisis de demanda por habilidad
     */
    public function getSkillDemandAnalysis(array $filters = [], int $limit = 50): array
    {
        $cacheKey = "skill_demand_analysis_" . md5(serialize($filters) . $limit);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = "SELECT 
                    skill_name_norm,
                    jobs_count,
                    ROUND((jobs_count * 100.0 / total.total_jobs), 2) as demand_percentage,
                    'high' as demand_level
                FROM vw_skill_demand 
                CROSS JOIN (SELECT COUNT(DISTINCT job_id) as total_jobs FROM vw_skill_demand) as total";

        $params = [];

        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` LIKE :filter_$field";
                    $params[":filter_$field"] = "%$value%";
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        $sql .= " ORDER BY jobs_count DESC, skill_name_norm ASC LIMIT :limit";
        $params[':limit'] = $limit;

        try {
            $result = $this->query($sql, $params);

            // Clasificar niveles de demanda
            $result = $this->classifyDemandLevels($result);

            // Cache por 1 hora
            $this->cache[$cacheKey] = $result;

            $this->logDebug('Skill demand analysis retrieved', [
                'skills_count' => count($result),
                'cache_key' => $cacheKey
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting skill demand analysis', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtiene Anáslisis de oferta de habilidades usando vw_skill_supply
     * 
     * @param array $filters Filtros opcionales
     * @param int $limit Lí­mite de resultados
     * @return array Anáslisis de oferta por habilidad
     */
    public function getSkillSupplyAnalysis(array $filters = [], int $limit = 50): array
    {
        $cacheKey = "skill_supply_analysis_" . md5(serialize($filters) . $limit);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = "SELECT 
                    skill_name_norm,
                    candidates_count,
                    ROUND((candidates_count * 100.0 / total.total_candidates), 2) as supply_percentage,
                    'medium' as supply_level
                FROM vw_skill_supply 
                CROSS JOIN (SELECT COUNT(DISTINCT candidate_id) as total_candidates FROM vw_skill_supply) as total";

        $params = [];

        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "`$field` LIKE :filter_$field";
                    $params[":filter_$field"] = "%$value%";
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        $sql .= " ORDER BY candidates_count DESC, skill_name_norm ASC LIMIT :limit";
        $params[':limit'] = $limit;

        try {
            $result = $this->query($sql, $params);

            // Clasificar niveles de oferta
            $result = $this->classifySupplyLevels($result);

            // Cache por 1 hora
            $this->cache[$cacheKey] = $result;

            $this->logDebug('Skill supply analysis retrieved', [
                'skills_count' => count($result),
                'cache_key' => $cacheKey
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting skill supply analysis', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtiene Anáslisis de brecha del mercado combinando demanda y oferta
     * 
     * @param int $limit Lí­mite de resultados
     * @return array Anáslisis de brechas por habilidad
     */
    public function getSkillMarketGap(int $limit = 30): array
    {
        $cacheKey = "skill_market_gap_" . $limit;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = "SELECT 
                    COALESCE(d.skill_name_norm, s.skill_name_norm) as skill_name,
                    COALESCE(d.jobs_count, 0) as demand_jobs,
                    COALESCE(s.candidates_count, 0) as supply_candidates,
                    CASE 
                        WHEN COALESCE(s.candidates_count, 0) = 0 THEN 999999
                        ELSE ROUND(COALESCE(d.jobs_count, 0) / s.candidates_count, 2)
                    END as demand_supply_ratio,
                    CASE 
                        WHEN COALESCE(s.candidates_count, 0) = 0 AND COALESCE(d.jobs_count, 0) > 0 THEN 'critical_shortage'
                        WHEN COALESCE(d.jobs_count, 0) / COALESCE(s.candidates_count, 1) >= 2.0 THEN 'high_demand'
                        WHEN COALESCE(d.jobs_count, 0) / COALESCE(s.candidates_count, 1) >= 1.5 THEN 'moderate_demand'
                        WHEN COALESCE(d.jobs_count, 0) / COALESCE(s.candidates_count, 1) >= 0.5 THEN 'balanced'
                        ELSE 'oversupply'
                    END as market_status
                FROM vw_skill_demand d
                FULL OUTER JOIN vw_skill_supply s ON d.skill_name_norm = s.skill_name_norm
                WHERE COALESCE(d.jobs_count, 0) > 0 OR COALESCE(s.candidates_count, 0) > 0
                ORDER BY demand_supply_ratio DESC, skill_name ASC
                LIMIT :limit";

        $params = [':limit' => $limit];

        try {
            $result = $this->query($sql, $params);

            // Enriquecer con recomendaciones
            $result = $this->addMarketRecommendations($result);

            // Cache por 1 hora
            $this->cache[$cacheKey] = $result;

            $this->logDebug('Market gap analysis retrieved', [
                'gaps_count' => count($result),
                'cache_key' => $cacheKey
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting market gap analysis', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtiene habilidades  más demandadas (trending)
     * 
     * @param int $days Dí­as hacia atrí¡s para el Anáslisis
     * @param int $limit Lí­mite de resultados
     * @return array Habilidades trending
     */
    public function getTrendingSkills(int $days = 30, int $limit = 20): array
    {
        $cacheKey = "trending_skills_{$days}_{$limit}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = "SELECT 
                    js.skill,
                    COUNT(DISTINCT js.job_id) as recent_jobs_count,
                    COUNT(DISTINCT CASE WHEN js.is_required = 1 THEN js.job_id END) as required_jobs_count,
                    ROUND(AVG(CASE WHEN js.years_experience > 0 THEN js.years_experience END), 1) as avg_experience_required,
                    ROUND((COUNT(DISTINCT js.job_id) * 100.0 / total.total_recent_jobs), 2) as trend_percentage
                FROM {$this->table} js
                INNER JOIN " . T('jobs') . " j ON js.job_id = j.id
                CROSS JOIN (
                    SELECT COUNT(DISTINCT id) as total_recent_jobs 
                    FROM " . T('jobs') . " 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days1 DAY)
                ) as total
                WHERE j.created_at >= DATE_SUB(NOW(), INTERVAL :days2 DAY)
                    AND j.status = 'active'
                GROUP BY js.skill
                HAVING recent_jobs_count >= 2
                ORDER BY recent_jobs_count DESC, required_jobs_count DESC, avg_experience_required ASC
                LIMIT :limit";

        $params = [
            ':days1' => $days,
            ':days2' => $days,
            ':limit' => $limit
        ];

        try {
            $result = $this->query($sql, $params);

            // Enriquecer con metadata
            $result = $this->enrichTrendingSkills($result);

            // Cache por 30 minutos ( más frecuente para trends)
            $this->cache[$cacheKey] = $result;

            $this->logDebug('Trending skills retrieved', [
                'skills_count' => count($result),
                'days' => $days,
                'cache_key' => $cacheKey
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting trending skills', [
                'days' => $days,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * MÉTODOS DE INTEGRACIÓN CON VISTAS - Para matching y búsquedas avanzadas
     */

    /**
     * Obtiene trabajos con metadata usando vw_jobs_with_meta filtrado por habilidades
     * 
     * @param array $skills Lista de habilidades para filtrar
     * @param array $filters Filtros adicionales
     * @param int $limit Lí­mite de resultados
     * @return array Trabajos con metadata
     */
    public function getJobsWithMetaBySkills(array $skills = [], array $filters = [], int $limit = 20): array
    {
        if (empty($skills)) {
            throw new \InvalidArgumentException('Skills array cannot be empty');
        }

        $sql = "SELECT DISTINCT
                    jwm.*,
                    COUNT(DISTINCT js.skill) as matched_skills_count,
                    GROUP_CONCAT(DISTINCT js.skill ORDER BY js.skill) as matched_skills
                FROM vw_jobs_with_meta jwm
                INNER JOIN {$this->table} js ON jwm.id = js.job_id";

        $params = [];
        $whereConditions = [];

        // Filtro de habilidades
        $skillsPlaceholders = [];
        foreach ($skills as $index => $skill) {
            $skillsPlaceholders[] = ":skill_$index";
            $params[":skill_$index"] = $skill;
        }
        $whereConditions[] = "js.skill IN (" . implode(', ', $skillsPlaceholders) . ")";

        // Filtros adicionales
        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "jwm.`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
        }

        if (!empty($whereConditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        $sql .= " GROUP BY jwm.id, jwm.title, jwm.company_name, jwm.location, jwm.type, jwm.skills_text, jwm.requirements, jwm.benefits
                 ORDER BY matched_skills_count DESC, jwm.title ASC
                 LIMIT :limit";
        $params[':limit'] = $limit;

        try {
            $result = $this->query($sql, $params);

            $this->logDebug('Jobs with meta by skills retrieved', [
                'skills' => $skills,
                'jobs_count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting jobs with meta by skills', [
                'skills' => $skills,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Encuentra candidatos compatibles usando vw_match_candidates_jobs
     * 
     * @param string $jobId ID del trabajo
     * @param int $minMatchedSkills Mí­nimo de habilidades coincidentes
     * @param int $limit Lí­mite de resultados
     * @return array Candidatos compatibles ordenados por matching
     */
    public function findCandidateMatches(string $jobId, int $minMatchedSkills = 1, int $limit = 50): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        $sql = "SELECT 
                    mcj.*,
                    c.name as candidate_name,
                    c.email as candidate_email,
                    c.location as candidate_location,
                    c.status as candidate_status,
                    ROUND((mcj.matched_skills * 100.0 / total_job_skills.total_skills), 2) as match_percentage
                FROM vw_match_candidates_jobs mcj
                INNER JOIN " . T('candidates') . " c ON mcj.candidate_id = c.id
                CROSS JOIN (
                    SELECT COUNT(*) as total_skills 
                    FROM {$this->table} 
                    WHERE job_id = :job_id_total
                ) as total_job_skills
                WHERE mcj.job_id = :job_id
                    AND mcj.matched_skills >= :min_matched_skills
                    AND c.status = 'active'
                ORDER BY mcj.matched_skills DESC, match_percentage DESC
                LIMIT :limit";

        $params = [
            ':job_id' => $jobId,
            ':job_id_total' => $jobId,
            ':min_matched_skills' => $minMatchedSkills,
            ':limit' => $limit
        ];

        try {
            $result = $this->query($sql, $params);

            // Enriquecer con datos adicionales
            $result = $this->enrichCandidateMatches($result, $jobId);

            $this->logDebug('Candidate matches found', [
                'job_id' => $jobId,
                'matches_count' => count($result),
                'min_matched_skills' => $minMatchedSkills
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error finding candidate matches', [
                'job_id' => $jobId,
                'min_matched_skills' => $minMatchedSkills,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtiene skills optimizadas para un job usando vistas combinadas
     * 
     * @param string $jobId ID del trabajo
     * @return array Habilidades optimizadas con Anáslisis de mercado
     */
    public function getJobSkillsOptimized(string $jobId): array
    {
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID cannot be empty');
        }

        $cacheKey = "job_skills_optimized_" . $jobId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $sql = "SELECT 
                    js.*,
                    COALESCE(sd.jobs_count, 0) as market_demand_jobs,
                    COALESCE(ss.candidates_count, 0) as market_supply_candidates,
                    CASE 
                        WHEN COALESCE(ss.candidates_count, 0) = 0 THEN 'very_scarce'
                        WHEN COALESCE(sd.jobs_count, 0) / ss.candidates_count >= 2.0 THEN 'scarce'
                        WHEN COALESCE(sd.jobs_count, 0) / ss.candidates_count >= 1.0 THEN 'balanced'
                        ELSE 'abundant'
                    END as availability_status,
                    ROUND(COALESCE(sd.jobs_count, 0) / COALESCE(ss.candidates_count, 1), 2) as demand_supply_ratio
                FROM {$this->table} js
                LEFT JOIN vw_skill_demand sd ON js.skill = sd.skill_name_norm
                LEFT JOIN vw_skill_supply ss ON js.skill = ss.skill_name_norm
                WHERE js.job_id = :job_id
                ORDER BY js.is_required DESC, demand_supply_ratio DESC, js.proficiency_level DESC";

        $params = [':job_id' => $jobId];

        try {
            $result = $this->query($sql, $params);

            // Enriquecer con recomendaciones de reclutamiento
            $result = $this->addRecruitmentRecommendations($result);

            // Cache por 2 horas
            $this->cache[$cacheKey] = $result;

            $this->logDebug('Optimized job skills retrieved', [
                'job_id' => $jobId,
                'skills_count' => count($result),
                'cache_key' => $cacheKey
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error getting optimized job skills', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * MÉTODOS DE UTILIDAD Y HELPERS PRIVADOS
     */

    /**
     * Clasifica niveles de demanda basado en percentiles
     */
    private function classifyDemandLevels(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        $jobsCounts = array_column($data, 'jobs_count');
        $percentile75 = $this->calculatePercentile($jobsCounts, 75);
        $percentile50 = $this->calculatePercentile($jobsCounts, 50);
        $percentile25 = $this->calculatePercentile($jobsCounts, 25);

        foreach ($data as &$row) {
            $count = $row['jobs_count'];
            if ($count >= $percentile75) {
                $row['demand_level'] = 'very_high';
            } elseif ($count >= $percentile50) {
                $row['demand_level'] = 'high';
            } elseif ($count >= $percentile25) {
                $row['demand_level'] = 'medium';
            } else {
                $row['demand_level'] = 'low';
            }
        }

        return $data;
    }

    /**
     * Clasifica niveles de oferta basado en percentiles
     */
    private function classifySupplyLevels(array $data): array
    {
        if (empty($data)) {
            return $data;
        }

        $candidatesCounts = array_column($data, 'candidates_count');
        $percentile75 = $this->calculatePercentile($candidatesCounts, 75);
        $percentile50 = $this->calculatePercentile($candidatesCounts, 50);
        $percentile25 = $this->calculatePercentile($candidatesCounts, 25);

        foreach ($data as &$row) {
            $count = $row['candidates_count'];
            if ($count >= $percentile75) {
                $row['supply_level'] = 'very_high';
            } elseif ($count >= $percentile50) {
                $row['supply_level'] = 'high';
            } elseif ($count >= $percentile25) {
                $row['supply_level'] = 'medium';
            } else {
                $row['supply_level'] = 'low';
            }
        }

        return $data;
    }

    /**
     * Aí±ade recomendaciones de mercado basadas en brechas
     */
    private function addMarketRecommendations(array $data): array
    {
        foreach ($data as &$row) {
            $recommendations = [];

            switch ($row['market_status']) {
                case 'critical_shortage':
                    $recommendations[] = 'Incrementar presupuesto de reclutamiento';
                    $recommendations[] = 'Considerar programas de capacitación interna';
                    $recommendations[] = 'Evaluar contratación remota o internacional';
                    break;

                case 'high_demand':
                    $recommendations[] = 'Acelerar procesos de selección';
                    $recommendations[] = 'Mejorar propuesta de valor al candidato';
                    $recommendations[] = 'Considerar rangos salariales competitivos';
                    break;

                case 'moderate_demand':
                    $recommendations[] = 'Mantener estrategia actual de reclutamiento';
                    $recommendations[] = 'Optimizar canales de sourcing';
                    break;

                case 'balanced':
                    $recommendations[] = 'Enfoque selectivo en candidatos de calidad';
                    $recommendations[] = 'Aprovechar mercado estable';
                    break;

                case 'oversupply':
                    $recommendations[] = 'Elevar estí¡ndares de selección';
                    $recommendations[] = 'Proceso de evaluación  más riguroso';
                    $recommendations[] = 'Oportunidad para negociar condiciones';
                    break;
            }

            $row['recommendations'] = $recommendations;
        }

        return $data;
    }

    /**
     * Enriquece datos de habilidades trending
     */
    private function enrichTrendingSkills(array $data): array
    {
        foreach ($data as &$row) {
            $row['trend_indicator'] = $this->getTrendIndicator($row['trend_percentage']);
            $row['priority_level'] = $this->getPriorityLevel($row['required_jobs_count'], $row['recent_jobs_count']);
            $row['experience_category'] = $this->categorizeExperience($row['avg_experience_required']);
        }

        return $data;
    }

    /**
     * Enriquece matches de candidatos
     */
    private function enrichCandidateMatches(array $data, string $jobId): array
    {
        foreach ($data as &$row) {
            $row['recommendation_score'] = $this->calculateRecommendationScore($row);
            $row['fit_assessment'] = $this->assessFit($row['match_percentage']);
        }

        return $data;
    }

    /**
     * Aí±ade recomendaciones de reclutamiento
     */
    private function addRecruitmentRecommendations(array $data): array
    {
        foreach ($data as &$row) {
            $recruitmentAdvice = [];

            switch ($row['availability_status']) {
                case 'very_scarce':
                    $recruitmentAdvice[] = 'Skill muy escaso - presupuesto alto requerido';
                    $recruitmentAdvice[] = 'Considerar alternativas o training';
                    break;

                case 'scarce':
                    $recruitmentAdvice[] = 'Skill escaso - competencia alta';
                    $recruitmentAdvice[] = 'Acelerar proceso de selección';
                    break;

                case 'balanced':
                    $recruitmentAdvice[] = 'Disponibilidad normal';
                    break;

                case 'abundant':
                    $recruitmentAdvice[] = 'Alta disponibilidad - proceso selectivo';
                    break;
            }

            if ($row['is_required']) {
                $recruitmentAdvice[] = 'Skill obligatorio - no negociable';
            }

            $row['recruitment_advice'] = $recruitmentAdvice;
        }

        return $data;
    }

    /**
     * Calcula percentil de un array de números
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if (floor($index) == $index) {
            return $values[$index];
        } else {
            $lower = $values[floor($index)];
            $upper = $values[ceil($index)];
            return $lower + ($upper - $lower) * ($index - floor($index));
        }
    }

    /**
     * Obtiene indicador de tendencia
     */
    private function getTrendIndicator(float $percentage): string
    {
        if ($percentage >= 20) return 'hot';
        if ($percentage >= 10) return 'trending';
        if ($percentage >= 5) return 'growing';
        return 'stable';
    }

    /**
     * Obtiene nivel de prioridad
     */
    private function getPriorityLevel(int $requiredJobs, int $totalJobs): string
    {
        $requiredRatio = $totalJobs > 0 ? ($requiredJobs / $totalJobs) : 0;

        if ($requiredRatio >= 0.8) return 'critical';
        if ($requiredRatio >= 0.5) return 'high';
        if ($requiredRatio >= 0.3) return 'medium';
        return 'low';
    }

    /**
     * Categoriza experiencia requerida
     */
    private function categorizeExperience(?float $years): string
    {
        if ($years === null) return 'not_specified';
        if ($years <= 1) return 'entry_level';
        if ($years <= 3) return 'junior';
        if ($years <= 5) return 'mid_level';
        if ($years <= 8) return 'senior';
        return 'expert';
    }

    /**
     * Calcula score de recomendación
     */
    private function calculateRecommendationScore(array $candidateData): int
    {
        $score = intval($candidateData['match_percentage']);

        // Bonificaciones por skills overlap
        if ($candidateData['skills_overlap'] ?? 0 > 0.8) $score += 10;
        if ($candidateData['skills_overlap'] ?? 0 > 0.6) $score += 5;

        return min(100, $score);
    }

    /**
     * Evalúa fit del candidato
     */
    private function assessFit(float $matchPercentage): string
    {
        if ($matchPercentage >= 80) return 'excellent';
        if ($matchPercentage >= 60) return 'good';
        if ($matchPercentage >= 40) return 'moderate';
        if ($matchPercentage >= 20) return 'low';
        return 'poor';
    }

    /**
     * Limpiar cache especí­fico
     */
    public function clearSkillsCache(): void
    {
        $keysToRemove = array_filter(array_keys($this->cache), function ($key) {
            return strpos($key, 'skill_') === 0 ||
                strpos($key, 'trending_') === 0 ||
                strpos($key, 'job_skills_') === 0;
        });

        foreach ($keysToRemove as $key) {
            unset($this->cache[$key]);
        }

        $this->logDebug('Skills cache cleared', [
            'cleared_keys' => count($keysToRemove)
        ]);
    }

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo job_skill con validaciones
     * @param array $data Datos del nuevo job_skill
     * @return mixed ID del nuevo job_skill o false en caso de error
     */
    public function createJobSkill(array $data): mixed
    {
        try {
            $this->validateJobSkillData($data);
            // Filtrar solo los campos permitidos por $fillable
            $filtered = [];
            foreach ($this->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }
            $id = $this->store($filtered);
            $this->invalidateJobSkillCache();

            Logger::info('JobSkill created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating job_skill', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener job_skill por ID
     * @param mixed $id ID del job_skill
     * @return array|null Datos del job_skill o null si no existe
     */
    public function getJobSkill($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving job_skill', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar job_skill con validaciones
     * @param mixed $id ID del job_skill a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateJobSkill($id, array $data): bool
    {
        try {
            $this->validateJobSkillData($data, $id);
            // Filtrar solo los campos permitidos por $fillable
            $filtered = [];
            foreach ($this->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }
            $result = $this->update($id, $filtered);

            if ($result) {
                $this->invalidateJobSkillCache();
                Logger::info('JobSkill updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($filtered)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating job_skill', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar job_skill con validaciones
     * @param mixed $id ID del job_skill a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteJobSkill($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateJobSkillCache();
                Logger::info('JobSkill deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting job_skill', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar job_skills con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Pí¡gina actual
     * @param int $limit Registros por pí¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de job_skills
     */
    public function searchJobSkills(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching job_skills', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de job_skills con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de job_skills
     */
    public function countJobSkills(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting job_skills', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ==========================================
    // MÉTODOS DE VALIDACIÓN ESPECíFICOS
    // ==========================================

    /**
     * Validar datos especí­ficos de job_skills
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son ví¡lidos
     */
    private function validateJobSkillData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones especí­ficas del modelo
    }

    /**
     * Invalidar cache especí­fico de job_skills
     */
    public function invalidateJobSkillCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['job_skills', 'job_skill_core', 'job_skill_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating job_skill cache', [], $e);
            return 0;
        }
    }
}
