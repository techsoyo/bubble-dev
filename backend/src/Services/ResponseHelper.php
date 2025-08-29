<?php

declare(strict_types=1);

namespace Services;

/**
 * Helper para respuestas JSON estándar
 * Provee métodos estáticos: success, error, json, getJsonInput, log
 */
class ResponseHelper
{
  public static function json($payload, int $status = 200): void
  {
    if (!headers_sent()) {
      http_response_code($status);
      header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  public static function success(string $message, $data = null, int $status = 200): void
  {
    $payload = ['success' => true, 'message' => $message];
    if ($data !== null) {
      $payload['data'] = $data;
    }
    self::json($payload, $status);
  }

  public static function error(string $message, $data = null, int $status = 400): void
  {
    $payload = ['success' => false, 'message' => $message];
    if ($data !== null) {
      $payload['errors'] = $data;
    }
    self::json($payload, $status);
  }

  /**
   * Obtener JSON de la request como array asociativo.
   * Devuelve null si JSON inválido o no recibido.
   */
  public static function getJsonInput(): ?array
  {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
      return null;
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return null;
    }
    return $data;
  }

  /**
   * Logging ligero; si existe Utils\Logger lo delega.
   */
  public static function log(string $level, string $message, array $context = []): void
  {
    if (class_exists('\\Utils\\Logger')) {
      try {
        $lvl = strtolower($level);
        switch ($lvl) {
          case 'security':
            // Logger::security expects (string $event, array $details = [])
            \Utils\Logger::security($message, $context);
            return;
          case 'emergency':
            \Utils\Logger::emergency($message, $context);
            return;
          case 'alert':
            \Utils\Logger::alert($message, $context);
            return;
          case 'critical':
            \Utils\Logger::critical($message, $context);
            return;
          case 'error':
            \Utils\Logger::error($message, $context);
            return;
          case 'warning':
            \Utils\Logger::warning($message, $context);
            return;
          case 'notice':
            \Utils\Logger::notice($message, $context);
            return;
          case 'debug':
            \Utils\Logger::debug($message, $context);
            return;
          case 'info':
          default:
            \Utils\Logger::info($message, $context);
            return;
        }
      } catch (\Throwable $e) {
        // fallback to error_log
      }
    }

    error_log(strtoupper($level) . ': ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
  }
}

// Crear alias para compatibilidad con código existente que usa \Utils\ResponseHelper
if (!class_exists('\Utils\\ResponseHelper')) {
  class_alias('\Services\\ResponseHelper', '\Utils\\ResponseHelper');
}
