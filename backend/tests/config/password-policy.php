<?php

/**
 * Validador de políticas de contraseñas seguras
 * Implementa estándares de seguridad OWASP
 */

class PasswordPolicyValidator
{
  // Configuración de políticas
  const MIN_LENGTH = 8;
  const MAX_LENGTH = 128;
  const REQUIRE_UPPERCASE = true;
  const REQUIRE_LOWERCASE = true;
  const REQUIRE_NUMBERS = true;
  const REQUIRE_SPECIAL_CHARS = true;
  const MIN_SPECIAL_CHARS = 1;

  // Lista de contraseñas comunes prohibidas
  private static $commonPasswords = [
    '123456',
    'password',
    '123456789',
    '12345678',
    '12345',
    '1234567',
    '1234567890',
    'qwerty',
    'abc123',
    '111111',
    'password1',
    'admin',
    'letmein',
    'welcome',
    'monkey',
    'login',
    'dragon',
    'passw0rd',
    'master',
    'hello',
    'freedom',
    'whatever',
    'qazwsx',
    'trustno1',
    'ranger'
  ];

  /**
   * Validar contraseña contra todas las políticas
   */
  public static function validate($password)
  {
    $errors = [];

    // Verificar longitud
    if (strlen($password) < self::MIN_LENGTH) {
      $errors[] = "La contraseña debe tener al menos " . self::MIN_LENGTH . " caracteres";
    }

    if (strlen($password) > self::MAX_LENGTH) {
      $errors[] = "La contraseña no puede exceder " . self::MAX_LENGTH . " caracteres";
    }

    // Verificar mayúsculas
    if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
      $errors[] = "La contraseña debe incluir al menos una letra mayúscula";
    }

    // Verificar minúsculas
    if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
      $errors[] = "La contraseña debe incluir al menos una letra minúscula";
    }

    // Verificar números
    if (self::REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
      $errors[] = "La contraseña debe incluir al menos un número";
    }

    // Verificar caracteres especiales
    if (self::REQUIRE_SPECIAL_CHARS) {
      $specialCount = preg_match_all('/[^a-zA-Z0-9]/', $password);
      if ($specialCount < self::MIN_SPECIAL_CHARS) {
        $errors[] = "La contraseña debe incluir al menos " . self::MIN_SPECIAL_CHARS . " caracter(es) especial(es)";
      }
    }

    // Verificar contraseñas comunes
    if (in_array(strtolower($password), array_map('strtolower', self::$commonPasswords))) {
      $errors[] = "Esta contraseña es demasiado común y no está permitida";
    }

    // Verificar patrones secuenciales
    if (self::hasSequentialPattern($password)) {
      $errors[] = "La contraseña no puede contener secuencias largas como '1234' o 'abcd'";
    }

    // Verificar repeticiones
    if (self::hasRepeatingPattern($password)) {
      $errors[] = "La contraseña no puede contener muchos caracteres repetidos";
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'strength' => self::calculateStrength($password)
    ];
  }

  /**
   * Detectar patrones secuenciales (más de 3 caracteres consecutivos)
   */
  private static function hasSequentialPattern($password)
  {
    // Buscar secuencias de 4 o más caracteres (más restrictivo pero realista)
    for ($i = 0; $i < strlen($password) - 3; $i++) {
      $char1 = ord($password[$i]);
      $char2 = ord($password[$i + 1]);
      $char3 = ord($password[$i + 2]);
      $char4 = ord($password[$i + 3]);

      if ($char2 == $char1 + 1 && $char3 == $char2 + 1 && $char4 == $char3 + 1) {
        return true; // Secuencia ascendente de 4+
      }

      if ($char2 == $char1 - 1 && $char3 == $char2 - 1 && $char4 == $char3 - 1) {
        return true; // Secuencia descendente de 4+
      }
    }

    return false;
  }

  /**
   * Detectar patrones repetitivos
   */
  private static function hasRepeatingPattern($password)
  {
    // Verificar si más del 50% de la contraseña son caracteres repetidos
    $chars = array_count_values(str_split($password));
    $maxRepeat = max($chars);

    return ($maxRepeat / strlen($password)) > 0.5;
  }

  /**
   * Calcular fuerza de la contraseña (0-100)
   */
  private static function calculateStrength($password)
  {
    $score = 0;
    $length = strlen($password);

    // Puntos por longitud
    $score += min($length * 4, 40);

    // Puntos por variedad de caracteres
    if (preg_match('/[a-z]/', $password)) $score += 5;
    if (preg_match('/[A-Z]/', $password)) $score += 5;
    if (preg_match('/[0-9]/', $password)) $score += 5;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 10;

    // Puntos por complejidad
    $uniqueChars = count(array_unique(str_split($password)));
    $score += ($uniqueChars / $length) * 20;

    // Penalizar patrones comunes
    if (self::hasSequentialPattern($password)) $score -= 20;
    if (self::hasRepeatingPattern($password)) $score -= 20;
    if (in_array(strtolower($password), array_map('strtolower', self::$commonPasswords))) $score -= 30;

    return max(0, min(100, $score));
  }

  /**
   * Obtener recomendaciones para mejorar la contraseña
   */
  public static function getRecommendations($password)
  {
    $recommendations = [];

    if (strlen($password) < 12) {
      $recommendations[] = "Usa al menos 12 caracteres para mayor seguridad";
    }

    if (!preg_match('/[A-Z]/', $password)) {
      $recommendations[] = "Incluye letras mayúsculas";
    }

    if (!preg_match('/[a-z]/', $password)) {
      $recommendations[] = "Incluye letras minúsculas";
    }

    if (!preg_match('/[0-9]/', $password)) {
      $recommendations[] = "Incluye números";
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
      $recommendations[] = "Incluye símbolos (!@#$%^&*)";
    }

    $recommendations[] = "Evita información personal (nombres, fechas)";
    $recommendations[] = "No reutilices contraseñas de otras cuentas";

    return $recommendations;
  }
}

/**
 * Funciones helper para integración
 */
function validatePassword($password)
{
  return PasswordPolicyValidator::validate($password);
}

function isPasswordStrong($password)
{
  $result = PasswordPolicyValidator::validate($password);
  return $result['valid'] && $result['strength'] >= 70;
}
