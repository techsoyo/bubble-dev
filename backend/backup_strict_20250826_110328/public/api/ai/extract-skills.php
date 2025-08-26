<?php declare(strict_types=1);



require_once __DIR__ . '/../bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃƒÂ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃƒÂ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
preflightHandle();
sendCorsHeaders();

/**
 * Endpoint para extracciÃƒÆ’Ã‚Â³n de habilidades de un CV
 *
 * Reemplaza la funcionalidad del mÃƒÆ’Ã‚Â³dulo IA con implementaciÃƒÆ’Ã‚Â³n en PHP puro
 */

use Utils\ResponseHelper;

// Solo permitir mÃƒÆ’Ã‚Â©todo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error('MÃƒÆ’Ã‚Â©todo no permitido', 405);
    exit;
}

// Obtener input JSON
// ...lÃƒÆ’Ã‚Â³gica original aquÃƒÆ’Ã‚Â­...

