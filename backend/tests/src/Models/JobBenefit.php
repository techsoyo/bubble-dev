<?php

namespace Models;

/**
 * Modelo para los beneficios asociados a las ofertas de trabajo.
 */
class JobBenefit extends BaseModel
{
    protected string $table = 'job_benefits';

    /**
     * Devuelve todos los beneficios asociados a un puesto de trabajo.
     *
     * @param string $jobId ID del puesto
     * @return array Lista de beneficios
     */
    public function findByJobId(string $jobId): array
    {
        return $this->findAll(['job_id' => $jobId], 1, self::MAX_LIMIT);
    }
}
