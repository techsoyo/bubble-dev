<?php declare(strict_types=1);
namespace Security\Cookies.php\Security;

/**
 * Manejo centralizado de cookies para autenticaciÃƒÂ³n
 * 
 * Esta clase proporciona funciones reutilizables para el manejo seguro
 * de cookies, especialmente para tokens JWT con configuraciÃƒÂ³n httpOnly.
 * 
 * @package Security
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */
final class Cookies
{
  /**
   * Detectar si la conexiÃƒÂ³n es HTTPS considerando proxies/CDN
   */
  public static function isHttps(): bool
  {
    // DetrÃƒÂ¡s de proxy/CDN (Cloudflare, AWS ALB, etc.)
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
      return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    // Otros headers de proxy comunes
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
      return true;
    }

    // Header estÃƒÂ¡ndar de Apache/Nginx
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
      return true;
    }

    // Puerto 443 (HTTPS estÃƒÂ¡ndar)
    return (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
  }

  /**
   * Obtener opciones de cookie configuradas
   */
  public static function options(array $overrides = []): array
  {
    // Lee configuraciÃƒÂ³n de .env con defaults sensatos
    $domain   = $_ENV['COOKIE_DOMAIN']   ?? '';          // p.ej. ".acme.com" para subdominios
    $sameSite = $_ENV['COOKIE_SAMESITE'] ?? 'Lax';       // "Lax" | "Strict" | "None"

    // Determinar si usar secure
    $secure = isset($_ENV['COOKIE_SECURE'])
      ? filter_var($_ENV['COOKIE_SECURE'], FILTER_VALIDATE_BOOL)
      : self::isHttps();

    $defaults = [
      'expires'  => time() + 86400,  // 24 horas por defecto
      'path'     => '/',             // Disponible en todo el sitio
      'domain'   => $domain,         // Configurable para subdominios
      'secure'   => $secure,         // Auto-detecta HTTPS o configurable
      'httponly' => true,            // Siempre httpOnly para seguridad
      'samesite' => $sameSite,       // ProtecciÃƒÂ³n CSRF configurable
    ];

    return array_merge($defaults, $overrides);
  }

  /**
   * Establecer cookie JWT de autenticaciÃƒÂ³n
   */
  public static function setJwt(string $jwt, int $expirationTime = null): void
  {
    $options = [];

    if ($expirationTime !== null) {
      $options['expires'] = $expirationTime;
    }

    setcookie('access_token', $jwt, self::options($options));
  }

  /**
   * Eliminar cookie JWT de autenticaciÃƒÂ³n
   */
  public static function unsetJwt(): void
  {
    $opts = self::options(['expires' => time() - 3600]);
    setcookie('access_token', '', $opts);
  }

  /**
   * Obtener el valor de la cookie JWT
   */
  public static function getJwt(): ?string
  {
    return $_COOKIE['access_token'] ?? null;
  }

  /**
   * Verificar si existe una cookie JWT vÃƒÂ¡lida
   */
  public static function hasJwt(): bool
  {
    return !empty($_COOKIE['access_token']);
  }
}
