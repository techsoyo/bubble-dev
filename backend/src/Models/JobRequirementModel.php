<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

class JobRequirementModel extends BaseModel
{
  protected string $table = 'bt_job_requirements';
  protected string $primaryKey = 'id';

  /**
   * Campos permitidos para asignación masiva
   * Ajusta según la estructura real de la tabla
   */
  protected array $fillable = [
    'job_id',
    'requirement',
    'is_mandatory',
    'priority',
    'requirement_type',
  ];

  /**
   * Crear un nuevo requisito de trabajo de forma segura
   */
  public function createJobRequirement(array $data): int|false
  {
    try {
      $this->validateRequirementData($data);
      $filtered = $this->filterFillable($data);
      $id = $this->store($filtered);
      if ($id) {
        Logger::info('JobRequirement creado', ['id' => $id]);
        return $id;
      }
      return false;
    } catch (\Exception $e) {
      Logger::error('Error creando JobRequirement', ['error' => $e->getMessage(), 'data' => $data]);
      throw $e;
    }
  }

  /**
   * Actualizar un requisito de trabajo de forma segura
   */
  public function updateJobRequirement(int $id, array $data): bool
  {
    try {
      if (!$this->findById($id)) {
        return false;
      }
      $this->validateRequirementData($data, false);
      $filtered = $this->filterFillable($data);
      $result = $this->update($id, $filtered);
      if ($result) {
        Logger::info('JobRequirement actualizado', ['id' => $id]);
      }
      return $result;
    } catch (\Exception $e) {
      Logger::error('Error actualizando JobRequirement', ['id' => $id, 'error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Filtra los datos permitiendo solo los campos fillable
   */
  private function filterFillable(array $data): array
  {
    $filtered = [];
    foreach ($this->fillable as $field) {
      if (array_key_exists($field, $data)) {
        $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
      }
    }
    return $filtered;
  }

  /**
   * Validación bí¡sica de datos
   */
  private function validateRequirementData(array $data, bool $isCreation = true): void
  {
    if ($isCreation && empty($data['job_id'])) {
      throw new \InvalidArgumentException('job_id es requerido');
    }
    if (isset($data['job_id']) && (!is_numeric($data['job_id']) || $data['job_id'] <= 0)) {
      throw new \InvalidArgumentException('job_id debe ser un entero positivo');
    }
    if ($isCreation && empty($data['requirement'])) {
      throw new \InvalidArgumentException('El campo requirement es requerido');
    }
    if (isset($data['requirement']) && strlen(trim($data['requirement'])) < 3) {
      throw new \InvalidArgumentException('El requirement debe tener al menos 3 caracteres');
    }
    if (isset($data['priority']) && !in_array($data['priority'], [1, 2, 3, 4, 5])) {
      throw new \InvalidArgumentException('Prioridad no ví¡lida');
    }
    if (isset($data['is_mandatory']) && !is_bool($data['is_mandatory'])) {
      throw new \InvalidArgumentException('is_mandatory debe ser booleano');
    }
  }
}
