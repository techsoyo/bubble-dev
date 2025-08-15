<?php

/**
 * Sanitizador y validador de entrada para prevenir SQL Injection
 * Implementa múltiples capas de protección
 */

class InputSanitizer
{
  // Patrones de SQL injection conocidos
  private static $sqlPatterns = [
    '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',           // Meta caracteres SQL
    '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',  // Inyecciones típicas
    '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i', // union + select
    '/((\%27)|(\'))union/i',                      // union
    '/exec(\s|\+)+(s|x)p\w+/i',                   // stored procedures
    '/UNION(?:\s+ALL)?\s+SELECT/i',               // union select
    '/INSERT\s+INTO/i',                           // insert
    '/DELETE\s+FROM/i',                           // delete
    '/UPDATE\s+\w+\s+SET/i',                      // update
    '/DROP\s+(TABLE|DATABASE)/i',                 // drop
    '/CREATE\s+(TABLE|DATABASE)/i',               // create
    '/ALTER\s+TABLE/i',                           // alter
    '/TRUNCATE\s+TABLE/i',                        // truncate
  ];

  // Caracteres especiales peligrosos
  private static $dangerousChars = [
    "'",
    '"',
    '\\',
    '/',
    '*',
    ';',
    '=',
    '<',
    '>',
    '(',
    ')',
    '[',
    ']',
    '{',
    '}',
    '$',
    '&',
    '|'
  ];

  /**
   * Sanitizar email de forma segura (sin modificar el contenido)
   */
  public static function sanitizeEmail($email)
  {
    // Remover espacios y convertir a minúsculas
    $email = trim(strtolower($email));

    // Validar formato básico
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return false;
    }

    // Verificar patrones maliciosos
    if (self::containsSQLInjection($email)) {
      error_log("Intento de SQL injection detectado en email: $email");
      return false;
    }

    // NO escapar - devolver email limpio sin modificar
    return $email;
  }

  /**
   * Sanitizar texto general
   */
  public static function sanitizeText($text, $maxLength = 255)
  {
    // Limitar longitud
    $text = substr($text, 0, $maxLength);

    // Remover caracteres de control
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

    // Verificar patrones maliciosos
    if (self::containsSQLInjection($text)) {
      error_log("Intento de SQL injection detectado en texto: $text");
      return false;
    }

    // Escapar HTML
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    return trim($text);
  }

  /**
   * Sanitizar ID numérico
   */
  public static function sanitizeId($id)
  {
    // Convertir a entero
    $id = intval($id);

    // Verificar que sea positivo
    if ($id <= 0) {
      return false;
    }

    return $id;
  }

  /**
   * Detectar intentos de SQL injection
   */
  private static function containsSQLInjection($input)
  {
    foreach (self::$sqlPatterns as $pattern) {
      if (preg_match($pattern, $input)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Escapar para base de datos (usar con PDO es mejor)
   */
  private static function escapeForDatabase($input)
  {
    // Esto es un fallback - PDO preparadas es la mejor opción
    return addslashes($input);
  }

  /**
   * Validar y sanitizar datos de login
   */
  public static function sanitizeLoginData($data)
  {
    $result = [
      'email' => false,
      'password' => false,
      'errors' => []
    ];

    // Sanitizar email
    if (isset($data['email'])) {
      $email = trim(strtolower($data['email']));

      // Validar formato básico
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['errors'][] = 'Email inválido';
      } else if (self::containsSQLInjection($email)) {
        $result['errors'][] = 'Email contiene caracteres peligrosos';
        error_log("Intento de SQL injection detectado en email: $email");
      } else {
        $result['email'] = $email; // NO usar addslashes para email
      }
    }

    // Verificar contraseña (no sanitizar, solo validar)
    if (isset($data['password'])) {
      $password = $data['password'];

      // Verificar longitud mínima
      if (strlen($password) < 3) {
        $result['errors'][] = 'Contraseña demasiado corta';
      } else if (strlen($password) > 255) {
        $result['errors'][] = 'Contraseña demasiado larga';
      } else if (self::containsSQLInjection($password)) {
        $result['errors'][] = 'Contraseña contiene caracteres no permitidos';
        error_log("Intento de SQL injection en contraseña desde IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
      } else {
        $result['password'] = $password;
      }
    }

    return $result;
  }

  /**
   * Detectar intentos de ataque por headers
   */
  public static function detectMaliciousHeaders()
  {
    $suspiciousHeaders = [
      'HTTP_USER_AGENT',
      'HTTP_REFERER',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_REAL_IP',
      'HTTP_COOKIE'
    ];

    foreach ($suspiciousHeaders as $header) {
      if (isset($_SERVER[$header])) {
        $value = $_SERVER[$header];
        if (self::containsSQLInjection($value)) {
          error_log("Header malicioso detectado - $header: $value");
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Log de intentos de ataque
   */
  public static function logSecurityEvent($type, $data)
  {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');

    $logEntry = "[$timestamp] SECURITY: $type - IP: $ip - UA: $userAgent - Data: " . json_encode($data);
    error_log($logEntry);

    // También podrías guardarlo en una tabla de seguridad
    // self::saveToSecurityLog($type, $ip, $data);
  }
}

/**
 * Funciones helper para uso rápido
 */
function secure_email($email)
{
  return InputSanitizer::sanitizeEmail($email);
}

function secure_text($text, $maxLength = 255)
{
  return InputSanitizer::sanitizeText($text, $maxLength);
}

function secure_id($id)
{
  return InputSanitizer::sanitizeId($id);
}
