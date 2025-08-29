<?php declare(strict_types=1);
namespace Security;

/**
 * CSRF Middleware - ProtecciÃƒÂ³n contra Cross-Site Request Forgery
 * 
 * Implementa el patrÃƒÂ³n "double-submit cookie":
 * - Cookie XSRF-TOKEN (legible por JS)
 * - Header X-CSRF-Token (enviado por JS)
 * - ValidaciÃƒÂ³n que ambos coincidan
 * 
 * @package Security
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */
final class CsrfMiddleware
{
  /**
   * MÃƒÂ©todos HTTP que requieren validaciÃƒÂ³n CSRF
   */
  private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

  /**
   * Nombre de la cookie CSRF
   */
  private const CSRF_COOKIE_NAME = 'XSRF-TOKEN';

  /**
   * Nombre del header CSRF
   */
  private const CSRF_HEADER_NAME = 'HTTP_X_CSRF_TOKEN';

  /**
   * Generar token CSRF y establecer cookie
   */
  public static function generateToken(): string
  {
    // Generar token aleatorio seguro
    $csrfToken = bin2hex(random_bytes(32));

    // Establecer cookie CSRF (NO httpOnly para que JS pueda leerla)
    $cookieOptions = Cookies::options([
      'httponly' => false, // Debe ser legible por JavaScript
      'samesite' => 'Strict', // MÃƒÂ¡s restrictivo para CSRF
    ]);

    setcookie(self::CSRF_COOKIE_NAME, $csrfToken, $cookieOptions);

    return $csrfToken;
  }

  /**
   * Validar token CSRF en request actual
   */
  public static function validateToken(): bool
  {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Solo validar mÃƒÂ©todos que pueden modificar estado
    if (!in_array($method, self::PROTECTED_METHODS, true)) {
      return true; // GET, HEAD, OPTIONS estÃƒÂ¡n libres
    }

    // Obtener tokens de cookie y header
    $cookieToken = $_COOKIE[self::CSRF_COOKIE_NAME] ?? '';
    $headerToken = $_SERVER[self::CSRF_HEADER_NAME] ?? '';

    // Validar que ambos tokens existan y coincidan
    if (!$cookieToken || !$headerToken) {
      return false;
    }

    // ComparaciÃƒÂ³n segura contra timing attacks
    return hash_equals($cookieToken, $headerToken);
  }

  /**
   * Middleware de protecciÃƒÂ³n CSRF
   * 
   * Valida el token CSRF y envÃƒÂ­a 403 si es invÃƒÂ¡lido
   */
  public static function protect(): void
  {
    if (!self::validateToken()) {
      self::sendForbidden('CSRF token invalid or missing');
    }
  }

  /**
   * Verificar si el mÃƒÂ©todo actual requiere protecciÃƒÂ³n CSRF
   */
  public static function requiresProtection(): bool
  {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    return in_array($method, self::PROTECTED_METHODS, true);
  }

  /**
   * Obtener token CSRF actual de la cookie
   */
  public static function getToken(): ?string
  {
    return $_COOKIE[self::CSRF_COOKIE_NAME] ?? null;
  }

  /**
   * Eliminar cookie CSRF (ÃƒÂºtil en logout)
   */
  public static function clearToken(): void
  {
    $cookieOptions = Cookies::options([
      'expires' => time() - 3600,
      'httponly' => false,
    ]);

    setcookie(self::CSRF_COOKIE_NAME, '', $cookieOptions);
  }

  /**
   * Enviar respuesta de forbidden
   */
  private static function sendForbidden(string $message): void
  {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'success' => false,
      'error' => 'CSRF_TOKEN_INVALID',
      'message' => $message,
    ]);
    exit;
  }

  /**
   * Obtener el token para respuesta JSON (compatibilidad legacy)
   */
  public static function getTokenForResponse(): array
  {
    $token = self::getToken();
    return $token ? ['csrf_token' => $token] : [];
  }
}
