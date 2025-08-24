<?php

require_once __DIR__ . '/../bootstrap.php';
preflightHandle();
sendCorsHeaders();

/**
 * Endpoint para extracciÃ³n de habilidades de un CV
 *
 * Reemplaza la funcionalidad del mÃ³dulo IA con implementaciÃ³n en PHP puro
 */

use Utils\ResponseHelper;

// Solo permitir mÃ©todo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('MÃ©todo no permitido', 405);
    exit;
}

// Obtener input JSON
// ...lÃ³gica original aquÃ­...
