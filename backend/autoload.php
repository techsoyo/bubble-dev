<?php
// Normaliza APP_ENV desde entorno y define la constante si no existe
$__env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development';
if (!defined('APP_ENV')) {
    define('APP_ENV', $__env);
}
/**
 * PSR-4 Autoloader for Bubble of Talents Backend
 * 
 * This autoloader follows PSR-4 standards for automatic class loading.
 * It maps namespaces to directory structures and provides fallback loading
 * for legacy files that don't follow PSR-4 conventions.
 * 
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 */

/**
 * PSR-4 compliant autoloader class
 * 
 * Handles automatic loading of classes based on their namespace and class name.
 * Follows PSR-4 standard with namespace-to-directory mapping.
 */
class Autoloader
{
    /**
     * Namespace to directory mappings
     * 
     * @var array<string, string>
     */
    private static $namespaceMappings = [
        'Controllers\\' => __DIR__ . '/src/Controllers/',
        'Models\\' => __DIR__ . '/src/Models/',
        'Services\\' => __DIR__ . '/src/Services/',
        'Middleware\\' => __DIR__ . '/src/Middleware/',
        'Utils\\' => __DIR__ . '/src/Utils/',
        'Exceptions\\' => __DIR__ . '/src/Exceptions/',
        'Interfaces\\' => __DIR__ . '/src/Interfaces/',
        'Traits\\' => __DIR__ . '/src/Traits/',
    ];

    /**
     * Legacy file mappings for backward compatibility
     * 
     * @var array<string, string>
     */
    private static $legacyMappings = [
        'Database' => __DIR__ . '/config/database.php',
        'Config' => __DIR__ . '/config/config.php',
    ];

    /**
     * Register the autoloader
     * 
     * @return void
     */
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Load a class file based on its fully qualified name
     * 
     * @param string $className Fully qualified class name
     * @return bool True if class was loaded, false otherwise
     */
    public static function load(string $className): bool
    {
        // Try PSR-4 loading first
        if (self::loadPsr4($className)) {
            return true;
        }

        // Try legacy loading
        if (self::loadLegacy($className)) {
            return true;
        }

        // Log failed autoload attempt in development
        if (defined('APP_ENV') && APP_ENV === 'development') {
            error_log("Autoloader: Failed to load class '$className'");
        }

        return false;
    }

    /**
     * Load class using PSR-4 standard
     * 
     * @param string $className Fully qualified class name
     * @return bool True if class was loaded, false otherwise
     */
    private static function loadPsr4(string $className): bool
    {
        // Normalize the class name (remove leading backslash)
        $className = ltrim($className, '\\');

        // Find matching namespace
        foreach (self::$namespaceMappings as $namespace => $directory) {
            if (strpos($className, $namespace) === 0) {
                // Remove namespace prefix
                $relativeClass = substr($className, strlen($namespace));

                // Replace namespace separators with directory separators
                $relativeClass = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass);

                // Build full file path
                $filePath = $directory . $relativeClass . '.php';

                if (self::requireFile($filePath)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Load legacy classes that don't follow PSR-4
     * 
     * @param string $className Class name
     * @return bool True if class was loaded, false otherwise
     */
    private static function loadLegacy(string $className): bool
    {
        // Remove namespace if present
        $baseClassName = basename(str_replace('\\', '/', $className));

        // Check legacy mappings
        if (isset(self::$legacyMappings[$baseClassName])) {
            return self::requireFile(self::$legacyMappings[$baseClassName]);
        }

        // Try common legacy patterns
        $legacyPaths = [
            __DIR__ . '/src/' . $baseClassName . '.php',
            __DIR__ . '/config/' . $baseClassName . '.php',
            __DIR__ . '/src/Utils/' . $baseClassName . '.php',
        ];

        foreach ($legacyPaths as $path) {
            if (self::requireFile($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Safely require a PHP file
     * 
     * @param string $filePath Path to the file
     * @return bool True if file was loaded, false otherwise
     */
    private static function requireFile(string $filePath): bool
    {
        if (file_exists($filePath) && is_readable($filePath)) {
            require_once $filePath;
            return true;
        }

        return false;
    }

    /**
     * Add a new namespace mapping
     * 
     * @param string $namespace Namespace prefix (with trailing backslash)
     * @param string $directory Base directory for the namespace
     * @return void
     */
    public static function addNamespace(string $namespace, string $directory): void
    {
        self::$namespaceMappings[$namespace] = rtrim($directory, '/\\') . '/';
    }

    /**
     * Get all registered namespace mappings
     * 
     * @return array<string, string> Namespace to directory mappings
     */
    public static function getNamespaceMappings(): array
    {
        return self::$namespaceMappings;
    }

    /**
     * Preload commonly used classes for performance
     * 
     * @return void
     */
    public static function preloadCommonClasses(): void
    {
        $commonClasses = [
            'Utils\\Logger',
            'Utils\\ResponseHelper',
            'Utils\\Validator',
            'Models\\BaseModel',
            'Controllers\\BaseController',
            'Middleware\\SecurityMiddleware',
        ];

        foreach ($commonClasses as $className) {
            if (!class_exists($className, false)) {
                self::load($className);
            }
        }
    }
}

// Register the autoloader
Autoloader::register();
if (APP_ENV === 'production') {
    Autoloader::preloadCommonClasses();
}

/**
 * Legacy autoload function for compatibility
 * 
 * @deprecated Use Autoloader::load() instead
 * @param string $className Class name to load
 * @return void
 */
function autoload_legacy($className)
{
    Autoloader::load($className);
}

// Register legacy autoloader as fallback
spl_autoload_register('autoload_legacy');
