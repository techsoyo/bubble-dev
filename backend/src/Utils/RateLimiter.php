<?php

declare(strict_types=1);

namespace Utils;

/**
 * RateLimiter simple basado en archivos para ventanas fijas de 10 minutos.
 * Rutas target: /api/cv/parse (10/10min) y /api/cv/confirm (20/10min).
 * Estructura de archivo: storage/ratelimit/{window}_{ipHash}.json => { route => count }
 * Pensado para ser sustituible por Redis implementando la misma interfaz pública.
 */
final class RateLimiter
{
    private const WINDOW_SECONDS = 600; // 10 min
    private const BASE_DIR = __DIR__ . '/../../storage/ratelimit';

    public static function check(string $route, string $ip, int $limit): array
    {
        $window = (int) floor(time() / self::WINDOW_SECONDS);
        $ipKey = preg_replace('/[^0-9a-f]/', '', sha1($ip));
        $dir = self::BASE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . $window . '_' . $ipKey . '.json';
        $data = [];
        $h = @fopen($file, 'c+');
        if ($h) {
            flock($h, LOCK_EX);
            $raw = stream_get_contents($h);
            if ($raw) {
                $dec = json_decode($raw, true);
                if (is_array($dec)) {
                    $data = $dec;
                }
            }
            $count = (int) ($data[$route] ?? 0);
            $count++;
            $data[$route] = $count;
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, json_encode($data));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);
        } else {
            // Si no se puede abrir archivo, permitimos (fail-open) pero registramos.
            if (class_exists(Log::class)) {
                Log::json('warn', ['event' => 'rate_limit', 'tag' => 'FILE_OPEN_FAIL']);
            }
            return ['allowed' => true, 'remaining' => $limit, 'count' => 1];
        }
        $remaining = max(0, $limit - $data[$route]);
        return [
            'allowed' => $data[$route] <= $limit,
            'remaining' => $remaining,
            'count' => $data[$route]
        ];
    }

    public static function enforceForRoute(string $route): void
    {
        $ip = self::clientIp();
        $limit = ($route === '/api/cv/parse') ? 10 : (($route === '/api/cv/confirm') ? 20 : 60);
        $res = self::check($route, $ip, $limit);
        if (!$res['allowed']) {
            http_response_code(429);
            $payload = [
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Lí­mite de solicitudes excedido',
                    'details' => [
                        'route' => $route,
                        'limit' => $limit,
                        'window_seconds' => self::WINDOW_SECONDS
                    ]
                ]
            ];
            if (class_exists(Log::class)) {
                Log::json('warn', ['event' => 'rate_limit', 'tag' => 'BLOCK', 'route' => $route, 'ip' => $ip]);
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    private static function clientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                return trim(explode(',', $_SERVER[$h])[0]);
            }
        }
        return '0.0.0.0';
    }
}
