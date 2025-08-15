<?php

namespace Models;

/**
 * Modelo para las autenticaciones sociales de los candidatos.
 */
class SocialLogin extends BaseModel
{
    protected string $table = 'social_logins';

    /**
     * Obtiene las autenticaciones sociales de un candidato.
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de autenticaciones
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->findAll(['candidate_id' => $candidateId], 1, self::MAX_LIMIT);
    }
}
