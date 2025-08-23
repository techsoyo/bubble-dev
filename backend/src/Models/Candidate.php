<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

class Candidate extends BaseModel
{
    protected string $table = 'candidates'; // Especificar tabla explícitamente

    protected array $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password_hash',
        'phone',
        'linkedin_url',
        'portfolio_url',
        'date_of_birth',
        'nationality',
        'location',
        'profile_image',
        'available_from',
        'desired_salary',
        'desired_contract_type',
        'status',
        'department_id',
        'department_category_id',
        'registration_source',
        'referred_by',
        'cv_filename',
        'professional_summary',
        'soft_skills',
        'hard_skills',
        'languages',
        'interests',
        'references',
        'availability',
        'certifications'
    ];

    // Campos añadidos desde la estructura de bt_candidates
    // (excluyendo id y created_at que no deberían ser asignables)
    protected array $additionalFillable = [
        'cv_original_file',
        'cv_text_file',
        'cv_json_file',
        'data_source',
        'gdpr_consent_given',
        'gdpr_consent_date',
        'IA_processing_consent',
        'IA_consent_date',
        'data_processing_purposes',
        'consent_version',
        'ip_address_consent',
        'user_agent_consent',
        'consent_withdrawn_date',
        'data_retention_until',
        'provider_id',
        'provider_type',
        'avatar'
    ];

    public function __construct(?string $table = null, string|int $primaryKey = 'id')
    {
        parent::__construct($table, $primaryKey);
        // Fusionar fillable adicionales para operaciones de store/update
        $this->fillable = array_values(array_unique(array_merge($this->fillable, $this->additionalFillable)));
    }

    protected array $hidden = [
        'password_hash',
        'cv_parsed_data',
        'cv_original_file',
        'cv_text_file',
        'cv_json_file',
        'gdpr_consent_date',
        'IA_consent_date',
        'ip_address_consent',
        'user_agent_consent'
    ];

    /**
     * MÉTODOS DE VISTAS - Funcionalidad específica de candidatos
     */

    /**
     * Vista básica para listados con filtros y paginación
     */
    public function getCandidatesList(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $sql = "SELECT * FROM vw_candidates_list";
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

        // Añadir paginación
        $offset = ($page - 1) * $limit;
        $sql .= ' LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting candidates list', [
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get candidates list: ' . $e->getMessage());
        }
    }

    /**
     * Vista completa para perfiles de candidatos
     */
    public function getCandidateProfile(string $candidateId): ?array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $sql = "SELECT * FROM vw_candidate_profile_full WHERE id = :id";

        try {
            $result = $this->query($sql, [':id' => $candidateId]);
            return $result[0] ?? null;
        } catch (\Exception $e) {
            $this->logError('Error getting candidate profile', ['candidate_id' => $candidateId], $e);
            throw new \RuntimeException('Failed to get candidate profile: ' . $e->getMessage());
        }
    }

    /**
     * Vista de habilidades planas del candidato
     */
    public function getCandidateSkills(string $candidateId): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        $sql = "SELECT * FROM vw_candidate_skills_flat WHERE candidate_id = :id ORDER BY skill_name";

        try {
            return $this->query($sql, [':id' => $candidateId]);
        } catch (\Exception $e) {
            $this->logError('Error getting candidate skills', ['candidate_id' => $candidateId], $e);
            throw new \RuntimeException('Failed to get candidate skills: ' . $e->getMessage());
        }
    }

    /**
     * Vista de coincidencias trabajo-candidato
     */
    public function getCandidateJobMatches(string $candidateId, int $limit = 10): array
    {
        if (empty($candidateId)) {
            throw new \InvalidArgumentException('Candidate ID cannot be empty');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100');
        }

        $sql = "SELECT * FROM vw_match_candidates_jobs 
                WHERE candidate_id = :id 
                ORDER BY matched_skills DESC 
                LIMIT :limit";

        try {
            return $this->query($sql, [
                ':id' => $candidateId,
                ':limit' => $limit
            ]);
        } catch (\Exception $e) {
            $this->logError('Error getting candidate job matches', [
                'candidate_id' => $candidateId,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get candidate job matches: ' . $e->getMessage());
        }
    }

    /**
     * Vista optimizada con cache para datos básicos
     */
    public function getCandidatesCoreOptimized(array $filters = [], int $cacheTtl = 300): array
    {
        $cacheKey = $this->generateCacheKey('candidates_core', $filters);

        try {
            // Intentar obtener desde cache si está habilitado
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($filters) {
                    return $this->executeCoreCandidatesQuery($filters);
                });
            }

            // Fallback sin cache
            return $this->executeCoreCandidatesQuery($filters);
        } catch (\Exception $e) {
            $this->logError('Error getting optimized candidates', ['filters' => $filters], $e);
            // Fallback directo a la query
            return $this->executeCoreCandidatesQuery($filters);
        }
    }

    /**
     * Método auxiliar para ejecutar la query de candidatos básicos
     */
    private function executeCoreCandidatesQuery(array $filters): array
    {
        $sql = "SELECT * FROM vw_candidates_core";
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

        return $this->query($sql, $params);
    }

    /**
     * Obtener candidatos paginados usando vista optimizada
     */
    public function getCandidatesPaginated(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        // Usar vista optimizada para los datos
        $data = $this->getCandidatesList($filters, $page, $limit);

        // Obtener conteo total para paginación
        $total = $this->countCandidatesWithFilters($filters);

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
     * Contar candidatos con filtros aplicando la misma lógica que las vistas
     */
    private function countCandidatesWithFilters(array $filters): int
    {
        // Usar la tabla base para el conteo ya que las vistas pueden duplicar filas por joins
        $sql = "SELECT COUNT(DISTINCT c.id) as total FROM bt_candidates c";
        $params = [];

        // Aplicar joins si hay filtros que lo requieran
        if (isset($filters['department_name']) || isset($filters['department_category_name']) || isset($filters['skills_text'])) {
            $sql .= " LEFT JOIN bt_departments d ON d.id = c.department_id";
            $sql .= " LEFT JOIN bt_department_categories dc ON dc.id = c.department_category_id";
            $sql .= " LEFT JOIN bt_candidate_skill_map m ON m.candidate_id = c.id";
            $sql .= " LEFT JOIN bt_skills s ON s.id = m.skill_id";
        }

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

        try {
            $result = $this->query($sql, $params);
            return (int)($result[0]['total'] ?? 0);
        } catch (\Exception $e) {
            $this->logError('Error counting candidates', ['filters' => $filters], $e);
            return 0;
        }
    }

    // Mantener métodos existentes del modelo original
    public function findByUserId($userId): ?array
    {
        return $this->findOneBy('user_id', $userId);
    }

    public function updateCVInfo($id, $cvPath, $parsedData): bool
    {
        $data = [
            'cv_file_path' => $cvPath,
            'cv_parsed_data' => json_encode($parsedData),
            'cv_updated_at' => date('Y-m-d H:i:s')
        ];
        return $this->update($id, $data);
    }

    public function updateStatus($id, $status, $notes = null): bool
    {
        $data = [
            'status' => $status,
            'status_updated_at' => date('Y-m-d H:i:s')
        ];
        if ($notes !== null) {
            $data['status_notes'] = $notes;
        }
        return $this->update($id, $data);
    }

    /**
     * Invalidar cache específico de candidatos
     */
    public function invalidateCandidateCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['candidates', 'candidates_core', 'candidates_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating candidate cache', [], $e);
            return 0;
        }
    }
}
