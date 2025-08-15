<?php

namespace Middleware;

use Utils\Logger;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Validator;

/**
 * Production-ready validation middleware for API request validation
 *
 * This middleware provides comprehensive request validation for all API endpoints
 * with support for both array-based and string-based validation rules.
 *
 * Features:
 * - Type-specific validation
 * - Security validation (XSS, SQL injection)
 * - File upload validation
 * - Comprehensive error logging
 * - Standardized error responses
 *
 * @author Production Team
 * @version 2.0
 */
class ValidationMiddleware
{
    /**
     * Handle request validation with enhanced security and logging
     *
     * @param Request|array $request Request object or data array
     * @param array $rules Validation rules (array-based or string-based)
     * @param array $options Additional validation options
     * @return bool True if validation passes, false otherwise
     */
    public static function handle($request, array $rules, array $options = [])
    {
        try {
            // Extract data from request
            $data = self::extractRequestData($request);

            // Log validation attempt
            Logger::info('Validation attempt', [
              'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
              'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
              'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
              'fields' => array_keys($data)
            ]);

            // Perform validation using enhanced validator
            $errors = Validator::validate($data, $rules);

            if (!empty($errors)) {
                // Log validation failure
                Logger::warning('Validation failed', [
                  'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                  'errors' => $errors,
                  'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);

                // Send standardized error response
                self::sendValidationErrorResponse($errors, $options);
                return false;
            }

            // Log successful validation
            Logger::info('Validation successful', [
              'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
              'fields_validated' => count($data)
            ]);

            return true;
        } catch (\Exception $e) {
            // Log validation system error
            Logger::error('Validation middleware error', [
              'message' => $e->getMessage(),
              'file' => $e->getFile(),
              'line' => $e->getLine(),
              'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);

            // Send generic error response
            ResponseHelper::error('Validation system error', 500);
            return false;
        }
    }

    /**
     * Legacy method for backward compatibility
     *
     * @param Request $request Objeto de solicitud
     * @param array $rules Reglas de validación
     * @return bool True si la validación es exitosa, false en caso contrario
     */
    public static function validate(Request $request, array $rules)
    {
        return self::handle($request, $rules);
    }

    /**
     * Validate specific data types with enhanced filtering
     *
     * @param mixed $value Value to validate
     * @param string $type Type to validate against
     * @param array $options Additional validation options
     * @return array Validation result [isValid, sanitizedValue, error]
     */
    public static function validateType($value, $type, array $options = [])
    {
        $result = [
          'isValid' => false,
          'sanitizedValue' => $value,
          'error' => null
        ];

        try {
            switch ($type) {
                case 'integer':
                    $filtered = filter_var($value, FILTER_VALIDATE_INT);
                    if ($filtered !== false) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $filtered;

                        // Check range if specified
                        if (isset($options['min']) && $filtered < $options['min']) {
                            $result['isValid'] = false;
                            $result['error'] = "Value must be at least {$options['min']}";
                        }
                        if (isset($options['max']) && $filtered > $options['max']) {
                            $result['isValid'] = false;
                            $result['error'] = "Value must not exceed {$options['max']}";
                        }
                    } else {
                        $result['error'] = 'Value must be a valid integer';
                    }
                    break;

                case 'float':
                    $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
                    if ($filtered !== false) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $filtered;
                    } else {
                        $result['error'] = 'Value must be a valid number';
                    }
                    break;

                case 'email':
                    $filtered = filter_var($value, FILTER_VALIDATE_EMAIL);
                    if ($filtered !== false) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $filtered;
                    } else {
                        $result['error'] = 'Value must be a valid email address';
                    }
                    break;

                case 'url':
                    $filtered = filter_var($value, FILTER_VALIDATE_URL);
                    if ($filtered !== false) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $filtered;
                    } else {
                        $result['error'] = 'Value must be a valid URL';
                    }
                    break;

                case 'ip':
                    $flags = FILTER_VALIDATE_IP;
                    if (isset($options['version'])) {
                        if ($options['version'] === 'ipv4') {
                            $flags |= FILTER_FLAG_IPV4;
                        } elseif ($options['version'] === 'ipv6') {
                            $flags |= FILTER_FLAG_IPV6;
                        }
                    }

                    $filtered = filter_var($value, $flags);
                    if ($filtered !== false) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $filtered;
                    } else {
                        $version = isset($options['version']) ? " ({$options['version']})" : '';
                        $result['error'] = "Value must be a valid IP address{$version}";
                    }
                    break;

                case 'boolean':
                    $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($filtered !== null) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $filtered;
                    } else {
                        $result['error'] = 'Value must be a valid boolean';
                    }
                    break;

                case 'alphanumeric':
                    if (ctype_alnum(str_replace(' ', '', $value))) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $value;
                    } else {
                        $result['error'] = 'Value must contain only letters and numbers';
                    }
                    break;

                case 'alpha':
                    if (ctype_alpha(str_replace(' ', '', $value))) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $value;
                    } else {
                        $result['error'] = 'Value must contain only letters';
                    }
                    break;

                case 'regex':
                    if (isset($options['pattern'])) {
                        if (preg_match($options['pattern'], $value)) {
                            $result['isValid'] = true;
                            $result['sanitizedValue'] = $value;
                        } else {
                            $result['error'] = $options['message'] ?? 'Value does not match required format';
                        }
                    } else {
                        $result['error'] = 'No regex pattern specified';
                    }
                    break;

                case 'json':
                    json_decode($value);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $result['isValid'] = true;
                        $result['sanitizedValue'] = $value;
                    } else {
                        $result['error'] = 'Value must be valid JSON';
                    }
                    break;

                default:
                    $result['error'] = "Unknown validation type: $type";
            }
        } catch (\Exception $e) {
            $result['error'] = 'Validation error: ' . $e->getMessage();
            Logger::error('Type validation error', [
              'type' => $type,
              'value' => $value,
              'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Validate file uploads with comprehensive security checks
     *
     * @param array $files $_FILES array or specific file entry
     * @param array $rules File validation rules
     * @return array Validation errors (empty if valid)
     */
    public static function validateFiles($files, array $rules = [])
    {
        $errors = [];

        foreach ($files as $fieldName => $file) {
            if (!is_array($file) || !isset($file['tmp_name'])) {
                continue;
            }

            // Skip if no file uploaded (unless required)
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                if (isset($rules[$fieldName]['required']) && $rules[$fieldName]['required']) {
                    $errors[$fieldName] = 'File is required';
                }
                continue;
            }

            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[$fieldName] = self::getUploadErrorMessage($file['error']);
                continue;
            }

            // Validate using FileService if available, otherwise use Validator
            if (isset($rules[$fieldName])) {
                $fileErrors = Validator::validateFileUpload($file, $rules[$fieldName]);
                if (!empty($fileErrors)) {
                    $errors[$fieldName] = implode(', ', $fileErrors);
                }
            }
        }

        return $errors;
    }

    /**
     * Extract data from request object or array
     *
     * @param Request|array $request Request source
     * @return array Extracted data
     */
    private static function extractRequestData($request)
    {
        if ($request instanceof Request) {
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    $data = $_GET;
                    break;
                case 'POST':
                case 'PUT':
                case 'PATCH':
                case 'DELETE':
                    $data = Request::json();
                    break;
                default:
                    $data = [];
            }
            return $data;
        }

        if (is_array($request)) {
            return $request;
        }

        // Fallback to global arrays
        $data = [];

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $data = $_GET;
                break;
            case 'POST':
                $data = $_POST;
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $input = file_get_contents('php://input');
                $decoded = json_decode($input, true);
                $data = $decoded ?: [];
                break;
        }

        return $data;
    }

    /**
     * Send standardized validation error response
     *
     * @param array $errors Validation errors
     * @param array $options Response options
     */
    private static function sendValidationErrorResponse(array $errors, array $options = [])
    {
        $statusCode = $options['status_code'] ?? 422;
        $message = $options['message'] ?? 'Validation failed';

        header('Content-Type: application/json');
        http_response_code($statusCode);

        $response = [
          'status' => 'error',
          'message' => $message,
          'errors' => $errors
        ];

        // Add request ID for tracking if available
        if (isset($_SERVER['HTTP_X_REQUEST_ID'])) {
            $response['request_id'] = $_SERVER['HTTP_X_REQUEST_ID'];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Get human-readable upload error message
     *
     * @param int $errorCode PHP upload error code
     * @return string Error message
     */
    private static function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds maximum upload size';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds form maximum size';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary upload directory';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload blocked by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Validate API endpoint access with rate limiting and security checks
     *
     * @param array $options Security options
     * @return bool True if access is allowed
     */
    public static function validateApiAccess(array $options = [])
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $endpoint = $_SERVER['REQUEST_URI'] ?? 'unknown';

            // Basic rate limiting (if enabled)
            if (isset($options['rate_limit'])) {
                if (!self::checkRateLimit($ip, $endpoint, $options['rate_limit'])) {
                    Logger::warning('Rate limit exceeded', ['ip' => $ip, 'endpoint' => $endpoint]);
                    ResponseHelper::error('Too many requests', 429);
                    return false;
                }
            }

            // Check for suspicious patterns in request
            if (isset($options['security_check']) && $options['security_check']) {
                if (!self::checkRequestSecurity()) {
                    Logger::warning('Suspicious request detected', ['ip' => $ip, 'endpoint' => $endpoint]);
                    ResponseHelper::error('Request blocked', 403);
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Logger::error('API access validation error', [
              'error' => $e->getMessage(),
              'ip' => $ip ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Simple rate limiting check
     *
     * @param string $ip Client IP
     * @param string $endpoint Endpoint path
     * @param array $limits Rate limit configuration
     * @return bool True if within limits
     */
    private static function checkRateLimit($ip, $endpoint, $limits)
    {
        $maxRequests = $limits['max_requests'] ?? 60;
        $timeWindow = $limits['time_window'] ?? 60; // seconds

        $cacheDir = __DIR__ . '/../../cache/rate_limits';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheKey = md5($ip . $endpoint);
        $cacheFile = $cacheDir . '/' . $cacheKey;

        $now = time();
        $requests = [];

        // Load existing requests
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $requests = $data['requests'] ?? [];
        }

        // Remove old requests outside time window
        $requests = array_filter($requests, function ($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });

        // Check if limit exceeded
        if (count($requests) >= $maxRequests) {
            return false;
        }

        // Add current request
        $requests[] = $now;

        // Save updated requests
        file_put_contents($cacheFile, json_encode(['requests' => $requests]));

        return true;
    }

    /**
     * Check request for suspicious patterns
     *
     * @return bool True if request appears safe
     */
    private static function checkRequestSecurity()
    {
        // Check User-Agent for common bot patterns
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $suspiciousAgents = [
          'sqlmap',
          'nikto',
          'nessus',
          'openvas',
          'vega',
          'w3af'
        ];

        foreach ($suspiciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return false;
            }
        }

        // Check for suspicious query parameters
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $suspiciousPatterns = [
          '/(\bunion\s+select\b)/i',
          '/(\bselect\s+\*\s+from\b)/i',
          '/(<script[^>]*>)/i',
          '/(javascript:)/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $queryString)) {
                return false;
            }
        }

        return true;
    }
}
