<?php

namespace Models;

/**
 * Modelo para las entrevistas programadas.
 *
 * Cada entrevista está asociada a una solicitud de empleo (`application_id`) y
 * opcionalmente a un reclutador (`recruiter_id`).  Se almacena información
 * como la fecha, duración, ubicación o enlace de reunión.
 */
class Interview extends BaseModel
{
    protected string $table = 'interviews';

    /**
     * Devuelve todas las entrevistas para una solicitud concreta.
     *
     * @param string $applicationId ID de la solicitud
     * @return array Lista de entrevistas
     */
    public function findByApplicationId(string $applicationId): array
    {
        return $this->findAll(['application_id' => $applicationId], 1, self::MAX_LIMIT);
    }
}
