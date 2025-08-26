<?php declare(strict_types=1);
namespace Models;

use Utils\Logger;

/**
 * Modelo para las categorÃƒÆ’Ã‚Â­as dentro de cada departamento con soporte jerÃƒÆ’Ã‚Â¡rquico
 *
 * Proporciona funcionalidad completa para manejar categorÃƒÆ’Ã‚Â­as de departamentos
 * con estructura jerÃƒÆ’Ã‚Â¡rquica, cache optimizado y validaciones.
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class DepartmentCategory extends BaseModel
{
    protected string $table = 'department_categories';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÃƒÆ’Ã¢â‚¬Å“N AUTOMÃƒÆ’Ã‚ÂTICA APLICADA
     * Modelo: DepartmentCategory
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aÃƒÆ’Ã‚Â±adidos: ['department_id']
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['description', 'parent_id', 'sort_order', 'status']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 2
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    protected array $fillable = [
        'name',
        'department_id',
    ];

    protected array $hidden = [];

    /**
     * Cache TTL para jerarquÃƒÆ’Ã‚Â­as de categorÃƒÆ’Ã‚Â­as en segundos
     */
    private const HIERARCHY_CACHE_TTL = 3600; // 1 hora

    /**
     * Cache TTL para categorÃƒÆ’Ã‚Â­as con departamentos en segundos
     */
    private const DEPARTMENT_CACHE_TTL = 1800; // 30 minutos

    /**
     * Estados vÃƒÆ’Ã‚Â¡lidos para categorÃƒÆ’Ã‚Â­as
     */
    private const VALID_STATUSES = ['active', 'inactive', 'draft'];

    /**
     * Devuelve las categorÃƒÆ’Ã‚Â­as de un departamento concreto (mÃƒÆ’Ã‚Â©todo original)
     *
     * @param int $departmentId ID del departamento
     * @return array Lista de categorÃƒÆ’Ã‚Â­as
     */
    public function findByDepartmentId(int $departmentId): array
    {
        if ($departmentId <= 0) {
            throw new \InvalidArgumentException('Department ID must be positive');
        }

        try {
            return $this->findAll(['department_id' => $departmentId], 1, self::MAX_LIMIT);
        } catch (\Exception $e) {
            $this->logError('Error finding categories by department', [
                'department_id' => $departmentId
            ], $e);
            throw new \RuntimeException('Failed to find categories by department: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene la estructura jerÃƒÆ’Ã‚Â¡rquica completa de categorÃƒÆ’Ã‚Â­as
     *
     * Construye un ÃƒÆ’Ã‚Â¡rbol jerÃƒÆ’Ã‚Â¡rquico de todas las categorÃƒÆ’Ã‚Â­as activas
     * con soporte de cache para optimizar rendimiento.
     *
     * @param bool $includeInactive Incluir categorÃƒÆ’Ã‚Â­as inactivas
     * @param int $cacheTtl TTL del cache en segundos
     * @return array Estructura jerÃƒÆ’Ã‚Â¡rquica de categorÃƒÆ’Ã‚Â­as
     */
    public function getCategoriesHierarchy(bool $includeInactive = false, int $cacheTtl = self::HIERARCHY_CACHE_TTL): array
    {
        $cacheKey = $this->generateCacheKey('categories_hierarchy', [
            'include_inactive' => $includeInactive
        ]);

        try {
            // Intentar obtener desde cache si estÃƒÆ’Ã‚Â¡ habilitado
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($includeInactive) {
                    return $this->buildCategoriesHierarchy($includeInactive);
                });
            }

            // Fallback sin cache
            return $this->buildCategoriesHierarchy($includeInactive);
        } catch (\Exception $e) {
            $this->logError('Error getting categories hierarchy', [
                'include_inactive' => $includeInactive
            ], $e);
            return [];
        }
    }

    /**
     * Obtiene una categorÃƒÆ’Ã‚Â­a con sus departamentos relacionados
     *
     * Incluye informaciÃƒÆ’Ã‚Â³n completa de la categorÃƒÆ’Ã‚Â­a junto con
     * todos los departamentos que pertenecen a ella.
     *
     * @param int $categoryId ID de la categorÃƒÆ’Ã‚Â­a
     * @param int $cacheTtl TTL del cache en segundos
     * @return array|null CategorÃƒÆ’Ã‚Â­a con departamentos o null si no existe
     */
    public function getCategoryWithDepartments(int $categoryId, int $cacheTtl = self::DEPARTMENT_CACHE_TTL): ?array
    {
        if ($categoryId <= 0) {
            throw new \InvalidArgumentException('Category ID must be positive');
        }

        $cacheKey = $this->generateCacheKey('category_with_departments', ['id' => $categoryId]);

        try {
            if ($cacheTtl > 0 && class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () use ($categoryId) {
                    return $this->executeCategoryWithDepartmentsQuery($categoryId);
                });
            }

            return $this->executeCategoryWithDepartmentsQuery($categoryId);
        } catch (\Exception $e) {
            $this->logError('Error getting category with departments', [
                'category_id' => $categoryId
            ], $e);
            return null;
        }
    }

    /**
     * Obtiene las categorÃƒÆ’Ã‚Â­as de nivel superior (sin parent)
     *
     * Devuelve solo las categorÃƒÆ’Ã‚Â­as raÃƒÆ’Ã‚Â­z que no tienen categorÃƒÆ’Ã‚Â­a padre,
     * ordenadas por sort_order y nombre.
     *
     * @param bool $includeInactive Incluir categorÃƒÆ’Ã‚Â­as inactivas
     * @return array Lista de categorÃƒÆ’Ã‚Â­as principales
     */
    public function getTopLevelCategories(bool $includeInactive = false): array
    {
        try {
            $filters = ['parent_id' => null];
            if (!$includeInactive) {
                $filters['status'] = 'active';
            }

            $orderBy = [
                'sort_order' => 'ASC',
                'name' => 'ASC'
            ];

            return $this->findAll($filters, 1, self::MAX_LIMIT, $orderBy);
        } catch (\Exception $e) {
            $this->logError('Error getting top level categories', [
                'include_inactive' => $includeInactive
            ], $e);
            throw new \RuntimeException('Failed to get top level categories: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene candidatos agrupados por categorÃƒÆ’Ã‚Â­a usando vistas de BD
     *
     * Utiliza las vistas optimizadas para obtener estadÃƒÆ’Ã‚Â­sticas de candidatos
     * por categorÃƒÆ’Ã‚Â­a de departamento.
     *
     * @param int|null $categoryId ID especÃƒÆ’Ã‚Â­fico de categorÃƒÆ’Ã‚Â­a (null para todas)
     * @param array $filters Filtros adicionales para candidatos
     * @return array Candidatos agrupados por categorÃƒÆ’Ã‚Â­a
     */
    public function getCandidatesByCategory(?int $categoryId = null, array $filters = []): array
    {
        try {
            $sql = "SELECT 
                        dc.id as category_id,
                        dc.name as category_name,
                        dc.description as category_description,
                        COUNT(DISTINCT c.id) as candidates_count,
                        COUNT(DISTINCT CASE WHEN c.status = 'active' THEN c.id END) as active_candidates_count,
                        GROUP_CONCAT(DISTINCT c.id ORDER BY c.created_at DESC LIMIT 5) as recent_candidate_ids
                    FROM bt_department_categories dc
                    LEFT JOIN bt_candidates c ON c.department_category_id = dc.id";

            $params = [];

            // Construir WHERE clause
            $whereConditions = [];

            if ($categoryId !== null) {
                $whereConditions[] = "dc.id = :category_id";
                $params[':category_id'] = $categoryId;
            }

            // Agregar filtros adicionales para candidatos
            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if ($value !== null && $this->isValidFieldName($field)) {
                        $whereConditions[] = "c.`$field` = :filter_$field";
                        $params[":filter_$field"] = $value;
                    }
                }
            }

            if (!empty($whereConditions)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
            }

            $sql .= ' GROUP BY dc.id, dc.name, dc.description
                      ORDER BY dc.sort_order ASC, dc.name ASC';

            return $this->query($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Error getting candidates by category', [
                'category_id' => $categoryId,
                'filters' => $filters
            ], $e);
            throw new \RuntimeException('Failed to get candidates by category: ' . $e->getMessage());
        }
    }

    /**
     * Valida la estructura jerÃƒÆ’Ã‚Â¡rquica antes de guardar
     *
     * Previene loops circulares y valida la integridad de la jerarquÃƒÆ’Ã‚Â­a.
     *
     * @param int|null $parentId ID de la categorÃƒÆ’Ã‚Â­a padre
     * @param int|null $currentId ID de la categorÃƒÆ’Ã‚Â­a actual (para updates)
     * @return bool True si la estructura es vÃƒÆ’Ã‚Â¡lida
     * @throws \InvalidArgumentException Si la estructura no es vÃƒÆ’Ã‚Â¡lida
     */
    public function validateHierarchy(?int $parentId, ?int $currentId = null): bool
    {
        if ($parentId === null) {
            return true; // CategorÃƒÆ’Ã‚Â­a raÃƒÆ’Ã‚Â­z, siempre vÃƒÆ’Ã‚Â¡lida
        }

        if ($parentId === $currentId) {
            throw new \InvalidArgumentException('Una categorÃƒÆ’Ã‚Â­a no puede ser padre de sÃƒÆ’Ã‚Â­ misma');
        }

        try {
            // Verificar que el padre existe y estÃƒÆ’Ã‚Â¡ activo
            $parent = $this->findById($parentId);
            if (!$parent) {
                throw new \InvalidArgumentException('La categorÃƒÆ’Ã‚Â­a padre especificada no existe');
            }

            if ($parent['status'] !== 'active') {
                throw new \InvalidArgumentException('La categorÃƒÆ’Ã‚Â­a padre debe estar activa');
            }

            // Verificar loops circulares
            if ($currentId !== null) {
                $ancestors = $this->getAncestors($parentId);
                if (in_array($currentId, array_column($ancestors, 'id'))) {
                    throw new \InvalidArgumentException('La jerarquÃƒÆ’Ã‚Â­a propuesta crearÃƒÆ’Ã‚Â­a un bucle circular');
                }
            }

            // Validar profundidad mÃƒÆ’Ã‚Â¡xima (por ejemplo, 5 niveles)
            $depth = $this->getCategoryDepth($parentId);
            if ($depth >= 5) {
                throw new \InvalidArgumentException('La profundidad mÃƒÆ’Ã‚Â¡xima de jerarquÃƒÆ’Ã‚Â­a es 5 niveles');
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Error validating hierarchy', [
                'parent_id' => $parentId,
                'current_id' => $currentId
            ], $e);
            throw $e;
        }
    }

    /**
     * Crea o actualiza una categorÃƒÆ’Ã‚Â­a con validaciones
     *
     * @param array $data Datos de la categorÃƒÆ’Ã‚Â­a
     * @param int|null $id ID para actualizaciÃƒÆ’Ã‚Â³n (null para crear)
     * @return mixed ID de la categorÃƒÆ’Ã‚Â­a creada o true para actualizaciÃƒÆ’Ã‚Â³n
     * @throws \InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    public function createOrUpdate(array $data, ?int $id = null)
    {
        // Validar datos requeridos
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('El nombre de la categorÃƒÆ’Ã‚Â­a es requerido');
        }

        // Validar estado
        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES)) {
            throw new \InvalidArgumentException('Estado no vÃƒÆ’Ã‚Â¡lido. Debe ser: ' . implode(', ', self::VALID_STATUSES));
        }

        // Establecer valores por defecto
        $data['status'] = $data['status'] ?? 'active';
        $data['sort_order'] = $data['sort_order'] ?? 0;

        try {
            // Validar jerarquÃƒÆ’Ã‚Â­a
            $parentId = $data['parent_id'] ?? null;
            $this->validateHierarchy($parentId, $id);

            // Crear o actualizar
            if ($id === null) {
                $result = $this->store($data);
                $this->logDebug('Category created successfully', ['id' => $result]);
            } else {
                $result = $this->update($id, $data);
                $this->logDebug('Category updated successfully', ['id' => $id]);
            }

            // Invalidar cache
            $this->invalidateCategoryCache();

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error creating/updating category', [
                'data' => $data,
                'id' => $id
            ], $e);
            throw $e;
        }
    }

    /**
     * Elimina una categorÃƒÆ’Ã‚Â­a y reorganiza la jerarquÃƒÆ’Ã‚Â­a
     *
     * @param int $id ID de la categorÃƒÆ’Ã‚Â­a a eliminar
     * @param bool $promoteChildren Si promover hijos al padre o eliminarlos
     * @return bool True si se eliminÃƒÆ’Ã‚Â³ correctamente
     */
    public function deleteWithHierarchy(int $id, bool $promoteChildren = true): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID must be positive');
        }

        try {
            // Obtener la categorÃƒÆ’Ã‚Â­a a eliminar
            $category = $this->findById($id);
            if (!$category) {
                throw new \InvalidArgumentException('Category not found');
            }

            // Obtener categorÃƒÆ’Ã‚Â­as hijas
            $children = $this->findAll(['parent_id' => $id]);

            if ($promoteChildren && !empty($children)) {
                // Promover hijos al abuelo
                $newParentId = $category['parent_id'];
                foreach ($children as $child) {
                    $this->update($child['id'], ['parent_id' => $newParentId]);
                }
            } elseif (!empty($children)) {
                // Eliminar hijos recursivamente
                foreach ($children as $child) {
                    $this->deleteWithHierarchy($child['id'], false);
                }
            }

            // Eliminar la categorÃƒÆ’Ã‚Â­a
            $result = $this->delete($id);

            // Invalidar cache
            $this->invalidateCategoryCache();

            $this->logDebug('Category deleted with hierarchy', [
                'id' => $id,
                'promote_children' => $promoteChildren
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting category with hierarchy', ['id' => $id], $e);
            throw $e;
        }
    }

    /**
     * Construye la estructura jerÃƒÆ’Ã‚Â¡rquica de categorÃƒÆ’Ã‚Â­as
     */
    private function buildCategoriesHierarchy(bool $includeInactive): array
    {
        $filters = [];
        if (!$includeInactive) {
            $filters['status'] = 'active';
        }

        $categories = $this->findAll($filters, 1, self::MAX_LIMIT, ['sort_order' => 'ASC', 'name' => 'ASC']);

        return $this->buildTreeFromFlat($categories);
    }

    /**
     * Construye ÃƒÆ’Ã‚Â¡rbol jerÃƒÆ’Ã‚Â¡rquico desde lista plana
     */
    private function buildTreeFromFlat(array $categories): array
    {
        $tree = [];
        $indexed = [];

        // Indexar por ID
        foreach ($categories as $category) {
            $category['children'] = [];
            $indexed[$category['id']] = $category;
        }

        // Construir ÃƒÆ’Ã‚Â¡rbol
        foreach ($indexed as $category) {
            if ($category['parent_id'] === null) {
                $tree[] = &$indexed[$category['id']];
            } else {
                if (isset($indexed[$category['parent_id']])) {
                    $indexed[$category['parent_id']]['children'][] = &$indexed[$category['id']];
                }
            }
        }

        return $tree;
    }

    /**
     * Ejecuta query para obtener categorÃƒÆ’Ã‚Â­a con departamentos
     */
    private function executeCategoryWithDepartmentsQuery(int $categoryId): ?array
    {
        $sql = "SELECT 
                    dc.*,
                    COUNT(d.id) as departments_count,
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', d.id,
                            'name', d.name,
                            'description', d.description,
                            'status', d.status
                        )
                    ) as departments
                FROM bt_department_categories dc
                LEFT JOIN bt_departments d ON d.category_id = dc.id
                WHERE dc.id = :id
                GROUP BY dc.id";

        $result = $this->query($sql, [':id' => $categoryId]);

        if (empty($result)) {
            return null;
        }

        $category = $result[0];

        // Parsear departamentos JSON
        if ($category['departments']) {
            $category['departments'] = array_map(
                'json_decode',
                explode(',', $category['departments'])
            );
        } else {
            $category['departments'] = [];
        }

        return $category;
    }

    /**
     * Obtiene los ancestros de una categorÃƒÆ’Ã‚Â­a
     */
    private function getAncestors(int $categoryId): array
    {
        $ancestors = [];
        $currentId = $categoryId;

        while ($currentId !== null) {
            $category = $this->findById($currentId);
            if (!$category) {
                break;
            }
            $ancestors[] = $category;
            $currentId = $category['parent_id'];
        }

        return $ancestors;
    }

    /**
     * Calcula la profundidad de una categorÃƒÆ’Ã‚Â­a en la jerarquÃƒÆ’Ã‚Â­a
     */
    private function getCategoryDepth(int $categoryId): int
    {
        return count($this->getAncestors($categoryId));
    }

    /**
     * Invalida el cache especÃƒÆ’Ã‚Â­fico de categorÃƒÆ’Ã‚Â­as
     */
    public function invalidateCategoryCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags([
                    'categories_hierarchy',
                    'category_with_departments',
                    'department_categories'
                ]);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating category cache', [], $e);
            return 0;
        }
    }

    /**
     * Obtiene estadÃƒÆ’Ã‚Â­sticas de uso de categorÃƒÆ’Ã‚Â­as
     */
    public function getCategoryStats(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_categories,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_categories,
                        COUNT(CASE WHEN parent_id IS NULL THEN 1 END) as root_categories,
                        MAX(sort_order) as max_sort_order,
                        COUNT(DISTINCT parent_id) as categories_with_children
                    FROM bt_department_categories";

            $result = $this->query($sql, []);
            return $result[0] ?? [];
        } catch (\Exception $e) {
            $this->logError('Error getting category stats', [], $e);
            return [];
        }
    }

    // ==========================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS CRUD ENCAPSULADOS ESTÃƒÆ’Ã‚ÂNDAR
    // ==========================================

    /**
     * Crear nuevo department_category con validaciones
     * @param array $data Datos del nuevo department_category
     * @return mixed ID del nuevo department_category o false en caso de error
     */
    public function createDepartmentCategory(array $data): mixed
    {
        try {
            $this->validateDepartmentCategoryData($data);
            // Filtrar solo los campos permitidos
            $filtered = array_intersect_key($data, array_flip($this->fillable));
            $id = $this->store($filtered);
            $this->invalidateDepartmentCategoryCache();
            $this->logDebug('DepartmentCategory created successfully', [
                'model' => static::class,
                'id' => $id
            ]);
            return $id;
        } catch (\Exception $e) {
            $this->logError('Error creating department_category', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Obtener department_category por ID
     * @param mixed $id ID del department_category
     * @return array|null Datos del department_category o null si no existe
     */
    public function getDepartmentCategory($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            $this->logError('Error retrieving department_category', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
            return null;
        }
    }

    /**
     * Actualizar department_category con validaciones
     * @param mixed $id ID del department_category a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualizaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function updateDepartmentCategory($id, array $data): bool
    {
        try {
            $this->validateDepartmentCategoryData($data, $id);
            // Filtrar solo los campos permitidos
            $filtered = array_intersect_key($data, array_flip($this->fillable));
            $result = $this->update($id, $filtered);
            if ($result) {
                $this->invalidateDepartmentCategoryCache();
                $this->logDebug('DepartmentCategory updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($filtered)
                ]);
            }
            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating department_category', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Eliminar department_category con validaciones
     * @param mixed $id ID del department_category a eliminar
     * @return bool True si la eliminaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function deleteDepartmentCategory($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateDepartmentCategoryCache();
                $this->logDebug('DepartmentCategory deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error deleting department_category', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ], $e);
            return false;
        }
    }

    /**
     * Buscar department_categories con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @param int $page PÃƒÆ’Ã‚Â¡gina actual
     * @param int $limit Registros por pÃƒÆ’Ã‚Â¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de department_categories
     */
    public function searchDepartmentCategories(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            $this->logError('Error searching department_categories', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
            return [];
        }
    }

    /**
     * Contar total de department_categories con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @return int NÃƒÆ’Ã‚Âºmero total de department_categories
     */
    public function countDepartmentCategories(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            $this->logError('Error counting department_categories', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ], $e);
            return 0;
        }
    }

    // ==========================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS DE VALIDACIÃƒÆ’Ã¢â‚¬Å“N ESPECÃƒÆ’Ã‚ÂFICOS
    // ==========================================

    /**
     * Validar datos especÃƒÆ’Ã‚Â­ficos de department_categories
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualizaciÃƒÆ’Ã‚Â³n (opcional)
     * @throws \InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    private function validateDepartmentCategoryData(array $data, $id = null): void
    {
        // Validar nombre requerido
        if (isset($data['name']) && empty(trim($data['name']))) {
            throw new \InvalidArgumentException('Department category name is required and cannot be empty');
        }

        // Validar longitud del nombre
        if (isset($data['name']) && strlen($data['name']) > 255) {
            throw new \InvalidArgumentException('Department category name cannot exceed 255 characters');
        }

        // Validar department_id si se proporciona
        if (isset($data['department_id'])) {
            if (!is_numeric($data['department_id']) || $data['department_id'] <= 0) {
                throw new \InvalidArgumentException('Department ID must be a positive integer');
            }
        }

        // Validar parent_id si se proporciona
        if (isset($data['parent_id'])) {
            if ($data['parent_id'] !== null) {
                if (!is_numeric($data['parent_id']) || $data['parent_id'] <= 0) {
                    throw new \InvalidArgumentException('Parent ID must be a positive integer or null');
                }

                // Validar jerarquÃƒÆ’Ã‚Â­a si existe el mÃƒÆ’Ã‚Â©todo
                if (method_exists($this, 'validateHierarchy')) {
                    $this->validateHierarchy($data['parent_id'], $id);
                }
            }
        }

        // Validar sort_order si se proporciona
        if (isset($data['sort_order']) && !is_numeric($data['sort_order'])) {
            throw new \InvalidArgumentException('Sort order must be numeric');
        }

        // Validar status si se proporciona
        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES)) {
            throw new \InvalidArgumentException('Invalid status. Must be: ' . implode(', ', self::VALID_STATUSES));
        }

        // Validar unicidad del nombre dentro del departamento
        if (isset($data['name']) && isset($data['department_id'])) {
            $existing = $this->findAll([
                'name' => $data['name'],
                'department_id' => $data['department_id']
            ]);

            if (!empty($existing)) {
                // Si es actualizaciÃƒÆ’Ã‚Â³n, verificar que no sea el mismo registro
                if ($id === null || $existing[0]['id'] != $id) {
                    throw new \InvalidArgumentException('Department category name already exists in this department');
                }
            }
        }
    }

    /**
     * Invalidar cache especÃƒÆ’Ã‚Â­fico de department_categories
     */
    public function invalidateDepartmentCategoryCache(): int
    {
        try {
            // Usar el mÃƒÆ’Ã‚Â©todo existente si estÃƒÆ’Ã‚Â¡ disponible
            if (method_exists($this, 'invalidateCategoryCache')) {
                return $this->invalidateCategoryCache();
            }

            // Fallback para cache externo
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['department_categories', 'department_category_core', 'department_category_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating department_category cache', [], $e);
            return 0;
        }
    }
}
