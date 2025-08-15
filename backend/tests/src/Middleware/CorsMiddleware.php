<?php

namespace Middleware;

use Utils\Request;

/**
 * Middleware para manejar CORS (Cross-Origin Resource Sharing)
 */
class CorsMiddleware
{
    /**
     * Procesa la solicitud y aplica los encabezados CORS
     *
     * @param Request $request Objeto de solicitud
     * @return bool Siempre devuelve true para permitir que continue la cadena de middleware
     */
    public static function handle(Request $request)
    {
        if (!defined('CORS_APPLIED') && PHP_SAPI !== 'cli') {
            if (!defined('BASE_PATH')) {
                define('BASE_PATH', realpath(__DIR__ . '/../../'));
            }
            require_once BASE_PATH . '/cors.php';
        }
        // Si OPTIONS y cors.php no salió (por algún motivo), manejarlo
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
        return true;
    }
}
