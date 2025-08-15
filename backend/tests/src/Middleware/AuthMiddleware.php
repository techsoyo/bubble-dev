<?php

namespace Middleware;

use Utils\JWT;
use Utils\Request;
use Utils\ResponseHelper;

/**
 * Middleware para autenticación mediante JWT
 */
class AuthMiddleware
{
    /**
     * Verifica si el usuario está autenticado mediante JWT
     *
     * @param Request $request Objeto de solicitud
     * @return bool True si el usuario está autenticado, false en caso contrario
     */
    public static function handle(Request $request)
    {
        // Obtener el token de autenticación
        $token = $request->getAuthToken();

        if (!$token) {
            ResponseHelper::error('No autorizado: Token no proporcionado', null, 401);
            return false;
        }

        // Verificar el token
        $payload = JWT::verify($token);

        if (!$payload) {
            ResponseHelper::error('No autorizado: Token inválido o expirado', null, 401);
            return false;
        }

        // Almacenar los datos del usuario en la solicitud para su uso posterior
        $request->setUser($payload);

        return true;
    }
}
