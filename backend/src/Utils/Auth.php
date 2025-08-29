<?php

declare(strict_types=1);

namespace Utils;

/**
 * Autenticación bí¡sica Bearer configurable.
 * REQUIRE_AUTH_FOR_CONFIRM=true obliga a Authorization: Bearer <token>.
 * Si AUTH_BEARER_TOKEN estí¡ definido, debe coincidir; si no, cualquier token no vací­o se acepta (stub).
 */
final class Auth
{
    public static function enforceConfirmAuth(): void
    {
        $require = (getenv('REQUIRE_AUTH_FOR_CONFIRM') === 'true') || (($_ENV['REQUIRE_AUTH_FOR_CONFIRM'] ?? '') === 'true');
        if (!$require) {
            return;
        }
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) {
            self::deny();
        }
        $token = trim($m[1]);
        if ($token === '') {
            self::deny();
        }
        $expected = getenv('AUTH_BEARER_TOKEN') ?: ($_ENV['AUTH_BEARER_TOKEN'] ?? '');
        if ($expected !== '' && !hash_equals($expected, $token)) {
            self::deny();
        }
    }

    private static function deny(): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'AUTH_REQUIRED',
                'message' => 'Autenticación requerida',
                'details' => (object)[]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (class_exists(Log::class)) {
            Log::json('warn', ['event' => 'auth', 'tag' => 'DENY']);
        }
        exit;
    }
}
