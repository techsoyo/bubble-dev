<?php

declare(strict_types=1);

namespace Services;

use Services\SecurityLoggerService;

/**
 * ValidationService - Servicio centralizado de validación y sanitización
 *
 * @package Services
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */
class ValidationService
{
  /**
   * Validar y sanitizar email
   */
  public static function validateEmail(string $email, bool $required = true): array
  {
    $email = trim($email);

    if ($required && empty($email)) {
      SecurityLoggerService::logValidationError('email', 'Email requerido');
      return ['valid' => false, 'error' => 'Email requerido', 'sanitized' => ''];
    }

    if (!$required && empty($email)) {
      return ['valid' => true, 'error' => '', 'sanitized' => ''];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      SecurityLoggerService::logValidationError('email', 'Formato de email inválido', $email);
      return ['valid' => false, 'error' => 'Formato de email inválido', 'sanitized' => ''];
    }

    // Verificar longitud
    if (strlen($email) > 254) {
      SecurityLoggerService::logValidationError('email', 'Email demasiado largo', $email);
      return ['valid' => false, 'error' => 'Email demasiado largo', 'sanitized' => ''];
    }

    // Verificar dominio básico
    $domain = substr(strrchr($email, "@"), 1);
    if (empty($domain) || strlen($domain) < 4) {
      SecurityLoggerService::logValidationError('email', 'Dominio de email inválido', $email);
      return ['valid' => false, 'error' => 'Dominio de email inválido', 'sanitized' => ''];
    }

    // Lista negra de dominios temporales
    $blockedDomains = [
      '10minutemail.com',
      'guerrillamail.com',
      'mailinator.com',
      'temp-mail.org',
      'throwaway.email',
      'yopmail.com',
      'maildrop.cc',
      'tempail.com'
    ];

    if (in_array(strtolower($domain), $blockedDomains)) {
      SecurityLoggerService::logSuspiciousActivity('EMAIL_FROM_BLOCKED_DOMAIN', [
        'domain' => $domain,
        'email' => $email
      ]);
      return ['valid' => false, 'error' => 'Dominio de email no permitido', 'sanitized' => ''];
    }

    return ['valid' => true, 'error' => '', 'sanitized' => strtolower($email)];
  }

  /**
   * Validar y sanitizar contraseña
   */
  public static function validatePassword(string $password, bool $required = true): array
  {
    if ($required && empty($password)) {
      SecurityLoggerService::logValidationError('password', 'Contraseña requerida');
      return ['valid' => false, 'error' => 'Contraseña requerida', 'sanitized' => ''];
    }

    if (!$required && empty($password)) {
      return ['valid' => true, 'error' => '', 'sanitized' => ''];
    }

    // Verificar longitud mínima
    if (strlen($password) < 8) {
      SecurityLoggerService::logValidationError('password', 'Contraseña debe tener al menos 8 caracteres');
      return ['valid' => false, 'error' => 'Contraseña debe tener al menos 8 caracteres', 'sanitized' => ''];
    }

    // Verificar longitud máxima
    if (strlen($password) > 128) {
      SecurityLoggerService::logValidationError('password', 'Contraseña demasiado larga');
      return ['valid' => false, 'error' => 'Contraseña demasiado larga', 'sanitized' => ''];
    }

    // Verificar complejidad
    $hasLower = preg_match('/[a-z]/', $password);
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasDigit = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password);

    if (!$hasLower || !$hasUpper || !$hasDigit) {
      SecurityLoggerService::logValidationError('password', 'Contraseña debe contener mayúsculas, minúsculas y números');
      return ['valid' => false, 'error' => 'Contraseña debe contener mayúsculas, minúsculas y números', 'sanitized' => ''];
    }

    // Verificar contraseñas comunes (lista básica)
    $commonPasswords = [
      'password',
      '123456',
      '123456789',
      'qwerty',
      'abc123',
      'password123',
      'admin',
      'letmein',
      'welcome',
      'monkey',
      '1234567890',
      'iloveyou',
      'princess',
      'rockyou',
      '1234567',
      '12345678',
      'password1',
      '123123'
    ];

    if (in_array(strtolower($password), $commonPasswords)) {
      SecurityLoggerService::logSuspiciousActivity('WEAK_PASSWORD_DETECTED', [
        'password_length' => strlen($password),
        'has_special_chars' => $hasSpecial
      ]);
      return ['valid' => false, 'error' => 'Contraseña demasiado común', 'sanitized' => ''];
    }

    return ['valid' => true, 'error' => '', 'sanitized' => $password];
  }

  /**
   * Validar y sanitizar nombre
   */
  public static function validateName(string $name, string $field = 'name', bool $required = true): array
  {
    $name = trim($name);

    if ($required && empty($name)) {
      SecurityLoggerService::logValidationError($field, 'Campo requerido');
      return ['valid' => false, 'error' => 'Campo requerido', 'sanitized' => ''];
    }

    if (!$required && empty($name)) {
      return ['valid' => true, 'error' => '', 'sanitized' => ''];
    }

    // Verificar longitud
    if (strlen($name) < 2) {
      SecurityLoggerService::logValidationError($field, 'Nombre demasiado corto');
      return ['valid' => false, 'error' => 'Nombre demasiado corto', 'sanitized' => ''];
    }

    if (strlen($name) > 100) {
      SecurityLoggerService::logValidationError($field, 'Nombre demasiado largo');
      return ['valid' => false, 'error' => 'Nombre demasiado largo', 'sanitized' => ''];
    }

    // Solo permitir letras, espacios, guiones y apóstrofes
    if (!preg_match('/^[a-zA-Z\s\-\'áéíóúÁÉÍÓÚñÑ]+$/', $name)) {
      SecurityLoggerService::logValidationError($field, 'Nombre contiene caracteres inválidos');
      return ['valid' => false, 'error' => 'Nombre contiene caracteres inválidos', 'sanitized' => ''];
    }

    // Capitalizar primera letra de cada palabra
    $sanitized = ucwords(strtolower($name));

    return ['valid' => true, 'error' => '', 'sanitized' => $sanitized];
  }

  /**
   * Validar y sanitizar entrada general
   */
  public static function validateInput(string $input, string $type, array $options = []): array
  {
    $input = trim($input);
    $required = $options['required'] ?? true;
    $maxLength = $options['max_length'] ?? 255;
    $minLength = $options['min_length'] ?? 1;

    if ($required && empty($input)) {
      SecurityLoggerService::logValidationError($type, 'Campo requerido');
      return ['valid' => false, 'error' => 'Campo requerido', 'sanitized' => ''];
    }

    if (!$required && empty($input)) {
      return ['valid' => true, 'error' => '', 'sanitized' => ''];
    }

    if (strlen($input) < $minLength) {
      SecurityLoggerService::logValidationError($type, "Longitud mínima: {$minLength} caracteres");
      return ['valid' => false, 'error' => "Longitud mínima: {$minLength} caracteres", 'sanitized' => ''];
    }

    if (strlen($input) > $maxLength) {
      SecurityLoggerService::logValidationError($type, "Longitud máxima: {$maxLength} caracteres");
      return ['valid' => false, 'error' => "Longitud máxima: {$maxLength} caracteres", 'sanitized' => ''];
    }

    // Sanitizar basado en tipo
    switch ($type) {
      case 'email':
        return self::validateEmail($input, $required);
      case 'password':
        return self::validatePassword($input, $required);
      case 'name':
      case 'first_name':
      case 'last_name':
        return self::validateName($input, $type, $required);
      default:
        // Sanitización básica
        $sanitized = filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return ['valid' => true, 'error' => '', 'sanitized' => $sanitized];
    }
  }

  /**
   * Validar array de campos
   */
  public static function validateFields(array $fields, array $rules): array
  {
    $errors = [];
    $sanitized = [];

    foreach ($rules as $field => $rule) {
      if (!isset($fields[$field])) {
        if (($rule['required'] ?? true)) {
          $errors[$field] = 'Campo requerido';
          SecurityLoggerService::logValidationError($field, 'Campo requerido');
        }
        continue;
      }

      $validation = self::validateInput($fields[$field], $rule['type'] ?? 'text', $rule);
      if (!$validation['valid']) {
        $errors[$field] = $validation['error'];
      } else {
        $sanitized[$field] = $validation['sanitized'];
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'sanitized' => $sanitized
    ];
  }
}
