<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para los departamentos de la organización
 * 
 * Gestiona la información de departamentos, sus estadísticas de reclutamiento,
 * pipeline de candidatos y métricas de rendimiento organizacional.
 * 
 * Funcionalidades principales:
 * - Gestión de departamentos con información básica y presupuestaria
 * - Pipeline de reclutamiento por departamento usando vistas optimizadas
 * - Estadísticas de candidatos y aplicaciones por departamento
 * - Carga de trabajo de recruiters por departamento
 * - Cache de consultas pesadas para optimizar rendimiento
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class Department extends BaseModel
{
    /**
     * Tabla de la base de datos
     */
    protected string $table = 'departments';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: Department
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['description', 'head_id', 'status', 'budget', 'location']
     * 📊 Total campos fillable: 1
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'name',
    ];

    /**
     * Campos ocultos por seguridad (información sensible)
     */
    protected array $hidden = [
        'budget'  // Información financiera sensible
    ];

    /**
     * Cache TTL por defecto para consultas de pipeline (5 minutos)
     */
    private const PIPELINE_CACHE_TTL = 300;

    /**
     * MÉTODOS DE VISTAS - Pipeline y estadísticas departamentales
     */

    /**
     * Obtener pipeline de reclutamiento por departamento
     * 
     * Utiliza la vista vw_pipeline_department para obtener estadísticas
     * completas del pipeline de reclutamiento por departamento.
     * 
     * @param string|null $departmentId ID específico del departamento (opcional)
     * @param bool $useCache Usar cache para optimizar consultas pesadas
     * @param int $cacheTtl Tiempo de vida del cache en segundos
     * @return array Datos del pipeline por departamento
     * 
     * @throws \InvalidArgumentException Si department_id no es válido
     * @throws \RuntimeException Si falla la consulta
     */
    public function getDepartmentPipeline(?string $departmentId = null, bool $useCache = true, int $cacheTtl = self::PIPELINE_CACHE_TTL): array
    {
        $sql = "SELECT 
                    department_id,
                    department_name,
                    department_category_id, 
                    department_category_name,
                    recruiter_id,
                    applications_count,
                    candidates_count
                FROM vw_pipeline_department";

        $params = [];

        if ($departmentId !== null) {
            if (empty($departmentId)) {
                throw new \InvalidArgumentException('Department ID cannot be empty');
            }
            $sql .= " WHERE department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }

        $sql .= " ORDER BY department_name, recruiter_id";

        try {
            // Usar cache si está habilitado
            if ($useCache && $cacheTtl > 0) {
                $cacheKey = $this->generateCacheKey('department_pipeline', [
                    'department_id' => $departmentId
                ]);

                return $this->getCachedQuery($cacheKey, $sql, $params, $cacheTtl);
            }

            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting department pipeline', [
                'department_id' => $departmentId,
                'use_cache' => $useCache
            ], $e);
            throw new \RuntimeException('Failed to get department pipeline: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadísticas de rendimiento por departamento
     * 
     * Calcula métricas agregadas de rendimiento departamental incluyendo
     * totales de candidatos, aplicaciones, tasas de conversión y promedios.
     * 
     * @param string $departmentId ID del departamento
     * @param bool $useCache Usar cache para optimizar consultas
     * @return array Métricas de rendimiento del departamento
     */
    public function getDepartmentStats(string $departmentId, bool $useCache = true): array
    {
        if (empty($departmentId)) {
            throw new \InvalidArgumentException('Department ID cannot be empty');
        }

        $sql = "SELECT 
                    d.id,
                    d.name as department_name,
                    d.description,
                    d.status,
                    d.location,
                    COUNT(DISTINCT c.id) as total_candidates,
                    COUNT(DISTINCT a.id) as total_applications,
                    COUNT(DISTINCT CASE WHEN a.status = 'interview' THEN a.id END) as applications_in_interview,
                    COUNT(DISTINCT CASE WHEN a.status = 'accepted' THEN a.id END) as applications_accepted,
                    COUNT(DISTINCT CASE WHEN a.status = 'rejected' THEN a.id END) as applications_rejected,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN a.status = 'interview' THEN a.id END) * 100.0 / 
                         NULLIF(COUNT(DISTINCT a.id), 0)), 2
                    ) as interview_rate,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN a.status = 'accepted' THEN a.id END) * 100.0 / 
                         NULLIF(COUNT(DISTINCT a.id), 0)), 2
                    ) as acceptance_rate,
                    COALESCE(AVG(a.score), 0) as avg_application_score
                FROM bt_departments d
                LEFT JOIN bt_candidates c ON c.department_id = d.id
                LEFT JOIN bt_applications a ON a.candidate_id = c.id
                WHERE d.id = :department_id
                GROUP BY d.id, d.name, d.description, d.status, d.location";

        try {
            if ($useCache) {
                $cacheKey = $this->generateCacheKey('department_stats', ['department_id' => $departmentId]);
                $result = $this->getCachedQuery($cacheKey, $sql, [':department_id' => $departmentId], self::PIPELINE_CACHE_TTL);
            } else {
                $result = $this->query($sql, [':department_id' => $departmentId]);
            }

            return $result[0] ?? [];
        } catch (\Exception $e) {
            $this->logError('Error getting department stats', ['department_id' => $departmentId], $e);
            throw new \RuntimeException('Failed to get department stats: ' . $e->getMessage());
        }
    }

    /**
     * Obtener departamentos con carga de trabajo optimizada
     * 
     * Utiliza la vista vw_pipeline_department junto con vw_recruiter_load
     * para proporcionar una vista completa de la carga de trabajo por departamento.
     * 
     * @param array $filters Filtros opcionales por estado, ubicación, etc.
     * @param bool $useCache Usar cache para consultas optimizadas
     * @return array Lista de departamentos con métricas de carga
     */
    public function getDepartmentsWithLoad(array $filters = [], bool $useCache = true): array
    {
        $sql = "SELECT 
                    d.id,
                    d.name,
                    d.description,
                    d.status,
                    d.location,
                    COALESCE(p.candidates_count, 0) as candidates_count,
                    COALESCE(p.applications_count, 0) as applications_count,
                    COALESCE(rl.active_candidates, 0) as recruiter_active_load,
                    CASE 
                        WHEN COALESCE(rl.active_candidates, 0) = 0 THEN 'Low'
                        WHEN COALESCE(rl.active_candidates, 0) <= 10 THEN 'Medium'
                        WHEN COALESCE(rl.active_candidates, 0) <= 25 THEN 'High'
                        ELSE 'Critical'
                    END as load_status
                FROM bt_departments d
                LEFT JOIN (
                    SELECT 
                        department_id,
                        SUM(candidates_count) as candidates_count,
                        SUM(applications_count) as applications_count
                    FROM vw_pipeline_department 
                    GROUP BY department_id
                ) p ON p.department_id = d.id
                LEFT JOIN (
                    SELECT 
                        department_id,
                        SUM(active_candidates) as active_candidates
                    FROM vw_recruiter_load
                    GROUP BY department_id
                ) rl ON rl.department_id = d.id";

        $params = [];

        // Aplicar filtros
        if (!empty($filters)) {
            $whereConditions = [];
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $whereConditions[] = "d.`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }
        }

        $sql .= " ORDER BY d.name";

        try {
            if ($useCache) {
                $cacheKey = $this->generateCacheKey('departments_with_load', $filters);
                return $this->getCachedQuery($cacheKey, $sql, $params, self::PIPELINE_CACHE_TTL);
            }

            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting departments with load', ['filters' => $filters], $e);
            throw new \RuntimeException('Failed to get departments with load: ' . $e->getMessage());
        }
    }

    /**
     * Obtener candidatos por departamento con filtros avanzados
     * 
     * Recupera candidatos asignados a un departamento específico con
     * capacidad de filtrado por estado, ubicación, habilidades, etc.
     * 
     * @param string $departmentId ID del departamento
     * @param array $filters Filtros adicionales (status, location, skills, etc.)
     * @param int $page Página para paginación
     * @param int $limit Límite de resultados por página
     * @return array Lista paginada de candidatos del departamento
     */
    public function getCandidatesByDepartment(
        string $departmentId,
        array $filters = [],
        int $page = 1,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        if (empty($departmentId)) {
            throw new \InvalidArgumentException('Department ID cannot be empty');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $sql = "SELECT 
                    c.*,
                    d.name as department_name,
                    dc.name as department_category_name,
                    COUNT(DISTINCT a.id) as applications_count,
                    COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_applications,
                    GROUP_CONCAT(DISTINCT s.skill_name SEPARATOR ', ') as skills_text
                FROM bt_candidates c
                LEFT JOIN bt_departments d ON d.id = c.department_id
                LEFT JOIN bt_department_categories dc ON dc.id = c.department_category_id
                LEFT JOIN bt_applications a ON a.candidate_id = c.id
                LEFT JOIN bt_candidate_skill_map sm ON sm.candidate_id = c.id
                LEFT JOIN bt_skills s ON s.id = sm.skill_id
                WHERE c.department_id = :department_id";

        $params = [':department_id' => $departmentId];

        // Aplicar filtros adicionales
        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                if ($value !== null && $this->isValidFieldName($field)) {
                    $sql .= " AND c.`$field` = :filter_$field";
                    $params[":filter_$field"] = $value;
                }
            }
        }

        $sql .= " GROUP BY c.id
                  ORDER BY c.created_at DESC";

        // Añadir paginación
        $offset = ($page - 1) * $limit;
        $sql .= ' LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            $candidates = $this->query($sql, $params);

            // Obtener total para paginación
            $totalQuery = "SELECT COUNT(DISTINCT c.id) as total 
                           FROM bt_candidates c 
                           WHERE c.department_id = :department_id";

            $totalParams = [':department_id' => $departmentId];

            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if ($value !== null && $this->isValidFieldName($field)) {
                        $totalQuery .= " AND c.`$field` = :filter_$field";
                        $totalParams[":filter_$field"] = $value;
                    }
                }
            }

            $totalResult = $this->query($totalQuery, $totalParams);
            $total = (int)($totalResult[0]['total'] ?? 0);
            $totalPages = (int) ceil($total / $limit);

            return [
                'data' => $this->hideFields($candidates),
                'pagination' => [
                    'current_page' => $page,
                    'total' => $total,
                    'per_page' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('Error getting candidates by department', [
                'department_id' => $departmentId,
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get candidates by department: ' . $e->getMessage());
        }
    }

    /**
     * MÉTODOS DE GESTIÓN ORGANIZACIONAL
     */

    /**
     * Obtener jerarquía de departamentos con sus subordinados
     * 
     * @param string|null $parentId ID del departamento padre (null para raíz)
     * @return array Árbol jerárquico de departamentos
     */
    public function getDepartmentHierarchy(?string $parentId = null): array
    {
        $sql = "SELECT 
                    d.*,
                    h.name as head_name,
                    h.email as head_email,
                    COUNT(DISTINCT c.id) as employees_count,
                    COUNT(DISTINCT sub.id) as subdepartments_count
                FROM bt_departments d
                LEFT JOIN bt_staff_profiles h ON h.id = d.head_id
                LEFT JOIN bt_candidates c ON c.department_id = d.id
                LEFT JOIN bt_departments sub ON sub.parent_id = d.id";

        $params = [];

        if ($parentId === null) {
            $sql .= " WHERE d.parent_id IS NULL";
        } else {
            $sql .= " WHERE d.parent_id = :parent_id";
            $params[':parent_id'] = $parentId;
        }

        $sql .= " GROUP BY d.id ORDER BY d.name";

        try {
            $departments = $this->query($sql, $params);

            // Recursivamente obtener subdepartamentos
            foreach ($departments as &$department) {
                $department['children'] = $this->getDepartmentHierarchy($department['id']);
            }

            return $this->hideFields($departments);
        } catch (\Exception $e) {
            $this->logError('Error getting department hierarchy', ['parent_id' => $parentId], $e);
            throw new \RuntimeException('Failed to get department hierarchy: ' . $e->getMessage());
        }
    }

    /**
     * Obtener resumen ejecutivo de todos los departamentos
     * 
     * @param bool $useCache Usar cache para optimizar la consulta
     * @return array Resumen con métricas clave de todos los departamentos
     */
    public function getDepartmentsSummary(bool $useCache = true): array
    {
        $sql = "SELECT 
                    COUNT(DISTINCT d.id) as total_departments,
                    COUNT(DISTINCT CASE WHEN d.status = 'active' THEN d.id END) as active_departments,
                    COUNT(DISTINCT c.id) as total_candidates,
                    COUNT(DISTINCT a.id) as total_applications,
                    COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_applications,
                    COUNT(DISTINCT i.id) as total_interviews,
                    ROUND(AVG(a.score), 2) as avg_application_score,
                    COUNT(DISTINCT sp.id) as total_recruiters
                FROM bt_departments d
                LEFT JOIN bt_candidates c ON c.department_id = d.id
                LEFT JOIN bt_applications a ON a.candidate_id = c.id
                LEFT JOIN bt_interviews i ON i.application_id = a.id
                LEFT JOIN bt_staff_profiles sp ON sp.department_id = d.id AND sp.role = 'recruiter'";

        try {
            if ($useCache) {
                $cacheKey = $this->generateCacheKey('departments_summary', []);
                $result = $this->getCachedQuery($cacheKey, $sql, [], self::PIPELINE_CACHE_TTL);
            } else {
                $result = $this->query($sql, []);
            }

            return $result[0] ?? [];
        } catch (\Exception $e) {
            $this->logError('Error getting departments summary', [], $e);
            throw new \RuntimeException('Failed to get departments summary: ' . $e->getMessage());
        }
    }

    /**
     * MÉTODOS AUXILIARES Y CACHE
     */

    /**
     * Ejecutar consulta con cache
     */
    private function getCachedQuery(string $cacheKey, string $sql, array $params, int $ttl): array
    {
        // Verificar si existe sistema de cache
        if (
            isset($this->cache[$cacheKey]) &&
            isset($this->cache[$cacheKey]['expires']) &&
            $this->cache[$cacheKey]['expires'] > time()
        ) {
            return $this->cache[$cacheKey]['data'];
        }

        // Ejecutar query y cachear resultado
        $result = $this->query($sql, $params);

        $this->cache[$cacheKey] = [
            'data' => $result,
            'expires' => time() + $ttl
        ];

        return $result;
    }

    /**
     * Limpiar cache de departamentos
     */
    public function clearDepartmentCache(): void
    {
        foreach ($this->cache as $key => $value) {
            if (str_starts_with($key, 'department_') || str_starts_with($key, 'departments_')) {
                unset($this->cache[$key]);
            }
        }

        Logger::info('Department cache cleared', [
            'model' => static::class
        ]);
    }

    /**
     * MÉTODOS DE COMPATIBILIDAD
     * Mantener funcionalidad específica existente del modelo original
     */

    /**
     * Obtener departamento por nombre
     */
    public function findByName(string $name): ?array
    {
        return $this->findOneBy('name', $name);
    }

    /**
     * Obtener departamentos activos
     */
    public function getActiveDepartments(): array
    {
        return $this->findBy('status', 'active');
    }

    /**
     * Verificar si un departamento tiene candidatos asignados
     */
    public function hasCandidates(string $departmentId): bool
    {
        if (empty($departmentId)) {
            return false;
        }

        try {
            $sql = "SELECT COUNT(*) as count FROM bt_candidates WHERE department_id = :department_id";
            $result = $this->query($sql, [':department_id' => $departmentId]);
            return (int)($result[0]['count'] ?? 0) > 0;
        } catch (\Exception $e) {
            $this->logError('Error checking if department has candidates', [
                'department_id' => $departmentId
            ], $e);
            return false;
        }
    }

    /**
     * Obtener el jefe de departamento
     */
    public function getDepartmentHead(string $departmentId): ?array
    {
        if (empty($departmentId)) {
            throw new \InvalidArgumentException('Department ID cannot be empty');
        }

        $sql = "SELECT sp.* 
                FROM bt_departments d
                JOIN bt_staff_profiles sp ON sp.id = d.head_id
                WHERE d.id = :department_id";

        try {
            $result = $this->query($sql, [':department_id' => $departmentId]);
            return $result[0] ?? null;
        } catch (\Exception $e) {
            $this->logError('Error getting department head', [
                'department_id' => $departmentId
            ], $e);
            throw new \RuntimeException('Failed to get department head: ' . $e->getMessage());
        }
    }

    // ==========================================
    // MÉTODOS CRUD ENCAPSULADOS ESTÁNDAR
    // ==========================================

    /**
     * Crear nuevo department con validaciones
     * @param array $data Datos del nuevo department
     * @return mixed ID del nuevo department o false en caso de error
     */
    public function createDepartment(array $data): mixed
    {
        try {
            $this->validateDepartmentData($data);
            // Filtrar solo los campos permitidos
            $filtered = array_intersect_key($data, array_flip($this->fillable));
            $id = $this->store($filtered);
            $this->invalidateDepartmentCache();
            $this->logDebug('Department created successfully', [
                'model' => static::class,
                'id' => $id
            ]);
            return $id;
        } catch (\Exception $e) {
            $this->logError('Error creating department', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Obtener department por ID
     * @param mixed $id ID del department
     * @return array|null Datos del department o null si no existe
     */
    public function getDepartment($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            $this->logError('Error retrieving department', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
            return null;
        }
    }

    /**
     * Actualizar department con validaciones
     * @param mixed $id ID del department a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualización fue exitosa
     */
    public function updateDepartment($id, array $data): bool
    {
        try {
            $this->validateDepartmentData($data, $id);
            // Filtrar solo los campos permitidos
            $filtered = array_intersect_key($data, array_flip($this->fillable));
            $result = $this->update($id, $filtered);
            if ($result) {
                $this->invalidateDepartmentCache();
                $this->logDebug('Department updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($filtered)
                ]);
            }
            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating department', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Eliminar department con validaciones
     * @param mixed $id ID del department a eliminar
     * @return bool True si la eliminación fue exitosa
     */
    public function deleteDepartment($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateDepartmentCache();
                $this->logDebug('Department deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting department', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Buscar departments con filtros
     * @param array $filters Filtros de búsqueda
     * @param int $page Página actual
     * @param int $limit Registros por página
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de departments
     */
    public function searchDepartments(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            $this->logError('Error searching departments', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
            return [];
        }
    }

    /**
     * Contar total de departments con filtros
     * @param array $filters Filtros de búsqueda
     * @return int Número total de departments
     */
    public function countDepartments(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            $this->logError('Error counting departments', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
            return 0;
        }
    }

    // ==========================================
    // MÉTODOS DE VALIDACIÓN ESPECÍFICOS
    // ==========================================

    /**
     * Validar datos específicos de departments
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualización (opcional)
     * @throws \InvalidArgumentException Si los datos no son válidos
     */
    private function validateDepartmentData(array $data, $id = null): void
    {
        // Validar nombre requerido
        if (isset($data['name']) && empty(trim($data['name']))) {
            throw new \InvalidArgumentException('Department name is required and cannot be empty');
        }

        // Validar longitud del nombre
        if (isset($data['name']) && strlen($data['name']) > 255) {
            throw new \InvalidArgumentException('Department name cannot exceed 255 characters');
        }

        // Validar que el nombre no esté duplicado (si es creación o actualización con nombre diferente)
        if (isset($data['name'])) {
            $existing = $this->findBy('name', $data['name']);
            if (!empty($existing)) {
                // Si es actualización, verificar que no sea el mismo registro
                if ($id === null || $existing[0]['id'] != $id) {
                    throw new \InvalidArgumentException('Department name already exists');
                }
            }
        }
    }

    /**
     * Invalidar cache específico de departments
     */
    public function invalidateDepartmentCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['departments', 'department_core', 'department_list', 'pipeline']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating department cache', [], $e);
            return 0;
        }
    }
}
