<?php

namespace Models;

/**
 * Modelo para las habilidades declaradas por los candidatos.
 *
 * La tabla no cuenta con un identificador numérico; las filas se
 * distinguen por la combinación (candidate_id, skill).  Para
 * operaciones básicas se utiliza `candidate_id` como clave primaria
 * lógica.  Incluye un método para recuperar todas las habilidades de
 * un candidato concreto.
 */
class CandidateSkill extends BaseModel
{
    /**
     * Tabla sin prefijo; BaseModel aplicará el prefijo automáticamente.
     *
     * @var string
     */
    protected string $table = 'candidate_skills';

    /**
     * Clave primaria lógica.
     *
     * @var string
     */
    protected string $primaryKey = 'candidate_id';

    /**
     * Devuelve todas las habilidades asociadas a un candidato.
     *
     * @param string $candidateId ID del candidato
     * @return array Lista de habilidades
     */
    public function findByCandidateId(string $candidateId): array
    {
        return $this->findAll(['candidate_id' => $candidateId], 1, self::MAX_LIMIT);
    }
}
