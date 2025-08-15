<?php

namespace Models;

/**
 * Modelo para las categorías dentro de cada departamento.
 */
class DepartmentCategory extends BaseModel
{
    protected string $table = 'department_categories';

    /**
     * Devuelve las categorías de un departamento concreto.
     *
     * @param int $departmentId ID del departamento
     * @return array Lista de categorías
     */
    public function findByDepartmentId(int $departmentId): array
    {
        return $this->findAll(['department_id' => $departmentId], 1, self::MAX_LIMIT);
    }
}
