<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para enrutamiento de candidatos
 *
 * Gestiona la asignación de candidatos a departamentos, categorías de departamento
 * y recruiters específicos para optimizar el proceso de reclutamiento.
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class CandidateRouting extends BaseModel
{
    /**
     * Tabla asociada al modelo
     */
    protected string $table = 'candidate_routing';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: CandidateRouting
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['source_channel', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_url']
     * ❌ Campos removidos: ['department_id', 'department_category_id', 'recruiter_id', 'assigned_at', 'status']
     * 📊 Total campos fillable: 6
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    

    /**
     * Clave primaria de la tabla
     */
    protected string $primaryKey = 'id';

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'candidate_id',
        'source_channel',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'referrer_url',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     */
    protected array $hidden = [];

    /**
     * Estados válidos para el enrutamiento
     */
    const VALID_STATUSES = [
        'pending',
        'assigned',
        'in_progress',
        'completed',
        'reassigned',
        'cancelled'
    ];

    /**
     * Encontrar el último enrutamiento de un candidato
     *
     * @param int $candidateId ID del candidato
     * @return array|null Último enrutamiento o null si no existe
     * @throws \InvalidArgumentException Si el candidateId es inválido
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function findLatestByCandidate(int $candidateId): ?array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `candidate_id` = ? ORDER BY `assigned_at` DESC LIMIT 1";
            $results = $this->query($sql, [$candidateId]);

            $result = $results[0] ?? null;

            if ($result) {
                $this->logDebug('Latest routing found for candidate', [
                    'candidate_id' => $candidateId,
                    'routing_id' => $result['id'],
                    'status' => $result['status']
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error retrieving latest candidate routing', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to retrieve latest candidate routing: ' . $e->getMessage());
        }
    }

    /**
     * Obtener pipeline optimizado por departamento usando vista especializada
     *
     * @param array $filters Filtros opcionales para departamentos
     * @return array Datos del pipeline por departamento
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function getPipelineDepartmentOptimized(array $filters = []): array
    {
        try {
            $sql = "SELECT * FROM vw_pipeline_department";
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

            $sql .= ' ORDER BY applications_count DESC, candidates_count DESC';

            $results = $this->query($sql, $params);

            $this->logDebug('Pipeline department data retrieved', [
                'filters' => $filters,
                'departments_count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error retrieving pipeline department data', [
                'filters' => $filters
            ], $e);
            throw new \RuntimeException('Failed to retrieve pipeline department data: ' . $e->getMessage());
        }
    }

    /**
     * Crear nuevo enrutamiento para un candidato
     *
     * @param int $candidateId ID del candidato
     * @param int $departmentId ID del departamento
     * @param int|null $departmentCategoryId ID de la categoría de departamento (opcional)
     * @param int|null $recruiterId ID del recruiter (opcional)
     * @param string $status Estado inicial (por defecto 'pending')
     * @return mixed ID del enrutamiento creado
     * @throws \InvalidArgumentException Si los datos son inválidos
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function createRouting(int $candidateId, int $departmentId, ?int $departmentCategoryId = null, ?int $recruiterId = null, string $status = 'pending')
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        if ($departmentId <= 0) {
            throw new \InvalidArgumentException('Department ID must be a positive integer');
        }

        if (!in_array($status, self::VALID_STATUSES)) {
            throw new \InvalidArgumentException('Invalid status. Must be one of: ' . implode(', ', self::VALID_STATUSES));
        }

        try {
            $data = [
                'candidate_id' => $candidateId,
                'department_id' => $departmentId,
                'department_category_id' => $departmentCategoryId,
                'recruiter_id' => $recruiterId,
                'assigned_at' => date('Y-m-d H:i:s'),
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $routingId = $this->store($data);

            Logger::info('Candidate routing created successfully', [
                'routing_id' => $routingId,
                'candidate_id' => $candidateId,
                'department_id' => $departmentId,
                'recruiter_id' => $recruiterId,
                'status' => $status
            ]);

            return $routingId;
        } catch (\Exception $e) {
            $this->logError('Error creating candidate routing', [
                'candidate_id' => $candidateId,
                'department_id' => $departmentId,
                'recruiter_id' => $recruiterId
            ], $e);
            throw new \RuntimeException('Failed to create candidate routing: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar el estado de un enrutamiento
     *
     * @param mixed $routingId ID del enrutamiento
     * @param string $newStatus Nuevo estado
     * @param int|null $newRecruiterId Nuevo recruiter (opcional)
     * @return bool True si la actualización fue exitosa
     * @throws \InvalidArgumentException Si los datos son inválidos
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function updateRoutingStatus($routingId, string $newStatus, ?int $newRecruiterId = null): bool
    {
        if (empty($routingId)) {
            throw new \InvalidArgumentException('Routing ID cannot be empty');
        }

        if (!in_array($newStatus, self::VALID_STATUSES)) {
            throw new \InvalidArgumentException('Invalid status. Must be one of: ' . implode(', ', self::VALID_STATUSES));
        }

        try {
            $data = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Si se proporciona nuevo recruiter, actualizar también
            if ($newRecruiterId !== null) {
                $data['recruiter_id'] = $newRecruiterId;
                $data['assigned_at'] = date('Y-m-d H:i:s'); // Actualizar tiempo de asignación
            }

            $result = $this->update($routingId, $data);

            Logger::info('Candidate routing status updated', [
                'routing_id' => $routingId,
                'new_status' => $newStatus,
                'new_recruiter_id' => $newRecruiterId
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating routing status', [
                'routing_id' => $routingId,
                'new_status' => $newStatus
            ], $e);
            throw new \RuntimeException('Failed to update routing status: ' . $e->getMessage());
        }
    }

    /**
     * Reasignar candidato a nuevo recruiter
     *
     * @param int $candidateId ID del candidato
     * @param int $newRecruiterId ID del nuevo recruiter
     * @param string $reason Razón de la reasignación (opcional)
     * @return bool True si la reasignación fue exitosa
     * @throws \InvalidArgumentException Si los datos son inválidos
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function reassignCandidate(int $candidateId, int $newRecruiterId, string $reason = ''): bool
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        if ($newRecruiterId <= 0) {
            throw new \InvalidArgumentException('Recruiter ID must be a positive integer');
        }

        try {
            // Obtener el enrutamiento actual
            $currentRouting = $this->findLatestByCandidate($candidateId);

            if (!$currentRouting) {
                throw new \RuntimeException('No routing found for candidate');
            }

            // Actualizar el enrutamiento actual
            $result = $this->updateRoutingStatus($currentRouting['id'], 'reassigned', $newRecruiterId);

            Logger::info('Candidate reassigned successfully', [
                'candidate_id' => $candidateId,
                'previous_recruiter' => $currentRouting['recruiter_id'],
                'new_recruiter' => $newRecruiterId,
                'reason' => $reason
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error reassigning candidate', [
                'candidate_id' => $candidateId,
                'new_recruiter_id' => $newRecruiterId,
                'reason' => $reason
            ], $e);
            throw new \RuntimeException('Failed to reassign candidate: ' . $e->getMessage());
        }
    }

    /**
     * Obtener candidatos asignados a un recruiter específico
     *
     * @param int $recruiterId ID del recruiter
     * @param array $statusFilter Filtro de estados (opcional)
     * @param int $limit Límite de resultados (por defecto 50)
     * @return array Lista de candidatos asignados
     * @throws \InvalidArgumentException Si los datos son inválidos
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function getCandidatesByRecruiter(int $recruiterId, array $statusFilter = [], int $limit = 50): array
    {
        if ($recruiterId <= 0) {
            throw new \InvalidArgumentException('Recruiter ID must be a positive integer');
        }

        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Limit must be between 1 and 500');
        }

        try {
            $sql = "SELECT cr.*, c.name as candidate_name, c.email as candidate_email, 
                           d.name as department_name, dc.name as department_category_name
                    FROM `{$this->table}` cr
                    LEFT JOIN bt_candidates c ON c.id = cr.candidate_id
                    LEFT JOIN bt_departments d ON d.id = cr.department_id
                    LEFT JOIN bt_department_categories dc ON dc.id = cr.department_category_id
                    WHERE cr.recruiter_id = ?";

            $params = [$recruiterId];

            // Agregar filtro de estados si se proporciona
            if (!empty($statusFilter)) {
                $statusPlaceholders = str_repeat('?,', count($statusFilter) - 1) . '?';
                $sql .= " AND cr.status IN ($statusPlaceholders)";
                $params = array_merge($params, $statusFilter);
            }

            $sql .= " ORDER BY cr.assigned_at DESC LIMIT ?";
            $params[] = $limit;

            $results = $this->query($sql, $params);

            $this->logDebug('Candidates retrieved for recruiter', [
                'recruiter_id' => $recruiterId,
                'status_filter' => $statusFilter,
                'count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error retrieving candidates by recruiter', [
                'recruiter_id' => $recruiterId,
                'status_filter' => $statusFilter
            ], $e);
            throw new \RuntimeException('Failed to retrieve candidates by recruiter: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadísticas de carga de trabajo por recruiter usando vista optimizada
     *
     * @param int|null $departmentId Filtro por departamento (opcional)
     * @return array Estadísticas de carga de trabajo
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function getRecruiterWorkloadStats(?int $departmentId = null): array
    {
        try {
            $sql = "SELECT * FROM vw_recruiter_load";
            $params = [];

            if ($departmentId !== null) {
                $sql .= " WHERE department_id = ?";
                $params[] = $departmentId;
            }

            $sql .= " ORDER BY active_candidates DESC";

            $results = $this->query($sql, $params);

            $this->logDebug('Recruiter workload stats retrieved', [
                'department_id' => $departmentId,
                'recruiters_count' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error retrieving recruiter workload stats', [
                'department_id' => $departmentId
            ], $e);
            throw new \RuntimeException('Failed to retrieve recruiter workload stats: ' . $e->getMessage());
        }
    }

    /**
     * Obtener historial completo de enrutamientos de un candidato
     *
     * @param int $candidateId ID del candidato
     * @return array Historial de enrutamientos ordenado cronológicamente
     * @throws \InvalidArgumentException Si el candidateId es inválido
     * @throws \RuntimeException Si ocurre un error en la base de datos
     */
    public function getRoutingHistory(int $candidateId): array
    {
        if ($candidateId <= 0) {
            throw new \InvalidArgumentException('Candidate ID must be a positive integer');
        }

        try {
            $sql = "SELECT cr.*, d.name as department_name, dc.name as department_category_name,
                           sp.name as recruiter_name
                    FROM `{$this->table}` cr
                    LEFT JOIN bt_departments d ON d.id = cr.department_id
                    LEFT JOIN bt_department_categories dc ON dc.id = cr.department_category_id
                    LEFT JOIN bt_staff_profiles sp ON sp.id = cr.recruiter_id
                    WHERE cr.candidate_id = ?
                    ORDER BY cr.assigned_at DESC";

            $results = $this->query($sql, [$candidateId]);

            $this->logDebug('Routing history retrieved for candidate', [
                'candidate_id' => $candidateId,
                'history_entries' => count($results)
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logError('Error retrieving routing history', [
                'candidate_id' => $candidateId
            ], $e);
            throw new \RuntimeException('Failed to retrieve routing history: ' . $e->getMessage());
        }
    }

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo candidate_routing con validaciones
     * @param array $data Datos del nuevo candidate_routing
     * @return mixed ID del nuevo candidate_routing o false en caso de error
     */
    public function createCandidateRouting(array $data): mixed
    {
        try {
            $this->validateCandidateRoutingData($data);
            $id = $this->store($data);
            $this->invalidateCandidateRoutingCache();

            Logger::info('CandidateRouting created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating candidate_routing', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener candidate_routing por ID
     * @param mixed $id ID del candidate_routing
     * @return array|null Datos del candidate_routing o null si no existe
     */
    public function getCandidateRouting($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving candidate_routing', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar candidate_routing con validaciones
     * @param mixed $id ID del candidate_routing a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateCandidateRouting($id, array $data): bool
    {
        try {
            $this->validateCandidateRoutingData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateCandidateRoutingCache();
                Logger::info('CandidateRouting updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating candidate_routing', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar candidate_routing con validaciones
     * @param mixed $id ID del candidate_routing a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteCandidateRouting($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateCandidateRoutingCache();
                Logger::info('CandidateRouting deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting candidate_routing', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar candidate_routings con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de candidate_routings
     */
    public function searchCandidateRoutings(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching candidate_routings', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de candidate_routings con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de candidate_routings
     */
    public function countCandidateRoutings(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting candidate_routings', [
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
     * Validar datos específicos de candidate_routings
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    private function validateCandidateRoutingData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones específicas del modelo
    }

    /**
     * Invalidar cache específico de candidate_routings
     */
    public function invalidateCandidateRoutingCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['candidate_routings', 'candidate_routing_core', 'candidate_routing_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating candidate_routing cache', [], $e);
            return 0;
        }
    }
}
