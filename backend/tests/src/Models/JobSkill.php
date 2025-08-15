<?php

namespace Models;

/**
 * Modelo para las habilidades requeridas en ofertas de trabajo.
 */
class JobSkill extends BaseModel
{
    protected string $table = 'job_skills';

    protected string $primaryKey = 'job_id';

    /**
     * Devuelve todas las habilidades asociadas a un puesto de trabajo.
     *
     * @param string $jobId ID del puesto
     * @return array Lista de habilidades
     */
    public function findByJobId(string $jobId): array
    {
        return $this->findAll(['job_id' => $jobId], 1, self::MAX_LIMIT);
    }
}
