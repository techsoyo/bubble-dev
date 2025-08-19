<?php

require_once __DIR__ . '/../bootstrap.php';
preflightHandle();
sendCorsHeaders();

/**
 * Endpoint para extracción de habilidades de un CV
 *
 * Reemplaza la funcionalidad del módulo IA con implementación en PHP puro
 */

use Utils\ResponseHelper;

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('Método no permitido', 405);
    exit;
}

// Obtener input JSON
// ...lógica original aquí...
