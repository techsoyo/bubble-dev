<?php

namespace Security;

/**
 * Sistema de logging de eventos de seguridad
 * SEGURIDAD: Auditoria completa de eventos críticos
 */
class SecurityLogger
{
  private static $logFile = null;

  /**
   * Inicializar el logger
   */
  public static function init(): void
  {
    self::$logFile = __DIR__ . '/../../logs/security.log';

    // Crear directorio de logs si no existe
    $logDir = dirname(self::$logFile);
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, true);
    }
  }

  /**
   * Registrar evento de seguridad
   */
  public static function logSecurityEvent(string $event, array $data = [], string $level = 'INFO'): void
  {
    if (self::$logFile === null) {
      self::init();
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $logEntry = [
      'timestamp' => $timestamp,
      'level' => $level,
      'event' => $event,
      'ip' => $ip,
      'user_agent' => substr($userAgent, 0, 255), // Limitar longitud
      'data' => $data,
      'request_id' => uniqid()
    ];

    $logLine = json_encode($logEntry) . PHP_EOL;

    // Escribir al archivo de log
    file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);

    // Para eventos críticos, también log al syslog
    if (in_array($level, ['ERROR', 'CRITICAL'])) {
      error_log("SECURITY {$level}: {$event} - " . json_encode($data));
    }
  }

  /**
   * Log de autenticación exitosa
   */
  public static function logAuthSuccess(string $userId, string $role): void
  {
    self::logSecurityEvent('AUTH_SUCCESS', [
      'user_id' => $userId,
      'role' => $role
    ]);
  }

  /**
   * Log de fallo de autenticación
   */
  public static function logAuthFailure(string $reason, array $context = []): void
  {
    self::logSecurityEvent('AUTH_FAILURE', [
      'reason' => $reason,
      'context' => $context
    ], 'WARNING');
  }

  /**
   * Log de acceso denegado
   */
  public static function logAccessDenied(string $resource, string $userId = null): void
  {
    self::logSecurityEvent('ACCESS_DENIED', [
      'resource' => $resource,
      'user_id' => $userId
    ], 'WARNING');
  }

  /**
   * Log de violación de rate limiting
   */
  public static function logRateLimitViolation(string $userId, string $endpoint): void
  {
    self::logSecurityEvent('RATE_LIMIT_VIOLATION', [
      'user_id' => $userId,
      'endpoint' => $endpoint
    ], 'WARNING');
  }

  /**
   * Log de intento de path traversal
   */
  public static function logPathTraversal(string $attemptedPath): void
  {
    self::logSecurityEvent('PATH_TRAVERSAL_ATTEMPT', [
      'attempted_path' => $attemptedPath
    ], 'ERROR');
  }

  /**
   * Log de JWT inválido
   */
  public static function logInvalidJWT(string $reason, string $token = null): void
  {
    self::logSecurityEvent('INVALID_JWT', [
      'reason' => $reason,
      'token_preview' => $token ? substr($token, 0, 20) . '...' : 'none'
    ], 'WARNING');
  }

  /**
   * Log de operación admin crítica
   */
  public static function logAdminOperation(string $operation, string $adminId, array $details = []): void
  {
    self::logSecurityEvent('ADMIN_OPERATION', [
      'operation' => $operation,
      'admin_id' => $adminId,
      'details' => $details
    ]);
  }

  /**
   * Log de intento de CORS violación
   */
  public static function logCORSViolation(string $origin, string $method): void
  {
    self::logSecurityEvent('CORS_VIOLATION', [
      'origin' => $origin,
      'method' => $method
    ], 'WARNING');
  }

  /**
   * Obtener estadísticas de seguridad
   */
  public static function getSecurityStats(int $hours = 24): array
  {
    if (self::$logFile === null) {
      self::init();
    }

    if (!file_exists(self::$logFile)) {
      return ['error' => 'Log file not found'];
    }

    $lines = file(self::$logFile);
    $cutoffTime = time() - ($hours * 3600);

    $events = [];
    $levels = ['INFO' => 0, 'WARNING' => 0, 'ERROR' => 0, 'CRITICAL' => 0];

    foreach ($lines as $line) {
      $entry = json_decode($line, true);
      if (!$entry) continue;

      $entryTime = strtotime($entry['timestamp']);
      if ($entryTime < $cutoffTime) continue;

      $event = $entry['event'];
      $level = $entry['level'];

      if (!isset($events[$event])) {
        $events[$event] = 0;
      }

      $events[$event]++;
      $levels[$level]++;
    }

    return [
      'period_hours' => $hours,
      'events_by_type' => $events,
      'events_by_level' => $levels,
      'total_events' => array_sum($events)
    ];
  }
}
