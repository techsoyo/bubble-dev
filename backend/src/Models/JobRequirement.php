<?php

namespace Models;

/**
 * Modelo para los requisitos de las ofertas de trabajo.
 */
class JobRequirement extends BaseModel
{
    protected string $table = 'job_requirements';

    protected string $primaryKey = 'job_id';

    /**
     * Obtiene los requisitos de un puesto de trabajo concreto.
     *
     * @param string $jobId ID del puesto
     * @return array Lista de requisitos
     */
    public function findByJobId(string $jobId): array
    {
        return $this->findAll(['job_id' => $jobId], 1, self::MAX_LIMIT);
    }
}
