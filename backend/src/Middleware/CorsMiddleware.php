<?php

declare(strict_types=1);

namespace Middleware;

final class CorsMiddleware
{
  public static function handle(): void
  {
    // Config
    $corsOrigins = getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:3002';
    $corsCredentials = filter_var(getenv('CORS_ALLOW_CREDENTIALS'), FILTER_VALIDATE_BOOLEAN);
    $corsMethods = getenv('CORS_ALLOWED_METHODS') ?: 'GET,POST,PUT,PATCH,DELETE,OPTIONS';
    $corsHeadersConfig = getenv('CORS_ALLOWED_HEADERS') ?: 'Content-Type,Authorization,X-Requested-With';
    $corsMaxAge = (int) (getenv('CORS_MAX_AGE') ?: '86400');

    // Helper: sanitize header values to avoid CRLF injection
    $sanitize = static function (string $v): string {
      return preg_replace('/[\\r\\n]+/u', ' ', trim($v));
    };

    // Parse allowed origins list
    $rawOrigins = array_filter(array_map('trim', explode(',', $corsOrigins)));
    $allowedOrigins = array_values($rawOrigins); // reindex
    $allowAll = in_array('*', $allowedOrigins, true);

    // If credentials are allowed, wildcard '*' is not permitted
    if ($corsCredentials && $allowAll) {
      error_log('CORS misconfig: CORS_ALLOW_CREDENTIALS=true and CORS_ALLOWED_ORIGINS contains "*" - ignoring "*"');
      $allowAll = false;
      $allowedOrigins = array_filter($allowedOrigins, static function ($o) {
        return $o !== '*';
      });
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $origin = is_string($origin) ? trim($origin) : '';

    // Ensure browser doesn't cache wrong response per origin
    header('Vary: Origin');

    $isAllowedOrigin = false;
    if ($origin !== '') {
      if ($allowAll) {
        $isAllowedOrigin = true;
      } else {
        // exact-match check (safe). If you want pattern matching, implement explicitly.
        $isAllowedOrigin = in_array($origin, $allowedOrigins, true);
      }
    }

    // If origin allowed, emit CORS headers
    if ($isAllowedOrigin) {
      // sanitize origin before echoing header
      $safeOrigin = $sanitize($origin);
      header('Access-Control-Allow-Origin: ' . $safeOrigin);

      if ($corsCredentials) {
        header('Access-Control-Allow-Credentials: true');
      }

      // Allow headers: prefer what the client requests in preflight
      $reqHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
      $reqHeaders = is_string($reqHeaders) ? trim($reqHeaders) : '';
      $allowHeaders = $reqHeaders !== '' ? $reqHeaders : $corsHeadersConfig;
      $allowHeaders = $sanitize($allowHeaders);
      if ($allowHeaders !== '') {
        header('Access-Control-Allow-Headers: ' . $allowHeaders);
      }

      // Methods
      header('Access-Control-Allow-Methods: ' . $sanitize($corsMethods));

      // Max age
      if ($corsMaxAge > 0) {
        header('Access-Control-Max-Age: ' . (string)$corsMaxAge);
      }

      // Expose helpful headers to browser
      header('Access-Control-Expose-Headers: X-Request-Id');
    } else {
      // Not allowed origin -> do not emit CORS headers.
      // For non-OPTIONS requests, continue normal flow (will be blocked by browser).
      // For OPTIONS (preflight) we'll return 403 to make failure explicit server-side.
    }

    // Handle preflight
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
      if ($isAllowedOrigin) {
        // Successful preflight: no body
        http_response_code(204);
        // Some servers prefer Content-Length: 0
        header('Content-Length: 0');
        exit;
      } else {
        // Explicitly reject preflight from disallowed origin
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden (CORS): origin not allowed';
        exit;
      }
    }
  }
}
