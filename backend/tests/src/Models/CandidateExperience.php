<?php

namespace Models;

/**
 * Modelo para las experiencias profesionales de los candidatos.
 *
 * Cada experiencia posee un campo `id` autoincremental y está
 * asociada a un candidato mediante `candidate_id`.  Este modelo
 * proporciona un método auxiliar para recuperar todas las
 * experiencias de un candidato concreto.
 */
class CandidateExperience extends BaseModel
{
    /**
     * Nombre de la tabla sin prefijo.  El prefijo será añadido
     * automáticamente por BaseModel en función de la variable
     * DB_TABLE_PREFIX del entorno.
     *
     * @var string
     */
    protected string $table = 'candidate_experiences';

    /**
     * Devuelve todas las experiencias asociadas a un candidato.
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de experiencias
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->findAll(['candidate_id' => $candidateId], 1, self::MAX_LIMIT);
    }
}
