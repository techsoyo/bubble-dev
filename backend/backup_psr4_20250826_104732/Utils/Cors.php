<?php declare(strict_types=1);

namespace Utils\Cors.php\Utils;

/**
 * CORS Manager - DEPRECATED
 * CORS ahora se configura automÃ¡ticamente en bootstrap.php
 * Esta clase se mantiene por compatibilidad pero ya no es necesaria.
 */
final class Cors
{
    /**
     * DEPRECATED - CORS se configura automÃ¡ticamente en bootstrap.php
     */
    public static function enforce(): void
    {
        // CORS ya configurado en bootstrap.php - mÃ©todo mantenido por compatibilidad
        if (function_exists('error_log')) {
            error_log('DEPRECATION WARNING: Cors::enforce() ya no es necesario. CORS se configura automÃ¡ticamente en bootstrap.php');
        }
    }
}
