<?php declare(strict_types=1);

namespace Middleware\AuthMiddleware.php\Middleware;

use Utils\Request;
use Utils\JWT;
use Utils\ResponseHelper;

/**
 * Middleware de autenticaciÃ³n para rutas protegidas
 */
class AuthMiddleware
{
  /**
   * Manejar la autenticaciÃ³n y autorizaciÃ³n de la request
   * 
   * @param Request $request La solicitud actual
   * @param array $roles Roles permitidos para acceder al recurso
   * @return ?\Closure FunciÃ³n de middleware o null si la autenticaciÃ³n es exitosa
   */
  public static function handle(Request $request, array $roles = []): ?\Closure
  {
    // Intentar obtener token del encabezado Authorization (Bearer)
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = '';

    if (!empty($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
      $token = substr($authHeader, 7); // Eliminar "Bearer "
    }

    // Si no hay token en encabezado, intentar obtenerlo de cookies
    if (empty($token)) {
      $token = $_COOKIE['auth_token'] ?? '';
    }

    // Si no hay token en ninguna parte, responder con 401
    if (empty($token)) {
      return function () {
        return self::respondUnauthorized('No authentication token provided');
      };
    }

    try {
      // Verificar el token JWT
      $user = JWT::verify($token);

      // Verificar que el token tenga la informaciÃ³n mÃ­nima esperada
      if (!$user || !isset($user['user_id'])) {
        return function () {
          return self::respondUnauthorized('Invalid token payload');
        };
      }

      // Guardar usuario en el objeto Request para acceso posterior
      $request->setUser($user);

      // Si se especificaron roles, verificar que el usuario tenga alguno de ellos
      if (!empty($roles)) {
        $userRole = $user['role'] ?? 'guest';

        // Verificar si el rol del usuario estÃ¡ en la lista de roles permitidos
        // TambiÃ©n permitir acceso a administradores globales
        $hasAccess = in_array($userRole, $roles, true) ||
          in_array($userRole, ['admin', 'superadmin'], true);

        if (!$hasAccess) {
          return function () {
            return self::respondForbidden('Insufficient privileges');
          };
        }
      }

      // AutenticaciÃ³n y autorizaciÃ³n exitosas
      return null;
    } catch (\Exception $e) {
      // Error al verificar el token
      return function () use ($e) {
        return self::respondUnauthorized('Authentication error: ' . $e->getMessage());
      };
    }
  }

  /**
   * Responder con error 401 Unauthorized
   * 
   * @param string $message Mensaje de error
   * @return array Respuesta formateada
   */
  private static function respondUnauthorized($message = 'Unauthorized')
  {
    http_response_code(401);
    return ResponseHelper::error($message, null, 401);
  }

  /**
   * Responder con error 403 Forbidden
   * 
   * @param string $message Mensaje de error
   * @return array Respuesta formateada
   */
  private static function respondForbidden($message = 'Forbidden')
  {
    http_response_code(403);
    return ResponseHelper::error($message, null, 403);
  }
}
