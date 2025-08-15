<?php

namespace Models;

/**
 * Modelo para los valores de cultura corporativa.
 *
 * La tabla `culture` define distintos aspectos culturales de la
 * organización, cada uno con un ID numérico, título, descripción
 * e imagen asociada.  Este modelo utiliza las operaciones
 * genéricas de BaseModel.
 */
class Culture extends BaseModel
{
    /**
     * Nombre de la tabla sin prefijo.  BaseModel añadirá `bt_` si
     * DB_TABLE_PREFIX está configurado.
     *
     * @var string
     */
    protected string $table = 'culture';
}
