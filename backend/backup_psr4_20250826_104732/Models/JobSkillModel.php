<?php declare(strict_types=1);

namespace Models\JobSkillModel.php\Models;

use Models\BaseModel;
use Utils\Logger;

class JobSkillModel extends BaseModel
{
  protected string $table = 'bt_job_skills';
  protected string $primaryKey = 'id';

  /**
   * Campos permitidos para asignaciÃ³n masiva
   * Ajusta segÃºn la estructura real de la tabla
   */
  protected array $fillable = [
    'job_id',
    'skill_id',
    'required_level',
    'is_required',
    'weight',
  ];

  /**
   * Crear un nuevo registro de habilidad de trabajo de forma segura
   */
  public function createJobSkill(array $data): int|false
  {
    try {
      $this->validateSkillData($data);
      $filtered = $this->filterFillable($data);
      $id = $this->store($filtered);
      if ($id) {
        Logger::info('JobSkill creado', ['id' => $id]);
        return $id;
      }
      return false;
    } catch (\Exception $e) {
      Logger::error('Error creando JobSkill', ['error' => $e->getMessage(), 'data' => $data]);
      throw $e;
    }
  }

  /**
   * Actualizar un registro de habilidad de trabajo de forma segura
   */
  public function updateJobSkill(int $id, array $data): bool
  {
    try {
      if (!$this->findById($id)) {
        return false;
      }
      $this->validateSkillData($data, false);
      $filtered = $this->filterFillable($data);
      $result = $this->update($id, $filtered);
      if ($result) {
        Logger::info('JobSkill actualizado', ['id' => $id]);
      }
      return $result;
    } catch (\Exception $e) {
      Logger::error('Error actualizando JobSkill', ['id' => $id, 'error' => $e->getMessage()]);
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
   * ValidaciÃ³n bÃ¡sica de datos
   */
  private function validateSkillData(array $data, bool $isCreation = true): void
  {
    if ($isCreation && empty($data['job_id'])) {
      throw new \InvalidArgumentException('job_id es requerido');
    }
    if (isset($data['job_id']) && (!is_numeric($data['job_id']) || $data['job_id'] <= 0)) {
      throw new \InvalidArgumentException('job_id debe ser un entero positivo');
    }
    if ($isCreation && empty($data['skill_id'])) {
      throw new \InvalidArgumentException('skill_id es requerido');
    }
    if (isset($data['skill_id']) && (!is_numeric($data['skill_id']) || $data['skill_id'] <= 0)) {
      throw new \InvalidArgumentException('skill_id debe ser un entero positivo');
    }
    if (isset($data['required_level']) && (!is_numeric($data['required_level']) || $data['required_level'] < 0)) {
      throw new \InvalidArgumentException('required_level debe ser un nÃºmero positivo');
    }
    if (isset($data['weight']) && (!is_numeric($data['weight']) || $data['weight'] < 0)) {
      throw new \InvalidArgumentException('weight debe ser un nÃºmero positivo');
    }
    if (isset($data['is_required']) && !is_bool($data['is_required'])) {
      throw new \InvalidArgumentException('is_required debe ser booleano');
    }
  }
}
