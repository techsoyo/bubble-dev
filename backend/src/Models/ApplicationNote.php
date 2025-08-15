<?php

namespace Models;

/**
 * Modelo para notas asociadas a solicitudes de empleo.
 *
 * Cada nota está ligada a una solicitud (application_id) y tiene
 * un índice incremental (note_idx) que permite múltiples notas por
 * aplicación.  Al no disponer de un campo `id` propio, se establece
 * `application_id` como clave primaria lógica para operaciones básicas.
 *
 * Este modelo hereda de BaseModel para aprovechar las operaciones
 * genéricas de CRUD y los filtros seguros.  El nombre de tabla se
 * define sin prefijo; BaseModel aplicará automáticamente el prefijo
 * configurado en la variable de entorno DB_TABLE_PREFIX.
 */
class ApplicationNote extends BaseModel
{
    /**
     * Nombre de la tabla sin prefijo
     *
     * @var string
     */
    protected string $table = 'application_notes';

    /**
     * Clave primaria lógica; al no haber campo `id`, utilizamos
     * `application_id` para operaciones básicas.  Las notas se
     * identifican realmente por la combinación (application_id, note_idx).
     *
     * @var string
     */
    protected string $primaryKey = 'application_id';

    /**
     * Devuelve todas las notas asociadas a una solicitud concreta.
     *
     * @param string $applicationId ID de la solicitud
     * @return array Lista de notas
     */
    public function findByApplicationId(string $applicationId): array
    {
        return $this->findAll(['application_id' => $applicationId], 1, self::MAX_LIMIT);
    }
}
