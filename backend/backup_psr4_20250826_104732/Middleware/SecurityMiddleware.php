<?php declare(strict_types=1);

namespace Middleware\SecurityMiddleware.php\Middleware;

/**
 * SecurityMiddleware - Middleware de seguridad bÃ¡sico
 * Proporciona validaciones de seguridad y headers CORS
 */
class SecurityMiddleware
{

  /**
   * Validar request bÃ¡sico
   */
  public static function validateRequest()
  {
    // Verificar mÃ©todo HTTP
    $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
      http_response_code(405);
      return false;
    }

    // ValidaciÃ³n bÃ¡sica de headers
    return true;
  }

  /**
   * Configurar headers CORS
   */
  public static function corsHeaders()
  {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Content-Type: application/json; charset=UTF-8");

    // Responder a preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      http_response_code(200);
      exit();
    }
  }

  /**
   * Validar contenido JSON
   */
  public static function validateJsonInput()
  {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
      $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
      if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
          http_response_code(400);
          echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON format',
            'error' => json_last_error_msg()
          ]);
          return false;
        }
        $_POST = array_merge($_POST, $data);
      }
    }
    return true;
  }

  /**
   * Aplicar todas las validaciones
   */
  public static function apply()
  {
    self::corsHeaders();

    if (!self::validateRequest()) {
      echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
      ]);
      exit();
    }

    if (!self::validateJsonInput()) {
      exit();
    }

    return true;
  }
}
