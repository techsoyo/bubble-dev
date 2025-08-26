<?php declare(strict_types=1);


/**
 * Manejador global de errores y excepciones para producciÃ³n
 * 
 * Este archivo debe ser incluido al inicio de cada script PHP
 * para establecer un manejo uniforme y seguro de errores.
 * 
 * CaracterÃ­sticas de seguridad:
 * - No revela rutas de archivos en producciÃ³n
 * - Logs detallados solo en desarrollo
 * - Respuestas genÃ©ricas en producciÃ³n
 * - PrevenciÃ³n de exposiciÃ³n de informaciÃ³n sensible
 * 
 * @version 2.0.0
 * @author Bubble of Talents Security Team
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../src/Utils/Logger.php';
require_once __DIR__ . '/../src/Utils/ResponseHelper.php';

use Utils\Logger;
use Utils\ResponseHelper;

// Configurar manejo de errores segÃºn entorno
if (isProduction()) {
    // âœ… PRODUCCIÃ“N: ConfiguraciÃ³n segura
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    // âœ… DESARROLLO: ConfiguraciÃ³n para debugging
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
}

// Inicializar logger
Logger::init();

/**
 * Manejador de errores personalizado
 */
set_error_handler(function ($severity, $message, $file, $line) {
    // No procesar errores suprimidos con @
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $errorType = $errorTypes[$severity] ?? 'Unknown Error';

    // Filtrar archivo para no exponer rutas absolutas
    $safeFile = basename($file);

    Logger::error("PHP $errorType", [
        'message' => $message,
        'file' => $safeFile,
        'line' => $line,
        'severity' => $severity
    ]);

    // En desarrollo, mostrar errores; en producciÃ³n, solo logear
    if (function_exists('isDevelopment') && isDevelopment()) {
        return false; // Permitir que PHP muestre el error
    }

    // En producciÃ³n, suprimir mostrar errores al usuario
    return true;
});

/**
 * Manejador de excepciones no capturadas
 */
set_exception_handler(function ($exception) {
    $errorId = uniqid('EXC');

    // Filtrar informaciÃ³n sensible
    $safeFile = basename($exception->getFile());

    Logger::critical('ExcepciÃ³n no capturada', [
        'error_id' => $errorId,
        'message' => $exception->getMessage(),
        'file' => $safeFile,
        'line' => $exception->getLine(),
        'type' => get_class($exception)
    ], $exception);

    // Si es una request HTTP, enviar respuesta JSON
    if (!empty($_SERVER['REQUEST_METHOD'])) {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }

        // Enviar respuesta de error
        ResponseHelper::error(
            'Error interno del servidor',
            function_exists('isDevelopment') && isDevelopment() ? [
                'error_id' => $errorId,
                'debug' => $exception->getMessage()
            ] : ['error_id' => $errorId],
            500
        );
    } else {
        // Para scripts CLI
        echo "Error fatal: $errorId\n";
        if (function_exists('isDevelopment') && isDevelopment()) {
            echo $exception->getMessage() . "\n";
        }
    }

    exit(1);
});

/**
 * Manejador de errores fatales
 */
register_shutdown_function(function () {
    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $errorId = uniqid('FATAL');

        Logger::critical('Error fatal de PHP', [
            'error_id' => $errorId,
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line'],
            'type' => $error['type']
        ]);

        // Si es una request HTTP
        if (!empty($_SERVER['REQUEST_METHOD'])) {
            // Limpiar buffer de salida
            if (ob_get_level()) {
                ob_clean();
            }

            // Enviar respuesta de error si aÃºn no se han enviado headers
            if (!headers_sent()) {
                ResponseHelper::error(
                    'Error fatal del servidor',
                    function_exists('isDevelopment') && isDevelopment() ? [
                        'error_id' => $errorId,
                        'debug' => $error['message']
                    ] : ['error_id' => $errorId],
                    500
                );
            }
        }
    }
});

/**
 * Configurar reporte de errores segÃºn el entorno
 */
if (function_exists('isDevelopment') && isDevelopment()) {
    // Desarrollo: mostrar todos los errores
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // ProducciÃ³n: no mostrar errores al usuario
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
}

/**
 * Configuraciones de seguridad adicionales
 */
ini_set('expose_php', 0); // No exponer versiÃ³n de PHP
ini_set('session.cookie_httponly', 1); // Cookies no accesibles via JavaScript
ini_set('session.use_strict_mode', 1); // Modo estricto de sesiones

// Si estamos en HTTPS, configurar cookies seguras
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

/**
 * FunciÃ³n auxiliar para validar si estamos en desarrollo
 */
if (!function_exists('isDevelopment')) {
    function isDevelopment()
    {
        return config('APP_ENV', 'production') === 'development';
    }
}

/**
 * FunciÃ³n auxiliar para validar si estamos en producciÃ³n
 */
if (!function_exists('isProduction')) {
    function isProduction()
    {
        return config('APP_ENV', 'production') === 'production';
    }
}

// Log del inicio de la aplicaciÃ³n
Logger::info('Sistema iniciado', [
    'environment' => config('APP_ENV', 'production'),
    'php_version' => PHP_VERSION,
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
]);
