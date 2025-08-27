<?php

declare(strict_types=1);

namespace Middleware;

final class CsrfMiddleware
{
  public static function protect(): void
  {
    // No aplica a CLI
    if (PHP_SAPI === 'cli') return;

    // Solo métodos mutadores
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;

    $cookie = (string)($_COOKIE['csrf_token'] ?? '');
    $header = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    // Validación estricta
    if ($cookie === '' || $header === '' || !hash_equals($cookie, $header)) {
      if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
      }
      http_response_code(419);
      echo json_encode(['error' => 'CSRF token mismatch']);
      exit;
    }
  }

  public static function ensureToken(): void
  {
    if (PHP_SAPI === 'cli') return;

    if (!isset($_COOKIE['csrf_token']) || trim((string)$_COOKIE['csrf_token']) === '') {
      $token  = bin2hex(random_bytes(32));
      $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

      setcookie('csrf_token', $token, [
        'expires'  => time() + 86400,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => false, // FE debe leerla para X-CSRF-Token
        'samesite' => 'Lax',
      ]);

      if (!headers_sent()) {
        header('Set-Cookie: csrf_token=' . $token . '; Path=/; ' .
          ($secure ? 'Secure; ' : '') . 'SameSite=Lax');
      }
    }
  }
}
