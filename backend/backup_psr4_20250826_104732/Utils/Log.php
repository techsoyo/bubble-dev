<?php declare(strict_types=1);

namespace Utils\Log.php\Utils;

final class Log
{
    private const MAX_LEN = 2048;
    private const DEFAULT_FILE = __DIR__ . '/../../storage/logs/app.log';

    public static function json(string $level, array $payload): void
    {
        $level = strtolower($level);
        $cfg = strtolower(getenv('LOG_LEVEL') ?: 'info');
        if (!self::should($level, $cfg)) {
            return;
        }

        $base = [
          'ts'     => gmdate('c'),
          'level'  => $level,
          'req_id' => RequestId::get(),
          'route'  => self::route(),
          'ip'     => self::ip(),
          'ua'     => self::ua(),
        ];
        if ($cfg === 'debug') {
            $base['mem_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
        }

        // Normalizar campos mÃ­nimos esperados
        $payload = self::scrub($payload);
        if (!isset($payload['duration_ms'])) {
            $payload['duration_ms'] = null;
        }
        if (!isset($payload['outcome'])) {
            // Inferir outcome si hay error_code
            $payload['outcome'] = isset($payload['error_code']) ? 'error' : 'ok';
        }
        if (!array_key_exists('error_code', $payload)) {
            $payload['error_code'] = null;
        }
        // subtimings opcional
        $merged = $base + $payload;

        $json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        self::writeLine($json);
    }

    private static function should(string $lvl, string $cfg): bool
    {
        $order = ['debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];
        $lv = $order[$lvl] ?? 99;
        $cv = $order[$cfg] ?? 1;
        return $lv >= $cv; // mayor Ã­ndice = menos verboso permitido
    }

    private static function scrub(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (preg_match('/token|secret|password|api.?key/i', $k)) {
                continue;
            } // omitir secretos
            if (is_scalar($v)) {
                $s = (string)$v;
                if (strlen($s) > self::MAX_LEN) {
                    $s = substr($s, 0, self::MAX_LEN) . 'â€¦';
                }
                $out[$k] = $s;
            } elseif (is_array($v)) {
                // Recur y si se excede longitud total se mantiene estructura truncando valores internos
                $out[$k] = self::scrub($v);
            } else {
                $out[$k] = gettype($v);
            }
        }
        return $out;
    }

    private static function route(): string
    {
        return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    }
    private static function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                return trim(explode(',', $_SERVER[$h])[0]);
            }
        }
        return '0.0.0.0';
    }
    private static function ua(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strlen($ua) > 180 ? substr($ua, 0, 180) . 'â€¦' : $ua;
    }

    private static function writeLine(string $line): void
    {
        $custom = getenv('LOG_FILE');
        $file = $custom ?: self::DEFAULT_FILE;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // Intentar escritura atÃ³mica
        if (($fh = @fopen($file, 'ab')) !== false) {
            @flock($fh, LOCK_EX);
            @fwrite($fh, $line . PHP_EOL);
            @flock($fh, LOCK_UN);
            @fclose($fh);
        } else {
            // Fallback al error_log
            error_log($line);
        }
    }
}
