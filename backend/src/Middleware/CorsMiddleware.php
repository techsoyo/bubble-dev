<?php

namespace Middleware;

use Utils\Request;

/**
 * Middleware para manejar CORS - DEPRECATED
 * CORS ahora se configura automáticamente en bootstrap.php
 */
class CorsMiddleware
{
    /**
     * DEPRECATED - CORS se configura automáticamente en bootstrap.php
     *
     * @param Request $request Objeto de solicitud
     * @return bool Siempre devuelve true para permitir que continue la cadena de middleware
     */
    public static function handle(Request $request)
    {
        // CORS ya configurado en bootstrap.php - método mantenido por compatibilidad
        if (function_exists('error_log')) {
            error_log('DEPRECATION WARNING: CorsMiddleware ya no es necesario. CORS se configura automáticamente en bootstrap.php');
        }

        return true;
    }
}
