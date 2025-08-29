<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

class Candidate extends BaseModel
{
    protected string $table = 'candidates';

    /**
     * Campos que pueden ser asignados masivamente
     * Basados en la estructura real de la tabla bt_candidates
     *
     * @var array<string>
     */
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
        'certifications',
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
     * MÉTODOS DE VISTAS - Funcionalidad especí­fica de candidatos
     */

    /**
     * Vista bí¡sica para listados con filtros y paginación
     */
    public function getCandidatesList(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
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

        $offset = ($page - 1) * $limit;
        $sql .= ' LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        return $this->query($sql, $params);
    }

    /**
     * Vista completa para perfiles de candidatos
     */
    public function getCandidateProfile(string $candidateId): ?array
    {
        $sql = "SELECT * FROM vw_candidate_profile_full WHERE id = :id";
        $result = $this->query($sql, [':id' => $candidateId]);
        return $result[0] ?? null;
    }

    /**
     * Vista de habilidades planas del candidato
     */
    public function getCandidateSkills(string $candidateId): array
    {
        $sql = "SELECT * FROM vw_candidate_skills_flat WHERE candidate_id = :id ORDER BY skill_name";
        return $this->query($sql, [':id' => $candidateId]);
    }

    /**
     * Vista de coincidencias trabajo-candidato
     */
    public function getCandidateJobMatches(string $candidateId, int $limit = 10): array
    {
        $sql = "SELECT * FROM vw_match_candidates_jobs 
                WHERE candidate_id = :id 
                ORDER BY matched_skills DESC 
                LIMIT :limit";

        return $this->query($sql, [
            ':id' => $candidateId,
            ':limit' => $limit
        ]);
    }

    /**
     * Obtener candidatos paginados usando vista optimizada
     */
    public function getCandidatesPaginated(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        $data = $this->getCandidatesList($filters, $page, $limit);
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
        $sql = "SELECT COUNT(DISTINCT c.id) as total FROM bt_candidates c";
        $params = [];

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

        $result = $this->query($sql, $params);
        return (int)($result[0]['total'] ?? 0);
    }

    // Métodos especí­ficos mantenidos
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
}
