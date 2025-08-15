<?php

namespace Models;

/**
 * Modelo para las notificaciones enviadas a los candidatos.
 */
class Notification extends BaseModel
{
    protected string $table = 'notifications';

    /**
     * Devuelve todas las notificaciones de un candidato.
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de notificaciones
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->findAll(['candidate_id' => $candidateId], 1, self::MAX_LIMIT);
    }
}
