<?php

namespace Middleware;

use Utils\Request;

/**
 * Middleware de autenticación para rutas protegidas
 */
class AuthMiddleware
{
  /**
   * Manejar la autenticación de la request
   * 
   * @param Request $request
   * @return void
   * @throws \Exception Si no hay autenticación válida
   */
  public static function handle(Request $request)
  {
    // Intentar obtener el usuario usando el método existente
    try {
      $user = $request->getUser();

      // Si no hay usuario, es posible que no haya token
      if (!$user) {
        self::respondUnauthorized('No authentication token provided or token invalid');
        return;
      }

      // Si llegamos aquí, el usuario está autenticado correctamente

    } catch (\RuntimeException $e) {
      // Token inválido o error de autenticación
      self::respondUnauthorized($e->getMessage());
      return;
    } catch (\Exception $e) {
      // Otro tipo de error
      self::respondUnauthorized('Authentication error: ' . $e->getMessage());
      return;
    }
  }

  /**
   * Responder con error 401 y terminar ejecución
   * 
   * @param string $message
   */
  private static function respondUnauthorized($message = 'Unauthorized')
  {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
      'success' => false,
      'error' => $message,
      'code' => 401,
      'timestamp' => date('c'),
      'path' => $_SERVER['REQUEST_URI'] ?? '',
      'method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ]);
    exit;
  }
}
