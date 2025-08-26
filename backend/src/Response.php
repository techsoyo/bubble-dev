<?php declare(strict_types=1);
namespace Src;

class Response
{
  public static function ok(array $data, int $code = 200): void
  {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
  }

  public static function created(array $data, ?string $location = null): void
  {
    if ($location) header('Location: ' . $location);
    self::ok($data, 201);
  }

  public static function error(string $message, int $code = 400, array $extra = []): void
  {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode(['error' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
  }
}
