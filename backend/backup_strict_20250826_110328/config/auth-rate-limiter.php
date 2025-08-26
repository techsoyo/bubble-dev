<?php declare(strict_types=1);


/**
 * Sistema de Rate Limiting para autenticaciÃ³n
 * Protege contra ataques de fuerza bruta
 */

// Inicializar sesiÃ³n INMEDIATAMENTE
session_start();

// ParÃ¡metros
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW_SECONDS', 300);

/**
 * Inicializar verificaciÃ³n de rate limiting
 */
function rate_limit_init(string $ip): void
{
  if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = [];
  }

  if (!isset($_SESSION['rate_limit'][$ip])) {
    $_SESSION['rate_limit'][$ip] = ['count' => 0, 'start' => time()];
  }
}

/**
 * Registrar un intento fallido - Retorna false si supera el lÃ­mite
 */
function rate_limit_register_failure(string $ip): bool
{
  rate_limit_init($ip);
  $entry = &$_SESSION['rate_limit'][$ip];
  $now = time();

  // Ventana expirada: reinicia contador
  if ($now - $entry['start'] > RATE_LIMIT_WINDOW_SECONDS) {
    $entry = ['count' => 1, 'start' => $now];
    return true;
  }

  // Incrementa contador de fallos
  $entry['count']++;

  // Si excede mÃ¡ximo de fallos, bloquea
  if ($entry['count'] > RATE_LIMIT_MAX_ATTEMPTS) {
    return false;
  }
  return true;
}

class AuthRateLimiter
{
  private static $attempts = [];
  private static $lockouts = [];

  // ConfiguraciÃ³n
  const MAX_ATTEMPTS = 5;
  const LOCKOUT_TIME = 300; // 5 minutos
  const ATTEMPT_WINDOW = 900; // 15 minutos

  /**
   * Verificar si una IP estÃ¡ permitida para intentar login
   */
  public static function isAllowed($ip)
  {
    // Limpiar intentos antiguos
    self::cleanOldAttempts($ip);

    // Verificar si estÃ¡ bloqueado
    if (isset(self::$lockouts[$ip])) {
      if (time() < self::$lockouts[$ip]) {
        return false; // AÃºn bloqueado
      } else {
        unset(self::$lockouts[$ip]); // Desbloquear
        self::$attempts[$ip] = []; // Resetear intentos
      }
    }

    // Verificar nÃºmero de intentos
    $attempts = self::getAttempts($ip);
    return count($attempts) < self::MAX_ATTEMPTS;
  }

  /**
   * Registrar un intento fallido
   * @return int El nÃºmero actual de intentos tras incrementarlo
   */
  public static function recordFailedAttempt($ip): int
  {
    if (!isset(self::$attempts[$ip])) {
      self::$attempts[$ip] = [];
    }

    self::$attempts[$ip][] = time();
    $currentAttempts = count(self::$attempts[$ip]);

    // Si excede el lÃ­mite, bloquear
    if ($currentAttempts >= self::MAX_ATTEMPTS) {
      self::$lockouts[$ip] = time() + self::LOCKOUT_TIME;

      // Log del bloqueo
      error_log("IP bloqueada por fuerza bruta: $ip - " . date('Y-m-d H:i:s'));
    }

    return $currentAttempts;
  }

  /**
   * Resetear intentos despuÃ©s de login exitoso
   */
  public static function resetAttempts($ip)
  {
    unset(self::$attempts[$ip]);
    unset(self::$lockouts[$ip]);
  }

  /**
   * Obtener intentos de una IP
   */
  private static function getAttempts($ip)
  {
    return self::$attempts[$ip] ?? [];
  }

  /**
   * Limpiar intentos antiguos
   */
  private static function cleanOldAttempts($ip)
  {
    if (!isset(self::$attempts[$ip])) {
      return;
    }

    $cutoff = time() - self::ATTEMPT_WINDOW;
    self::$attempts[$ip] = array_filter(
      self::$attempts[$ip],
      function ($timestamp) use ($cutoff) {
        return $timestamp > $cutoff;
      }
    );
  }

  /**
   * Obtener tiempo restante de bloqueo
   */
  public static function getRemainingLockoutTime($ip)
  {
    if (!isset(self::$lockouts[$ip])) {
      return 0;
    }

    return max(0, self::$lockouts[$ip] - time());
  }

  /**
   * Obtener informaciÃ³n de estado para debugging
   */
  public static function getStatus($ip)
  {
    return [
      'attempts' => count(self::getAttempts($ip)),
      'max_attempts' => self::MAX_ATTEMPTS,
      'is_locked' => isset(self::$lockouts[$ip]) && time() < self::$lockouts[$ip],
      'lockout_remaining' => self::getRemainingLockoutTime($ip),
      'is_allowed' => self::isAllowed($ip)
    ];
  }
}

/**
 * Middleware de rate limiting para endpoints de autenticaciÃ³n
 */
function applyRateLimiting()
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

  // Verificar si la IP estÃ¡ permitida
  if (!AuthRateLimiter::isAllowed($ip)) {
    $remaining = AuthRateLimiter::getRemainingLockoutTime($ip);

    http_response_code(429); // Too Many Requests
    header("Retry-After: $remaining");
    if (function_exists('jsonResponse')) {
      jsonResponse(429, [
        'error' => 'Too many login attempts',
        'message' => 'Account temporarily locked due to multiple failed login attempts',
        'retry_after' => $remaining,
        'status' => 429
      ]);
    } else {
      echo json_encode([
        'error' => 'Too many login attempts',
        'message' => 'Account temporarily locked due to multiple failed login attempts',
        'retry_after' => $remaining,
        'status' => 429
      ]);
      exit;
    }
  }
}

/**
 * Registra un intento fallido.
 * @param string|null $ip IP address (auto-detectado si es null)
 * @return bool True=permite continuar, False=debe bloquear con 429
 */
function recordFailedLogin(?string $ip = null): bool
{
  $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

  // Usar las funciones de sesiÃ³n, NO la clase AuthRateLimiter
  rate_limit_init($ip);
  $entry = &$_SESSION['rate_limit'][$ip];
  $now = time();

  // Ventana expirada: reinicia contador
  if ($now - $entry['start'] > RATE_LIMIT_WINDOW_SECONDS) {
    $entry = ['count' => 1, 'start' => $now];
  } else {
    $entry['count']++;
  }

  // DEBUG: Log para verificar el contador
  error_log("RateLimit[$ip] count={$entry['count']} start={$entry['start']} max=" . RATE_LIMIT_MAX_ATTEMPTS);

  // Si supera el mÃ¡ximo, bloquea
  $result = ($entry['count'] <= RATE_LIMIT_MAX_ATTEMPTS);
  error_log("RateLimit[$ip] resultado: " . ($result ? 'PERMITIR' : 'BLOQUEAR'));

  return $result;
}
/**
 * Registrar login exitoso - resetea contador
 */
function recordSuccessfulLogin($ip = null)
{
  $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

  // Resetear en sesiÃ³n
  if (isset($_SESSION['rate_limit'][$ip])) {
    unset($_SESSION['rate_limit'][$ip]);
  }

  error_log("RateLimit[$ip] LOGIN EXITOSO - contador reseteado");
}
