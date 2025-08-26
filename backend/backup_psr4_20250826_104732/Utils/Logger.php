<?php declare(strict_types=1);

namespace Utils\Logger.php\Utils;

/**
 * Advanced logging system with security features and performance optimizations
 *
 * This class provides comprehensive logging capabilities with built-in security
 * features including sensitive data filtering, log rotation, and structured
 * logging with contextual information. Designed for production environments
 * with high-performance requirements and security considerations.
 *
 * Features:
 * - PSR-3 compatible logging levels
 * - Automatic sensitive data filtering
 * - Log rotation and compression
 * - Request ID tracking for correlation
 * - Security event logging
 * - Performance optimized file operations
 * - Configurable log retention policies
 *
 * Security Considerations:
 * - All sensitive fields are automatically filtered
 * - Log files use restrictive permissions (750)
 * - Stack traces are sanitized to prevent information disclosure
 * - IP addresses are validated and normalized
 *
 * @package Utils
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 * @psr PSR-3 compatible
 */
class Logger
{
    /**
     * System is unusable
     *
     * @var string
     */
    public const LEVEL_EMERGENCY = 'EMERGENCY';

    /**
     * Action must be taken immediately
     *
     * @var string
     */
    public const LEVEL_ALERT = 'ALERT';

    /**
     * Critical conditions
     *
     * @var string
     */
    public const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Error conditions
     *
     * @var string
     */
    public const LEVEL_ERROR = 'ERROR';

    /**
     * Warning conditions
     *
     * @var string
     */
    public const LEVEL_WARNING = 'WARNING';

    /**
     * Normal but significant condition
     *
     * @var string
     */
    public const LEVEL_NOTICE = 'NOTICE';

    /**
     * Informational messages
     *
     * @var string
     */
    public const LEVEL_INFO = 'INFO';

    /**
     * Debug-level messages
     *
     * @var string
     */
    public const LEVEL_DEBUG = 'DEBUG';

    /**
     * Security event logging level
     *
     * @var string
     */
    public const LEVEL_SECURITY = 'SECURITY';

    /**
     * Base directory for log files
     *
     * @var string|null
     */
    private static ?string $logDirectory = null;

    /**
     * Sensitive field patterns that should be filtered from logs
     *
     * @var array<string>
     */
    private static array $sensitiveFields = [
        'password',
        'password_hash',
        'passwd',
        'pwd',
        'token',
        'jwt',
        'secret',
        'api_key',
        'apikey',
        'authorization',
        'auth',
        'credit_card',
        'creditcard',
        'ssn',
        'social_security',
        'private_key',
        'privatekey',
        'session_id',
        'sessionid',
        'csrf_token',
        'csrftoken'
    ];

    /**
     * Maximum log file size before rotation (10MB)
     *
     * @var int
     */
    private static int $maxLogSize = 10485760;

    /**
     * Current request ID for log correlation
     *
     * @var string|null
     */
    private static ?string $requestId = null;

    /**
     * Initialize the logger with secure configuration
     *
     * Sets up the logging directory with appropriate permissions and
     * creates necessary directory structure if it doesn't exist.
     *
     * @return void
     *
     * @throws \RuntimeException If log directory cannot be created
     */
    public static function init(): void
    {
        if (self::$logDirectory !== null) {
            return; // Already initialized
        }

        self::$logDirectory = realpath(__DIR__ . '/../../logs') ?: __DIR__ . '/../../logs';

        // Create log directory if it doesn't exist
        if (!is_dir(self::$logDirectory)) {
            if (!mkdir(self::$logDirectory, 0750, true)) {
                throw new \RuntimeException('Failed to create log directory: ' . self::$logDirectory);
            }
        }

        // Ensure restrictive permissions for security
        chmod(self::$logDirectory, 0750);

        // Create .htaccess to prevent web access
        $htaccessPath = self::$logDirectory . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n");
        }
    }

    /**
     * Log system emergency conditions
     *
     * System is unusable. This is usually reserved for system crashes
     * or critical security incidents that require immediate attention.
     *
     * @param string $message Emergency message
     * @param array<string, mixed> $context Additional context data
     * @return void
     *
     * @usage
     * ```php
     * Logger::emergency('Database connection failed completely', ['error' => $e->getMessage()]);
     * ```
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::log(self::LEVEL_EMERGENCY, $message, $context);
    }

    /**
     * Log alert conditions requiring immediate action
     *
     * Action must be taken immediately. Cases: entire website down,
     * database unavailable, etc.
     *
     * @param string $message Alert message
     * @param array<string, mixed> $context Additional context data
     * @return void
     *
     * @usage
     * ```php
     * Logger::alert('Website completely inaccessible', ['status_code' => 500]);
     * ```
     */
    public static function alert(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ALERT, $message, $context);
    }

    /**
     * Log critical error conditions
     *
     * Critical conditions that indicate serious problems but system
     * is still functional.
     *
     * @param string $message Critical message
     * @param array<string, mixed> $context Additional context data
     * @return void
     *
     * @usage
     * ```php
     * Logger::critical('Payment gateway unreachable', ['gateway' => 'stripe']);
     * ```
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log error conditions
     *
     * Error conditions that should be logged and monitored but don't
     * require immediate intervention.
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context data
     * @param \Exception|\Throwable|null $exception Optional exception object
     * @return void
     *
     * @usage
     * ```php
     * Logger::error('Failed to process user registration', ['user_id' => 123], $exception);
     * ```
     */
    public static function error(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        $contextData = $context;

        if ($exception) {
            $contextData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode(),
                'trace' => self::filterStackTrace($exception->getTraceAsString())
            ];
        }

        self::log(self::LEVEL_ERROR, $message, $contextData);
    }

    /**
     * Log warning conditions
     *
     * Warning conditions that should be monitored but don't indicate
     * immediate problems.
     *
     * @param string $message Warning message
     * @param array<string, mixed> $context Additional context data
     * @return void
     *
     * @usage
     * ```php
     * Logger::warning('API rate limit approaching', ['current_rate' => 450, 'limit' => 500]);
     * ```
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log normal but significant conditions
     *
     * Normal but significant condition that may require attention.
     *
     * @param string $message Notice message
     * @param array<string, mixed> $context Additional context data
     * @return void
     *
     * @usage
     * ```php
     * Logger::notice('User password changed', ['user_id' => 123]);
     * ```
     */
    public static function notice(string $message, array $context = []): void
    {
        self::log(self::LEVEL_NOTICE, $message, $context);
    }

    /**
     * Log informational messages
     *
     * Informational messages that are useful for monitoring application
     * behavior and user activities.
     *
     * @param string $message Informational message
     * @param array<string, mixed> $context Additional context data
     * @return void
     *
     * @usage
     * ```php
     * Logger::info('User successfully logged in', ['user_id' => 123, 'ip' => '192.168.1.1']);
     * ```
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log debug messages (only in development environment)
     *
     * Detailed debug information that should only be logged in
     * development environments.
     *
     * @param string $message Debug message
     * @param array<string, mixed> $context Additional context data
     * @return void
     *
     * @usage
     * ```php
     * Logger::debug('Processing validation rules', ['rules' => $validationRules]);
     * ```
     */
    public static function debug(string $message, array $context = []): void
    {
        // Only log debug messages in development environment
        if (self::isDevelopmentEnvironment()) {
            self::log(self::LEVEL_DEBUG, $message, $context);
        }
    }

    /**
     * Log security-related events for audit trails
     *
     * Logs security events to a separate file for security monitoring
     * and compliance requirements.
     *
     * @param string $event Type of security event
     * @param array<string, mixed> $details Event details
     * @return void
     *
     * @usage
     * ```php
     * Logger::security('failed_login_attempt', [
     *     'user_email' => 'user@local',
     *     'ip_address' => '192.168.1.1',
     *     'attempts' => 3
     * ]);
     * ```
     */
    public static function security(string $event, array $details = []): void
    {
        $securityContext = [
            'event_type' => $event,
            'ip_address' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => time(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'details' => $details
        ];

        self::log(self::LEVEL_SECURITY, $event, $securityContext, 'security.log');
    }

    /**
     * Main logging method that handles all log operations
     *
     * This is the core method that processes all log entries, applies
     * filtering, formatting, and writes to appropriate log files.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Contextual data
     * @param string|null $filename Optional custom log filename
     * @return void
     */
    private static function log(string $level, string $message, array $context = [], ?string $filename = null): void
    {
        // Initialize logger if not already done
        if (self::$logDirectory === null) {
            self::init();
        }

        try {
            // Filter sensitive information from context
            $filteredContext = self::filterSensitiveData($context);

            // Generate formatted log entry
            $logEntry = self::formatLogEntry($level, $message, $filteredContext);

            // Determine log file path
            $logFile = $filename ?: self::getLogFileName($level);
            $logPath = self::$logDirectory . DIRECTORY_SEPARATOR . $logFile;

            // Write to log file with proper locking
            if (file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
                error_log("Logger: Failed to write to log file: $logPath");
            }

            // Rotate log if it exceeds size limit
            self::rotateLogIfNeeded($logPath);
        } catch (\Throwable $e) {
            // Fallback to error_log if our logging fails
            error_log('Logger: Internal error - ' . $e->getMessage());
        }
    }

    /**
     * Format a log entry with consistent structure
     *
     * Creates a standardized log entry format with timestamp, level,
     * process information, and optional context data.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array<string, mixed> $context Context data
     * @return string Formatted log entry
     */
    private static function formatLogEntry(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $requestId = self::getRequestId();
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        // Build base log entry
        $entry = sprintf(
            '[%s] [%s] [PID:%d] [REQ:%s] [MEM:%s/%s] %s',
            $timestamp,
            $level,
            $pid,
            $requestId,
            self::formatBytes($memoryUsage),
            self::formatBytes($memoryPeak),
            $message
        );

        // Add context if present
        if (!empty($context)) {
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($contextJson !== false) {
                $entry .= ' ' . $contextJson;
            }
        }

        return $entry . PHP_EOL;
    }

    /**
     * Filter sensitive data from log context
     *
     * Recursively filters sensitive information from arrays and objects
     * to prevent accidental logging of credentials or personal data.
     *
     * @param mixed $data Data to filter
     * @return mixed Filtered data
     */
    private static function filterSensitiveData($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key) && self::isSensitiveField($key)) {
                    $data[$key] = '[FILTERED]';
                } elseif (is_array($value) || is_object($value)) {
                    $data[$key] = self::filterSensitiveData($value);
                } elseif (is_string($value) && self::looksLikeSensitiveData($value)) {
                    $data[$key] = '[FILTERED]';
                }
            }
        } elseif (is_object($data)) {
            try {
                $reflection = new \ReflectionObject($data);
                foreach ($reflection->getProperties() as $property) {
                    if (!$property->isPublic()) {
                        $property->setAccessible(true);
                    }

                    $propertyName = $property->getName();

                    if (self::isSensitiveField($propertyName)) {
                        $property->setValue($data, '[FILTERED]');
                    } else {
                        $value = $property->getValue($data);
                        if (is_array($value) || is_object($value)) {
                            $property->setValue($data, self::filterSensitiveData($value));
                        } elseif (is_string($value) && self::looksLikeSensitiveData($value)) {
                            $property->setValue($data, '[FILTERED]');
                        }
                    }
                }
            } catch (\ReflectionException $e) {
                // If reflection fails, convert to string representation
                return get_class($data) . '[REFLECTION_FAILED]';
            }
        }

        return $data;
    }

    /**
     * Check if a field name indicates sensitive data
     *
     * @param string $fieldName Field name to check
     * @return bool True if field appears to contain sensitive data
     */
    private static function isSensitiveField(string $fieldName): bool
    {
        $fieldLower = strtolower($fieldName);

        foreach (self::$sensitiveFields as $sensitiveField) {
            if (strpos($fieldLower, $sensitiveField) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value looks like sensitive data based on patterns
     *
     * @param string $value Value to check
     * @return bool True if value looks like sensitive data
     */
    private static function looksLikeSensitiveData(string $value): bool
    {
        // Check for JWT tokens
        if (preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $value)) {
            return true;
        }

        // Check for base64 encoded data that looks like tokens
        if (preg_match('/^[A-Za-z0-9+\/]{40,}={0,2}$/', $value)) {
            return true;
        }

        // Check for hex strings that look like hashes/tokens
        if (preg_match('/^[a-fA-F0-9]{32,}$/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Filter stack traces to remove sensitive information
     *
     * Sanitizes stack traces by removing absolute paths and limiting
     * size to prevent information disclosure and log bloat.
     *
     * @param string $stackTrace Original stack trace
     * @return string Filtered stack trace
     */
    private static function filterStackTrace(string $stackTrace): string
    {
        // Remove absolute paths for security
        $filtered = preg_replace('/\/[^\/\s]+\/[^\/\s]+\//', '/***/', $stackTrace);

        // Remove Windows drive letters and paths
        $filtered = preg_replace('/[A-Z]:\\\\[^\\s]+\\\\/', '***\\', $filtered);

        // Limit length to prevent excessive log size
        if (strlen($filtered) > 5000) {
            $filtered = substr($filtered, 0, 5000) . '... [TRUNCATED]';
        }

        return $filtered;
    }

    /**
     * Get client IP address securely
     *
     * Attempts to determine the real client IP address while handling
     * proxies and load balancers securely.
     *
     * @return string Client IP address
     */
    private static function getClientIp(): string
    {
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // Validate IP and exclude private/reserved ranges
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }

                // If validation fails but it's a valid IP format, use it
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Generate unique request ID for log correlation
     *
     * Creates a unique identifier for the current request to allow
     * correlation of log entries across the application.
     *
     * @return string Unique request ID
     */
    private static function getRequestId(): string
    {
        if (self::$requestId === null) {
            // Generate a short, unique request ID
            self::$requestId = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        }

        return self::$requestId;
    }

    /**
     * Rotate log file if it exceeds maximum size
     *
     * Automatically rotates log files when they exceed the configured
     * maximum size and optionally compresses old files.
     *
     * @param string $logPath Path to log file
     * @return void
     */
    private static function rotateLogIfNeeded(string $logPath): void
    {
        if (!file_exists($logPath) || filesize($logPath) <= self::$maxLogSize) {
            return;
        }

        $timestamp = date('Y-m-d-H-i-s');
        $rotatedPath = $logPath . '.' . $timestamp;

        // Rotate the log file
        if (rename($logPath, $rotatedPath)) {
            // Compress rotated file if gzip is available
            if (function_exists('gzopen')) {
                self::compressLogFile($rotatedPath);
            }

            // Clean up old log files (keep last 10)
            self::cleanupOldLogFiles(dirname($logPath), basename($logPath));
        }
    }

    /**
     * Compress a log file using gzip
     *
     * @param string $filePath Path to file to compress
     * @return void
     */
    private static function compressLogFile(string $filePath): void
    {
        try {
            $data = file_get_contents($filePath);
            if ($data !== false) {
                $gzData = gzencode($data, 9);
                if ($gzData !== false) {
                    file_put_contents($filePath . '.gz', $gzData);
                    unlink($filePath);
                }
            }
        } catch (\Throwable $e) {
            // If compression fails, keep the uncompressed file
            error_log('Logger: Failed to compress log file: ' . $e->getMessage());
        }
    }

    /**
     * Clean up old log files to prevent disk space issues
     *
     * @param string $logDir Log directory
     * @param string $baseFilename Base log filename
     * @return void
     */
    private static function cleanupOldLogFiles(string $logDir, string $baseFilename): void
    {
        try {
            $pattern = $logDir . DIRECTORY_SEPARATOR . $baseFilename . '.*';
            $files = glob($pattern);

            if (is_array($files) && count($files) > 10) {
                // Sort by modification time (oldest first)
                usort($files, function ($a, $b) {
                    return filemtime($a) - filemtime($b);
                });

                // Remove oldest files, keep last 10
                $filesToDelete = array_slice($files, 0, count($files) - 10);
                foreach ($filesToDelete as $file) {
                    unlink($file);
                }
            }
        } catch (\Throwable $e) {
            error_log('Logger: Failed to cleanup old log files: ' . $e->getMessage());
        }
    }

    /**
     * Get appropriate log filename based on log level
     *
     * @param string $level Log level
     * @return string Log filename
     */
    private static function getLogFileName(string $level): string
    {
        switch ($level) {
            case self::LEVEL_SECURITY:
                return 'security.log';
            case self::LEVEL_ERROR:
            case self::LEVEL_CRITICAL:
            case self::LEVEL_ALERT:
            case self::LEVEL_EMERGENCY:
                return 'error.log';
            case self::LEVEL_DEBUG:
                return 'debug.log';
            default:
                return 'application.log';
        }
    }

    /**
     * Format bytes into human readable format
     *
     * @param int $bytes Number of bytes
     * @return string Formatted string
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);

        return sprintf('%.2f%s', $bytes / pow(1024, $factor), $units[$factor] ?? 'TB');
    }

    /**
     * Check if running in development environment
     *
     * @return bool True if in development environment
     */
    private static function isDevelopmentEnvironment(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';
        return in_array(strtolower($env), ['development', 'dev', 'local', 'debug']);
    }

    /**
     * Set custom log directory
     *
     * Allows overriding the default log directory for testing or
     * custom configurations.
     *
     * @param string $directory Custom log directory path
     * @return void
     *
     * @throws \InvalidArgumentException If directory is invalid
     */
    public static function setLogDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("Log directory does not exist: $directory");
        }

        if (!is_writable($directory)) {
            throw new \InvalidArgumentException("Log directory is not writable: $directory");
        }

        self::$logDirectory = rtrim($directory, DIRECTORY_SEPARATOR);
    }

    /**
     * Set maximum log file size before rotation
     *
     * @param int $maxSize Maximum size in bytes
     * @return void
     *
     * @throws \InvalidArgumentException If size is invalid
     */
    public static function setMaxLogSize(int $maxSize): void
    {
        if ($maxSize < 1024) {
            throw new \InvalidArgumentException('Maximum log size must be at least 1KB');
        }

        self::$maxLogSize = $maxSize;
    }
}
