<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para los reclutadores de la plataforma.
 * 
 * Este modelo gestiona los perfiles del staff de reclutamiento, incluyendo
 * la distribución de carga de trabajo, métricas de rendimiento y asignación
 * de candidatos. Utiliza la vista vw_recruiter_load para optimizar consultas
 * de carga de trabajo.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class Recruiter extends BaseModel
{
    /**
     * Tabla de base de datos - bt_staff_profiles para recruiters
     */
    protected string $table = 'staff_profiles';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: Recruiter
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aí±adidos: ['candidate_id', 'first_name', 'last_name', 'hire_date', 'salary_range', 'emergency_contact_name', 'emergency_contact_phone', 'notes', 'profile_photo', 'is_active', 'languages', 'certifications', 'performance_metrics', 'created_by', 'updated_by']
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['name', 'active', 'max_candidates']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 20
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'candidate_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'department_id',
        'hire_date',
        'salary_range',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'profile_photo',
        'is_active',
        'specializations',
        'languages',
        'certifications',
        'performance_metrics',
        'created_by',
        'updated_by',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     */
    protected array $hidden = [
        'salary_info',
        'performance_metrics'
    ];

    /**
     * Cache TTL por defecto para métricas (5 minutos)
     */
    private const CACHE_TTL = 300;

    /**
     * Lí­mite mí¡ximo de candidatos por recruiter por defecto
     */
    private const DEFAULT_MAX_CANDIDATES = 50;

    /**
     * Obtener la carga de trabajo actual de un recruiter
     * 
     * Utiliza la vista vw_recruiter_load para obtener métricas optimizadas
     * de candidatos activos asignados en los últimos 180 dí­as.
     * 
     * @param int $recruiterId ID del recruiter
     * @return array|null Datos de carga de trabajo
     */
    public function getRecruiterLoad(int $recruiterId): ?array
    {
        if ($recruiterId <= 0) {
            throw new \InvalidArgumentException('Recruiter ID must be positive');
        }

        $sql = "SELECT recruiter_id, name, department_id, active_candidates 
                FROM vw_recruiter_load 
                WHERE recruiter_id = :recruiter_id";

        try {
            $result = $this->query($sql, [':recruiter_id' => $recruiterId]);
            return $result[0] ?? null;
        } catch (\Exception $e) {
            $this->logError('Error getting recruiter load', ['recruiter_id' => $recruiterId], $e);
            throw new \RuntimeException('Failed to get recruiter load: ' . $e->getMessage());
        }
    }

    /**
     * Obtener métricas de rendimiento de un recruiter
     * 
     * Incluye estadí­sticas de candidatos procesados, entrevistas realizadas,
     * contrataciones exitosas y tiempo promedio de procesamiento.
     * 
     * @param int $recruiterId ID del recruiter
     * @param int $cacheTtl Tiempo de vida del cache (segundos)
     * @return array Métricas de rendimiento
     */
    public function getRecruiterStats(int $recruiterId, int $cacheTtl = self::CACHE_TTL): array
    {
        if ($recruiterId <= 0) {
            throw new \InvalidArgumentException('Recruiter ID must be positive');
        }

        $cacheKey = $this->generateCacheKey('recruiter_stats', ['id' => $recruiterId]);

        try {
            // Intentar obtener desde cache si estí¡ habilitado
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($recruiterId) {
                    return $this->executeRecruiterStatsQuery($recruiterId);
                });
            }

            // Fallback sin cache
            return $this->executeRecruiterStatsQuery($recruiterId);
        } catch (\Exception $e) {
            $this->logError('Error getting recruiter stats', ['recruiter_id' => $recruiterId], $e);
            // Fallback con estadí­sticas bí¡sicas
            return [
                'recruiter_id' => $recruiterId,
                'total_candidates' => 0,
                'active_candidates' => 0,
                'completed_interviews' => 0,
                'successful_hires' => 0,
                'avg_processing_time_days' => 0,
                'efficiency_score' => 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Obtener lista de recruiters disponibles para asignaciones
     * 
     * Filtra recruiters activos con capacidad disponible basí¡ndose en
     * su carga actual versus el mí¡ximo configurado.
     * 
     * @param int|null $departmentId Filtrar por departamento especí­fico
     * @param array $specializations Filtrar por especializaciones
     * @return array Lista de recruiters disponibles
     */
    public function getAvailableRecruiters(?int $departmentId = null, array $specializations = []): array
    {
        $sql = "SELECT r.recruiter_id, r.name, r.department_id, r.active_candidates,
                       sp.max_candidates, sp.specializations, sp.email, sp.phone,
                       (sp.max_candidates - COALESCE(r.active_candidates, 0)) AS available_capacity
                FROM vw_recruiter_load r
                RIGHT JOIN bt_staff_profiles sp ON sp.id = r.recruiter_id
                WHERE sp.active = 1 
                AND sp.role = 'recruiter'
                AND (sp.max_candidates - COALESCE(r.active_candidates, 0)) > 0";

        $params = [];

        // Filtrar por departamento si se especifica
        if ($departmentId !== null) {
            $sql .= " AND r.department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }

        // Filtrar por especializaciones si se especifican
        if (!empty($specializations)) {
            $specializationConditions = [];
            foreach ($specializations as $index => $specialization) {
                $specializationConditions[] = "JSON_CONTAINS(sp.specializations, :spec_$index)";
                $params[":spec_$index"] = json_encode($specialization);
            }
            if (!empty($specializationConditions)) {
                $sql .= " AND (" . implode(' OR ', $specializationConditions) . ")";
            }
        }

        $sql .= " ORDER BY available_capacity DESC, r.active_candidates ASC";

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting available recruiters', [
                'department_id' => $departmentId,
                'specializations' => $specializations
            ], $e);
            throw new \RuntimeException('Failed to get available recruiters: ' . $e->getMessage());
        }
    }

    /**
     * Balancear la carga de trabajo entre recruiters
     * 
     * Identifica recruiters sobrecargados y redistribuye candidatos
     * a recruiters con menor carga dentro del mismo departamento.
     * 
     * @param int|null $departmentId Balancear solo un departamento especí­fico
     * @return array Resultado del balanceo con estadí­sticas
     */
    public function balanceRecruiterWorkload(?int $departmentId = null): array
    {
        try {
            $this->db->beginTransaction();

            // Obtener carga actual de todos los recruiters
            $recruiters = $this->getRecruiterWorkloadForBalancing($departmentId);

            if (empty($recruiters)) {
                $this->db->rollBack();
                return [
                    'success' => true,
                    'message' => 'No recruiters found for balancing',
                    'redistributed' => 0,
                    'recruiters_affected' => 0
                ];
            }

            $totalRedistributed = 0;
            $recruitersAffected = [];

            // Identificar recruiters sobrecargados y con capacidad
            $overloaded = array_filter($recruiters, fn($r) => $r['active_candidates'] > $r['max_candidates']);
            $available = array_filter($recruiters, fn($r) => $r['active_candidates'] < $r['max_candidates']);

            foreach ($overloaded as $overloadedRecruiter) {
                $excess = $overloadedRecruiter['active_candidates'] - $overloadedRecruiter['max_candidates'];

                // Buscar candidatos  más recientes para redistribuir
                $candidatesToMove = $this->getRecentCandidatesForRedistribution(
                    $overloadedRecruiter['recruiter_id'],
                    $excess
                );

                foreach ($candidatesToMove as $candidate) {
                    // Encontrar el recruiter con menor carga en el mismo departamento
                    $targetRecruiter = $this->findBestTargetRecruiter(
                        $available,
                        $overloadedRecruiter['department_id']
                    );

                    if ($targetRecruiter) {
                        // Reasignar candidato
                        $this->reassignCandidate(
                            $candidate['candidate_id'],
                            $overloadedRecruiter['recruiter_id'],
                            $targetRecruiter['recruiter_id']
                        );

                        $totalRedistributed++;
                        $recruitersAffected[] = $overloadedRecruiter['recruiter_id'];
                        $recruitersAffected[] = $targetRecruiter['recruiter_id'];

                        // Actualizar capacidad disponible
                        foreach ($available as &$availableRecruiter) {
                            if ($availableRecruiter['recruiter_id'] === $targetRecruiter['recruiter_id']) {
                                $availableRecruiter['active_candidates']++;
                                break;
                            }
                        }
                    }
                }
            }

            $this->db->commit();

            // Log del resultado
            Logger::info('Recruiter workload balanced', [
                'department_id' => $departmentId,
                'redistributed' => $totalRedistributed,
                'recruiters_affected' => array_unique($recruitersAffected)
            ]);

            return [
                'success' => true,
                'message' => "Successfully redistributed $totalRedistributed candidates",
                'redistributed' => $totalRedistributed,
                'recruiters_affected' => count(array_unique($recruitersAffected))
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logError('Error balancing recruiter workload', ['department_id' => $departmentId], $e);
            throw new \RuntimeException('Failed to balance recruiter workload: ' . $e->getMessage());
        }
    }

    /**
     * Obtener recruiter con menor carga para asignación automí¡tica
     * 
     * @param int|null $departmentId Filtrar por departamento
     * @param array $specializations Filtrar por especializaciones requeridas
     * @return array|null Datos del recruiter con menor carga
     */
    public function getRecruiterWithLowestLoad(?int $departmentId = null, array $specializations = []): ?array
    {
        $availableRecruiters = $this->getAvailableRecruiters($departmentId, $specializations);

        if (empty($availableRecruiters)) {
            return null;
        }

        // Retornar el recruiter con menor carga actual
        return $availableRecruiters[array_key_last($availableRecruiters)];
    }

    /**
     * Asignar candidato a un recruiter especí­fico
     * 
     * @param int $candidateId ID del candidato
     * @param int $recruiterId ID del recruiter
     * @return bool Resultado de la asignación
     */
    public function assignCandidateToRecruiter(int $candidateId, int $recruiterId): bool
    {
        if ($candidateId <= 0 || $recruiterId <= 0) {
            throw new \InvalidArgumentException('Candidate ID and Recruiter ID must be positive');
        }

        // Verificar que el recruiter tenga capacidad disponible
        $recruiterLoad = $this->getRecruiterLoad($recruiterId);
        if (!$recruiterLoad) {
            throw new \RuntimeException('Recruiter not found or inactive');
        }

        $recruiter = $this->findById($recruiterId);
        if (!$recruiter || !$recruiter['active']) {
            throw new \RuntimeException('Recruiter not found or inactive');
        }

        $maxCandidates = $recruiter['max_candidates'] ?? self::DEFAULT_MAX_CANDIDATES;
        if ($recruiterLoad['active_candidates'] >= $maxCandidates) {
            throw new \RuntimeException('Recruiter has reached maximum capacity');
        }

        try {
            $sql = "INSERT INTO bt_candidate_routing (candidate_id, recruiter_id, assigned_at, status) 
                    VALUES (:candidate_id, :recruiter_id, NOW(), 'assigned')
                    ON DUPLICATE KEY UPDATE 
                    recruiter_id = :recruiter_id, assigned_at = NOW(), status = 'assigned'";

            $this->query($sql, [
                ':candidate_id' => $candidateId,
                ':recruiter_id' => $recruiterId
            ]);

            Logger::info('Candidate assigned to recruiter', [
                'candidate_id' => $candidateId,
                'recruiter_id' => $recruiterId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logError('Error assigning candidate to recruiter', [
                'candidate_id' => $candidateId,
                'recruiter_id' => $recruiterId
            ], $e);
            throw new \RuntimeException('Failed to assign candidate to recruiter: ' . $e->getMessage());
        }
    }

    /**
     * Método auxiliar para ejecutar la query de estadí­sticas del recruiter
     */
    private function executeRecruiterStatsQuery(int $recruiterId): array
    {
        $sql = "SELECT 
                    sp.id AS recruiter_id,
                    COUNT(DISTINCT cr.candidate_id) AS total_candidates,
                    COUNT(DISTINCT CASE WHEN cr.status = 'assigned' THEN cr.candidate_id END) AS active_candidates,
                    COUNT(DISTINCT i.id) AS completed_interviews,
                    COUNT(DISTINCT CASE WHEN a.status = 'hired' THEN a.candidate_id END) AS successful_hires,
                    AVG(DATEDIFF(COALESCE(a.updated_at, NOW()), cr.assigned_at)) AS avg_processing_time_days,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN a.status = 'hired' THEN a.candidate_id END) * 100.0 / 
                         NULLIF(COUNT(DISTINCT cr.candidate_id), 0)), 2
                    ) AS efficiency_score
                FROM bt_staff_profiles sp
                LEFT JOIN bt_candidate_routing cr ON cr.recruiter_id = sp.id
                LEFT JOIN bt_applications a ON a.candidate_id = cr.candidate_id
                LEFT JOIN bt_interviews i ON i.application_id = a.id AND i.status = 'completed'
                WHERE sp.id = :recruiter_id
                AND sp.role = 'recruiter'
                GROUP BY sp.id";

        $result = $this->query($sql, [':recruiter_id' => $recruiterId]);

        if (empty($result)) {
            return [
                'recruiter_id' => $recruiterId,
                'total_candidates' => 0,
                'active_candidates' => 0,
                'completed_interviews' => 0,
                'successful_hires' => 0,
                'avg_processing_time_days' => 0,
                'efficiency_score' => 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }

        $stats = $result[0];
        $stats['last_updated'] = date('Y-m-d H:i:s');

        return $stats;
    }

    /**
     * Obtener carga de trabajo de recruiters para balanceo
     */
    private function getRecruiterWorkloadForBalancing(?int $departmentId): array
    {
        $sql = "SELECT r.recruiter_id, r.name, r.department_id, r.active_candidates,
                       sp.max_candidates
                FROM vw_recruiter_load r
                RIGHT JOIN bt_staff_profiles sp ON sp.id = r.recruiter_id
                WHERE sp.active = 1 AND sp.role = 'recruiter'";

        $params = [];

        if ($departmentId !== null) {
            $sql .= " AND r.department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }

        $sql .= " ORDER BY r.active_candidates DESC";

        return $this->query($sql, $params);
    }

    /**
     * Obtener candidatos recientes para redistribución
     */
    private function getRecentCandidatesForRedistribution(int $recruiterId, int $limit): array
    {
        $sql = "SELECT cr.candidate_id, cr.assigned_at
                FROM bt_candidate_routing cr
                WHERE cr.recruiter_id = :recruiter_id
                AND cr.status = 'assigned'
                ORDER BY cr.assigned_at DESC
                LIMIT :limit";

        return $this->query($sql, [
            ':recruiter_id' => $recruiterId,
            ':limit' => $limit
        ]);
    }

    /**
     * Encontrar el mejor recruiter objetivo para redistribución
     */
    private function findBestTargetRecruiter(array $availableRecruiters, int $departmentId): ?array
    {
        // Filtrar por departamento y ordenar por menor carga
        $sameDepart = array_filter(
            $availableRecruiters,
            fn($r) => $r['department_id'] == $departmentId
        );

        if (empty($sameDepart)) {
            return null;
        }

        // Ordenar por carga actual (menor primero)
        usort($sameDepart, fn($a, $b) => $a['active_candidates'] <=> $b['active_candidates']);

        return $sameDepart[0] ?? null;
    }

    /**
     * Reasignar candidato de un recruiter a otro
     */
    private function reassignCandidate(int $candidateId, int $fromRecruiterId, int $toRecruiterId): void
    {
        $sql = "UPDATE bt_candidate_routing 
                SET recruiter_id = :to_recruiter_id, 
                    assigned_at = NOW(),
                    status = 'reassigned'
                WHERE candidate_id = :candidate_id 
                AND recruiter_id = :from_recruiter_id";

        $this->query($sql, [
            ':candidate_id' => $candidateId,
            ':from_recruiter_id' => $fromRecruiterId,
            ':to_recruiter_id' => $toRecruiterId
        ]);

        // Log de la reasignación
        Logger::info('Candidate reassigned between recruiters', [
            'candidate_id' => $candidateId,
            'from_recruiter' => $fromRecruiterId,
            'to_recruiter' => $toRecruiterId
        ]);
    }

    /**
     * Mantener funcionalidad existente - encontrar por user_id si es necesario
     */
    public function findByUserId($userId): ?array
    {
        return $this->findOneBy('user_id', $userId);
    }

    /**
     * Obtener candidatos asignados a un recruiter
     */
    public function getAssignedCandidates(int $recruiterId, array $filters = []): array
    {
        $sql = "SELECT c.*, cr.assigned_at, cr.status as assignment_status
                FROM bt_candidates c
                INNER JOIN bt_candidate_routing cr ON cr.candidate_id = c.id
                WHERE cr.recruiter_id = :recruiter_id";

        $params = [':recruiter_id' => $recruiterId];

        // Aplicar filtros adicionales
        if (!empty($filters['status'])) {
            $sql .= " AND cr.status = :assignment_status";
            $params[':assignment_status'] = $filters['status'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND c.department_id = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }

        $sql .= " ORDER BY cr.assigned_at DESC";

        try {
            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting assigned candidates', [
                'recruiter_id' => $recruiterId,
                'filters' => $filters
            ], $e);
            throw new \RuntimeException('Failed to get assigned candidates: ' . $e->getMessage());
        }
    }

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo recruiter con validaciones
     * @param array $data Datos del nuevo recruiter
     * @return mixed ID del nuevo recruiter o false en caso de error
     */
    public function createRecruiter(array $data): mixed
    {
        try {
            $this->validateRecruiterData($data);
            // Filtrar solo los campos permitidos por $fillable
            $filtered = [];
            foreach ($this->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }
            $id = $this->store($filtered);
            $this->invalidateRecruiterCache();

            Logger::info('Recruiter created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating recruiter', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener recruiter por ID
     * @param mixed $id ID del recruiter
     * @return array|null Datos del recruiter o null si no existe
     */
    public function getRecruiter($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving recruiter', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar recruiter con validaciones
     * @param mixed $id ID del recruiter a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateRecruiter($id, array $data): bool
    {
        try {
            $this->validateRecruiterData($data, $id);
            // Filtrar solo los campos permitidos por $fillable
            $filtered = [];
            foreach ($this->fillable as $field) {
                if (array_key_exists($field, $data)) {
                    $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                }
            }
            $result = $this->update($id, $filtered);

            if ($result) {
                $this->invalidateRecruiterCache();
                Logger::info('Recruiter updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($filtered)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating recruiter', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar recruiter con validaciones
     * @param mixed $id ID del recruiter a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteRecruiter($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateRecruiterCache();
                Logger::info('Recruiter deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting recruiter', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar recruiters con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Pí¡gina actual
     * @param int $limit Registros por pí¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de recruiters
     */
    public function searchRecruiters(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching recruiters', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de recruiters con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de recruiters
     */
    public function countRecruiters(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting recruiters', [
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
     * Validar datos especí­ficos de recruiters
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son ví¡lidos
     */
    private function validateRecruiterData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones especí­ficas del modelo
    }

    /**
     * Invalidar cache especí­fico de recruiters
     */
    public function invalidateRecruiterCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['recruiters', 'recruiter_core', 'recruiter_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating recruiter cache', [], $e);
            return 0;
        }
    }
}
