<?php

namespace Utils;

class Request
{
    /** Devuelve el cuerpo JSON como array; lanza si es inválido */
    public static function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('JSON inválido en el cuerpo de la petición');
        }
        return is_array($data) ? $data : [];
    }

    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function header(string $name, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    /** Solo el token Bearer (sin verificar) */
    public static function bearer(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? null;
        if (!$auth && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $auth = $headers['Authorization'];
            }
        }
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** Verifica el token y devuelve payload (lanza 401 si no es válido) */
    public static function authUser(array $options = []): array
    {
        $token = self::bearer();
        if (!$token) {
            throw new \RuntimeException('Authorization Bearer token requerido');
        }

        $defaults = [
          'issuer'         => config('JWT_ISSUER', 'bubble-talents-api'),
          'audience'       => config('JWT_AUDIENCE', 'bubble-talents-app'),
          'time_tolerance' => 300,
        ];
        $opts = array_replace($defaults, $options);

        $payload = \Utils\JWT::verify($token, $opts);
        if ($payload === false) {
            throw new \RuntimeException('Token inválido o expirado');
        }
        return $payload;
    }

    /* ==== Versión OO, por si la usas en endpoints ==== */

    public function getUser(): ?array
    {
        $token = $this->getAuthToken();
        if (!$token) {
            return null;
        }

        $opts = [
          'issuer'         => config('JWT_ISSUER', 'bubble-talents-api'),
          'audience'       => config('JWT_AUDIENCE', 'bubble-talents-app'),
          'time_tolerance' => 300,
        ];
        try {
            $payload = \Utils\JWT::verify($token, $opts);
            return $payload === false ? null : $payload;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getUserOrFail(array $options = []): array
    {
        $token = $this->getAuthToken();
        if (!$token) {
            throw new \RuntimeException('Authorization Bearer token requerido');
        }

        $defaults = [
          'issuer'         => config('JWT_ISSUER', 'bubble-talents-api'),
          'audience'       => config('JWT_AUDIENCE', 'bubble-talents-app'),
          'time_tolerance' => 300,
        ];
        $opts = array_replace($defaults, $options);

        $payload = \Utils\JWT::verify($token, $opts);
        if ($payload === false) {
            throw new \RuntimeException('Token inválido o expirado');
        }
        return $payload;
    }

    private function getAuthToken(): ?string
    {
        return self::bearer();
    }
}
