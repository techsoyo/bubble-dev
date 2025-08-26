<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para gestión de categorías de trabajo
 * 
 * Gestiona las categorías de trabajos del sistema con soporte para
 * jerarquías, conteos de jobs asociados y validaciones.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-08-24
 */
class JobCategory extends BaseModel
{
  protected string $table = 'job_categories';

  protected array $fillable = [
    'name',
    'description',
    'parent_id',
    'is_active',
    'sort_order'
  ];

  protected array $hidden = [];

  /**
   * Crear una nueva categoría de trabajo
   * 
   * @param array $data Datos de la categoría
   * @return int|false ID de la categoría creada o false si falla
   */
  public function createJobCategory(array $data): int|false
  {
    try {
      $this->validateCategoryData($data);

      $categoryData = [
        'name' => trim($data['name']),
        'description' => $data['description'] ?? '',
        'parent_id' => $data['parent_id'] ?? null,
        'is_active' => $data['is_active'] ?? true,
        'sort_order' => $data['sort_order'] ?? 0
      ];

      $id = $this->store($categoryData);

      if ($id) {
        Logger::info('Job category created successfully', ['id' => $id, 'name' => $categoryData['name']]);
        return $id;
      }

      return false;
    } catch (\Exception $e) {
      Logger::error('Error creating job category', ['error' => $e->getMessage(), 'data' => $data]);
      throw $e;
    }
  }

  /**
   * Obtener una categoría por ID con conteo de jobs
   * 
   * @param int $id ID de la categoría
   * @return array|null Datos de la categoría o null si no existe
   */
  public function getJobCategoryWithCount(int $id): ?array
  {
    try {
      $sql = "
                SELECT 
                    jc.*,
                    COUNT(j.id) as jobs_count,
                    parent.name as parent_name
                FROM job_categories jc
                LEFT JOIN jobs j ON j.category_id = jc.id
                LEFT JOIN job_categories parent ON parent.id = jc.parent_id
                WHERE jc.id = :id
                GROUP BY jc.id
            ";

      $result = $this->query($sql, [':id' => $id]);

      if (!empty($result)) {
        Logger::debug('Job category retrieved successfully', ['id' => $id]);
        return $result[0];
      }

      return null;
    } catch (\Exception $e) {
      Logger::error('Error retrieving job category', ['id' => $id, 'error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Actualizar una categoría de trabajo
   * 
   * @param int $id ID de la categoría
   * @param array $data Datos a actualizar
   * @return bool True si se actualizó correctamente
   */
  public function updateJobCategory(int $id, array $data): bool
  {
    try {
      // Verificar que existe
      if (!$this->findById($id)) {
        return false;
      }

      $this->validateCategoryData($data, false);

      $categoryData = array_filter([
        'name' => isset($data['name']) ? trim($data['name']) : null,
        'description' => $data['description'] ?? null,
        'parent_id' => $data['parent_id'] ?? null,
        'is_active' => $data['is_active'] ?? null,
        'sort_order' => $data['sort_order'] ?? null
      ], fn($value) => $value !== null);

      $result = $this->update($id, $categoryData);

      if ($result) {
        Logger::info('Job category updated successfully', ['id' => $id]);
      }

      return $result;
    } catch (\Exception $e) {
      Logger::error('Error updating job category', ['id' => $id, 'error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Eliminar una categoría de trabajo
   * 
   * @param int $id ID de la categoría
   * @return bool True si se eliminó correctamente
   */
  public function deleteJobCategory(int $id): bool
  {
    try {
      // Verificar si tiene jobs asociados
      $jobsCount = $this->getJobsCountForCategory($id);
      if ($jobsCount > 0) {
        throw new \InvalidArgumentException('No se puede eliminar la categoría porque tiene jobs asociados');
      }

      $result = $this->delete($id);

      if ($result) {
        Logger::info('Job category deleted successfully', ['id' => $id]);
      }

      return $result;
    } catch (\Exception $e) {
      Logger::error('Error deleting job category', ['id' => $id, 'error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Buscar categorías de trabajo con jerarquía
   * 
   * @param array $criteria Criterios de búsqueda
   * @param int $limit Límite de resultados
   * @param int $offset Offset para paginación
   * @return array Lista de categorías encontradas
   */
  public function searchJobCategories(array $criteria = [], int $limit = 50, int $offset = 0): array
  {
    try {
      $sql = "
                SELECT 
                    jc.*,
                    COUNT(j.id) as jobs_count,
                    parent.name as parent_name
                FROM job_categories jc
                LEFT JOIN jobs j ON j.category_id = jc.id
                LEFT JOIN job_categories parent ON parent.id = jc.parent_id
            ";

      $params = [];
      $whereConditions = [];

      if (!empty($criteria['name'])) {
        $whereConditions[] = "jc.name LIKE :name";
        $params[':name'] = '%' . $criteria['name'] . '%';
      }

      if (!empty($criteria['parent_id'])) {
        $whereConditions[] = "jc.parent_id = :parent_id";
        $params[':parent_id'] = $criteria['parent_id'];
      }

      if (isset($criteria['is_active'])) {
        $whereConditions[] = "jc.is_active = :is_active";
        $params[':is_active'] = $criteria['is_active'];
      }

      if (!empty($whereConditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
      }

      $sql .= ' GROUP BY jc.id ORDER BY jc.sort_order ASC, jc.name ASC';
      $sql .= ' LIMIT :limit OFFSET :offset';

      $params[':limit'] = $limit;
      $params[':offset'] = $offset;

      return $this->query($sql, $params);
    } catch (\Exception $e) {
      Logger::error('Error searching job categories', ['criteria' => $criteria, 'error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Contar categorías según criterios
   * 
   * @param array $criteria Criterios de búsqueda
   * @return int Número de categorías encontradas
   */
  public function countJobCategories(array $criteria = []): int
  {
    try {
      $filters = [];

      if (!empty($criteria['name'])) {
        $filters['name'] = ['LIKE', '%' . $criteria['name'] . '%'];
      }

      if (!empty($criteria['parent_id'])) {
        $filters['parent_id'] = $criteria['parent_id'];
      }

      if (isset($criteria['is_active'])) {
        $filters['is_active'] = $criteria['is_active'];
      }

      return $this->countAll($filters);
    } catch (\Exception $e) {
      Logger::error('Error counting job categories', ['criteria' => $criteria, 'error' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Obtener categorías principales (sin parent_id)
   * 
   * @return array Lista de categorías principales
   */
  public function getMainCategories(): array
  {
    try {
      $sql = "
                SELECT 
                    jc.*,
                    COUNT(j.id) as jobs_count,
                    COUNT(subcats.id) as subcategories_count
                FROM job_categories jc
                LEFT JOIN jobs j ON j.category_id = jc.id
                LEFT JOIN job_categories subcats ON subcats.parent_id = jc.id
                WHERE jc.parent_id IS NULL AND jc.is_active = 1
                GROUP BY jc.id
                ORDER BY jc.sort_order ASC, jc.name ASC
            ";

      return $this->query($sql);
    } catch (\Exception $e) {
      Logger::error('Error getting main categories', ['error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Obtener conteo de jobs para una categoría
   * 
   * @param int $categoryId ID de la categoría
   * @return int Número de jobs asociados
   */
  private function getJobsCountForCategory(int $categoryId): int
  {
    try {
      $sql = "SELECT COUNT(*) as count FROM jobs WHERE category_id = :category_id";
      $result = $this->query($sql, [':category_id' => $categoryId]);

      return $result[0]['count'] ?? 0;
    } catch (\Exception $e) {
      Logger::error('Error counting jobs for category', ['category_id' => $categoryId, 'error' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Validar datos de categoría
   * 
   * @param array $data Datos a validar
   * @param bool $isCreation Si es una creación (requiere todos los campos)
   * @throws \InvalidArgumentException Si los datos no son válidos
   */
  private function validateCategoryData(array $data, bool $isCreation = true): void
  {
    if ($isCreation && empty($data['name'])) {
      throw new \InvalidArgumentException('El nombre de la categoría es requerido');
    }

    if (isset($data['name']) && strlen(trim($data['name'])) < 2) {
      throw new \InvalidArgumentException('El nombre de la categoría debe tener al menos 2 caracteres');
    }

    if (isset($data['parent_id']) && !is_null($data['parent_id']) && $data['parent_id'] <= 0) {
      throw new \InvalidArgumentException('El parent_id debe ser un número positivo o null');
    }

    if (isset($data['sort_order']) && !is_numeric($data['sort_order'])) {
      throw new \InvalidArgumentException('El sort_order debe ser un número');
    }
  }
}
