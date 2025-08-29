<?php

declare(strict_types=1);

namespace Services;

use Utils\Logger;

/**
 * SecurityLoggerService - Servicio centralizado de logging de seguridad
 *
 * @package Services
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */
class SecurityLoggerService
{
  private static $securityLogFile = __DIR__ . '/../../logs/security_events.log';

  /**
   * Registrar evento de seguridad
   */
  public static function logSecurityEvent(string $event, array $data = [], string $severity = 'INFO'): void
  {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $sessionId = session_id() ?: 'no_session';

    $logEntry = [
      'timestamp' => $timestamp,
      'event' => $event,
      'severity' => $severity,
      'ip_address' => $ip,
      'user_agent' => substr($userAgent, 0, 200),
      'session_id' => $sessionId,
      'data' => $data
    ];

    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    // Crear directorio si no existe
    $logDir = dirname(self::$securityLogFile);
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, true);
    }

    file_put_contents(self::$securityLogFile, $logLine, FILE_APPEND | LOCK_EX);

    // También loggear con el Logger general si está disponible
    if (class_exists('\Utils\Logger')) {
      $method = strtolower($severity);
      if (method_exists('\Utils\Logger', $method)) {
        \Utils\Logger::$method('Security Event: ' . $event, $data);
      }
    }
  }

  /**
   * Registrar intento de login
   */
  public static function logLoginAttempt(string $email, bool $success, string $ip, string $userAgent = ''): void
  {
    $event = $success ? 'login_success' : 'login_failed';
    $severity = $success ? 'INFO' : 'WARNING';

    self::logSecurityEvent($event, [
      'email' => $email,
      'ip_address' => $ip,
      'user_agent' => $userAgent,
      'timestamp' => time()
    ], $severity);
  }

  /**
   * Registrar cambio de contraseña
   */
  public static function logPasswordChange(string $userId, bool $success, string $ip): void
  {
    $event = $success ? 'password_change_success' : 'password_change_failed';
    $severity = $success ? 'INFO' : 'WARNING';

    self::logSecurityEvent($event, [
      'user_id' => $userId,
      'ip_address' => $ip,
      'timestamp' => time()
    ], $severity);
  }

  /**
   * Registrar actividad sospechosa
   */
  public static function logSuspiciousActivity(string $activity, array $details = [], string $ip = ''): void
  {
    self::logSecurityEvent('suspicious_activity', array_merge([
      'activity' => $activity,
      'ip_address' => $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
      'timestamp' => time()
    ], $details), 'ALERT');
  }

  /**
   * Registrar violación de rate limit
   */
  public static function logRateLimitViolation(string $operation, string $identifier, int $attempts, int $limit): void
  {
    self::logSecurityEvent('rate_limit_exceeded', [
      'operation' => $operation,
      'identifier' => $identifier,
      'attempts' => $attempts,
      'limit' => $limit,
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'timestamp' => time()
    ], 'WARNING');
  }

  /**
   * Registrar error de validación
   */
  public static function logValidationError(string $field, string $error, string $value = '', string $ip = ''): void
  {
    self::logSecurityEvent('validation_error', [
      'field' => $field,
      'error' => $error,
      'value_length' => strlen($value),
      'ip_address' => $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
      'timestamp' => time()
    ], 'ERROR');
  }

  /**
   * Obtener estadísticas de seguridad
   */
  public static function getSecurityStats(int $hours = 24): array
  {
    if (!file_exists(self::$securityLogFile)) {
      return [];
    }

    $stats = [
      'login_attempts' => 0,
      'login_success' => 0,
      'login_failed' => 0,
      'password_changes' => 0,
      'rate_limit_violations' => 0,
      'suspicious_activities' => 0,
      'validation_errors' => 0
    ];

    $cutoff = time() - ($hours * 3600);
    $lines = file(self::$securityLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
      $entry = json_decode($line, true);
      if (!$entry || !isset($entry['timestamp'])) {
        continue;
      }

      $entryTime = strtotime($entry['timestamp']);
      if ($entryTime < $cutoff) {
        continue;
      }

      switch ($entry['event']) {
        case 'login_success':
          $stats['login_attempts']++;
          $stats['login_success']++;
          break;
        case 'login_failed':
          $stats['login_attempts']++;
          $stats['login_failed']++;
          break;
        case 'password_change_success':
        case 'password_change_failed':
          $stats['password_changes']++;
          break;
        case 'rate_limit_exceeded':
          $stats['rate_limit_violations']++;
          break;
        case 'suspicious_activity':
          $stats['suspicious_activities']++;
          break;
        case 'validation_error':
          $stats['validation_errors']++;
          break;
      }
    }

    return $stats;
  }
}
