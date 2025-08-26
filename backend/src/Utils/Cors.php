<?php declare(strict_types=1);
namespace Utils;

/**
 * CORS Manager - DEPRECATED
 * CORS ahora se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
 * Esta clase se mantiene por compatibilidad pero ya no es necesaria.
 */
final class Cors
{
    /**
     * DEPRECATED - CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php
     */
    public static function enforce(): void
    {
        // CORS ya configurado en bootstrap.php - mÃƒÆ’Ã‚Â©todo mantenido por compatibilidad
        if (function_exists('error_log')) {
            error_log('DEPRECATION WARNING: Cors::enforce() ya no es necesario. CORS se configura automÃƒÆ’Ã‚Â¡ticamente en bootstrap.php');
        }
    }
}
