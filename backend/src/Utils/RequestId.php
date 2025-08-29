<?php

declare(strict_types=1);

namespace Utils;

final class RequestId
{
    private static ?string $id = null;

    public static function init(): void
    {
        if (self::$id !== null) {
            return;
        }
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        $use = self::isValidId($incoming) ? $incoming : self::generate();
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
        // Usar timestamp + random para garantizar unicidad
        return (string)(time() . rand(1000, 9999));
    }

    private static function isValidId(string $v): bool
    {
        return is_numeric($v) && (int)$v > 0;
    }
}
