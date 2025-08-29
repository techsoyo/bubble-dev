<?php

declare(strict_types=1);

namespace Services;

use Utils\Logger;

/**
 * RateLimitService - Servicio global de control de tasa
 *
 * @package Services
 * @author Bubble Talents Development Team
 * @version 1.0.0
 */
class RateLimitService
{
  private static $rateLimitFile = __DIR__ . '/../../storage/rate_limits.json';
  private static $maxAttempts = [
    'login' => ['attempts' => 5, 'window' => 900],     // 5 intentos por 15 min
    'password_reset' => ['attempts' => 3, 'window' => 3600], // 3 por hora
    'api_general' => ['attempts' => 100, 'window' => 3600], // 100 por hora
    'api_sensitive' => ['attempts' => 20, 'window' => 3600], // 20 por hora
  ];

  /**
   * Verificar si una operación puede realizarse
   */
  public static function canPerform(string $operation, string $identifier): bool
  {
    $limits = self::loadLimits();
    $now = time();

    if (!isset(self::$maxAttempts[$operation])) {
      return true; // Operación no limitada
    }

    $config = self::$maxAttempts[$operation];
    $key = $operation . '_' . $identifier;

    if (!isset($limits[$key])) {
      return true;
    }

    // Limpiar entradas expiradas
    $limits[$key] = array_filter($limits[$key], function ($timestamp) use ($now, $config) {
      return ($now - $timestamp) < $config['window'];
    });

    // Verificar límite
    $canPerform = count($limits[$key]) < $config['attempts'];

    if (!$canPerform) {
      Logger::warning('Rate limit exceeded', [
        'operation' => $operation,
        'identifier' => $identifier,
        'attempts' => count($limits[$key]),
        'limit' => $config['attempts'],
        'window_seconds' => $config['window']
      ]);
    }

    return $canPerform;
  }

  /**
   * Registrar un intento de operación
   */
  public static function recordAttempt(string $operation, string $identifier): void
  {
    $limits = self::loadLimits();
    $key = $operation . '_' . $identifier;
    $now = time();

    if (!isset($limits[$key])) {
      $limits[$key] = [];
    }

    $limits[$key][] = $now;
    self::saveLimits($limits);
  }

  /**
   * Obtener tiempo de espera restante
   */
  public static function getRetryAfter(string $operation, string $identifier): int
  {
    $limits = self::loadLimits();
    $now = time();
    $key = $operation . '_' . $identifier;

    if (!isset($limits[$key]) || !isset(self::$maxAttempts[$operation])) {
      return 0;
    }

    $config = self::$maxAttempts[$operation];
    $validAttempts = array_filter($limits[$key], function ($timestamp) use ($now, $config) {
      return ($now - $timestamp) < $config['window'];
    });

    if (count($validAttempts) < $config['attempts']) {
      return 0;
    }

    // Calcular tiempo hasta que expire el intento más antiguo
    sort($validAttempts);
    $oldestAttempt = $validAttempts[0];
    return max(0, ($oldestAttempt + $config['window']) - $now);
  }

  /**
   * Limpiar límites expirados
   */
  public static function cleanup(): void
  {
    $limits = self::loadLimits();
    $now = time();

    foreach ($limits as $key => $attempts) {
      $operation = explode('_', $key)[0];
      if (isset(self::$maxAttempts[$operation])) {
        $window = self::$maxAttempts[$operation]['window'];
        $limits[$key] = array_filter($attempts, function ($timestamp) use ($now, $window) {
          return ($now - $timestamp) < $window;
        });

        if (empty($limits[$key])) {
          unset($limits[$key]);
        }
      }
    }

    self::saveLimits($limits);
  }

  private static function loadLimits(): array
  {
    if (!file_exists(self::$rateLimitFile)) {
      return [];
    }

    $data = json_decode(file_get_contents(self::$rateLimitFile), true);
    return $data ?: [];
  }

  private static function saveLimits(array $limits): void
  {
    $dir = dirname(self::$rateLimitFile);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }

    file_put_contents(self::$rateLimitFile, json_encode($limits, JSON_PRETTY_PRINT));
  }
}
