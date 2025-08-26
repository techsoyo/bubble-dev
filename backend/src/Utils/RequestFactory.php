<?php declare(strict_types=1);
namespace Utils;

class RequestFactory
{
  public static function fromGlobals(): Request
  {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';

    $raw = file_get_contents('php://input') ?: '';
    $body = [];
    if (stripos($contentType, 'application/json') !== false) {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) $body = $decoded;
    } else {
      // x-www-form-urlencoded o multipart
      $body = $_POST ?? [];
    }

    return new Request(
      $_GET ?? [],
      $body,
      $_FILES ?? [],
      $headers,
      $_COOKIE ?? [],
      $_SERVER['REQUEST_METHOD'] ?? 'GET',
      $_SERVER['REQUEST_URI'] ?? '/'
    );
  }
}
