<?php declare(strict_types=1);

namespace Utils\RequestId.php\Utils;

final class RequestId
{
    private static ?string $id = null;

    public static function init(): void
    {
        if (self::$id !== null) {
            return;
        }
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        $use = self::isValidUuidV4($incoming) ? $incoming : self::generate();
        self::$id = $use;
        header('X-Request-Id: ' . self::$id);
    }

    public static function get(): string
    {
        if (self::$id === null) {
            self::init();
        }
        return self::$id;
    }

    private static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant
        $hex = bin2hex($bytes);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    private static function isValidUuidV4(string $v): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $v);
    }
}
