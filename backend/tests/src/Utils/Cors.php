<?php

declare(strict_types=1);

namespace Utils;

/**
 * CORS estricto basado en ALLOWED_ORIGINS (coma separado) en .env / variables.
 * Si el Origin no está permitido => 403 CORS_FORBIDDEN.
 * Preflight OPTIONS responde 204 con cabeceras.
 */
final class Cors
{
    /**
     * Simplificado: delega totalmente en cors.php, idempotente.
     */
    public static function enforce(): void
    {
        if (defined('CORS_APPLIED') || PHP_SAPI === 'cli') {
            return;
        }
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', realpath(__DIR__ . '/..')); // Utils => src, subir uno más si fuera necesario
            if (basename(BASE_PATH) === 'Utils') {
                define('BASE_PATH', realpath(__DIR__ . '/../../')); // fallback
            }
        }
        require_once BASE_PATH . '/cors.php';
    }
}
