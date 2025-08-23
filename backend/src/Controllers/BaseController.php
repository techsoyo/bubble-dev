<?php

declare(strict_types=1);

namespace Controllers;

use Utils\Logger;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Validator;

/**
 * Base controller providing common functionality for all controllers
 *
 * This class serves as the foundation for all controllers in the application,
 * providing standardized methods for validation, response handling, and common
 * operations. It follows PSR standards and implements security best practices.
 *
 * @package Controllers
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 */
abstract class BaseController
{
    public function __construct()
    {
        // Constructor base vacío implementado para evitar errores de llamada en hijos
    }

    /**
     * Maximum request size for JSON input (in bytes)
     *
     * @var int
     */
    protected const MAX_REQUEST_SIZE = 1048576; // 1MB

    /**
     * Default pagination limit
     *
     * @var int
     */
    protected const DEFAULT_PAGE_LIMIT = 20;

    /**
     * Maximum pagination limit
     *
     * @var int
     */
    protected const MAX_PAGE_LIMIT = 100;

    /**
     * Validates incoming request data according to specified rules
     *
     * This method validates request data using the enhanced Validator class,
     * providing comprehensive validation including security checks, type validation,
     * and business logic validation.
     *
     * @param Request $request The request object containing data to validate
     * @param array<string, mixed> $rules Validation rules in array format
     * @return array<string, mixed>|false Validated data array or false if validation fails
     *
     * @throws \InvalidArgumentException If request or rules are invalid
     *
     * @usage
     * ```php
     * $rules = [
     *     'name' => ['required' => true, 'max_length' => 100],
     *     'email' => ['required' => true, 'email' => true]
     * ];
     * $data = $this->validate($request, $rules);
     * ```
     */
    protected function validate(Request $request, array $rules)
    {
        if (empty($rules)) {
            throw new \InvalidArgumentException('Validation rules cannot be empty');
        }

        try {
            // Obtener cuerpo JSON
            $data = Request::json();

            // Log validation attempt for security monitoring
            Logger::info('Controller validation started', [
                'controller' => static::class,
                'rules_count' => count($rules),
                'data_fields' => array_keys($data)
            ]);

            $validator = new Validator($data, $rules);

            if (!$validator->validateData()) {
                $errors = $validator->getErrors();

                Logger::warning('Controller validation failed', [
                    'controller' => static::class,
                    'errors' => $errors
                ]);

                ResponseHelper::error('Validation failed', 422, ['errors' => $errors]);
                return false;
            }

            $validData = $validator->getValidData();

            Logger::info('Controller validation successful', [
                'controller' => static::class,
                'validated_fields' => count($validData)
            ]);

            return $validData;
        } catch (\Exception $e) {
            Logger::error('Controller validation error', [
                'controller' => static::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            ResponseHelper::error('Validation system error', 500);
            return false;
        }
    }

    /**
     * Returns a standardized success response
     *
     * Sends a JSON success response with consistent formatting and proper
     * HTTP status codes. Includes security headers and logging.
     *
     * @param string $message Success message to display
     * @param mixed $data Optional data to include in response
     * @param int $statusCode HTTP status code (default: 200)
     * @return void
     *
     * @throws \InvalidArgumentException If status code is not a valid success code
     *
     * @usage
     * ```php
     * $this->success('User created successfully', $userData, 201);
     * ```
     */
    protected function success(string $message = 'Operation successful', $data = null, int $statusCode = 200): void
    {
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \InvalidArgumentException("Invalid success status code: $statusCode");
        }

        Logger::info('Controller success response', [
            'controller' => static::class,
            'message' => $message,
            'status_code' => $statusCode,
            'has_data' => $data !== null
        ]);

        ResponseHelper::success($message, $data, $statusCode);
    }

    /**
     * Returns a standardized error response
     *
     * Sends a JSON error response with consistent formatting and proper
     * HTTP status codes. Includes security logging and error tracking.
     *
     * @param string $message Error message to display
     * @param mixed $errors Optional additional error details
     * @param int $statusCode HTTP status code (default: 400)
     * @return void
     *
     * @throws \InvalidArgumentException If status code is not a valid error code
     *
     * @usage
     * ```php
     * $this->error('Resource not found', null, 404);
     * ```
     */
    protected function error(string $message = 'An error occurred', $errors = null, int $statusCode = 400): void
    {
        if ($statusCode < 400 || $statusCode >= 600) {
            throw new \InvalidArgumentException("Invalid error status code: $statusCode");
        }

        Logger::warning('Controller error response', [
            'controller' => static::class,
            'message' => $message,
            'status_code' => $statusCode,
            'errors' => $errors
        ]);

        $payload = is_array($errors) ? ['errors' => $errors] : [];
        ResponseHelper::error($message, $statusCode, $payload);
    }

    /**
     * Validates and extracts pagination parameters from request
     *
     * Provides secure pagination handling with validation of page and limit
     * parameters, enforcing reasonable bounds and defaults.
     *
     * @param Request $request Request object containing pagination params
     * @return array{page: int, limit: int, offset: int} Pagination parameters
     *
     * @usage
     * ```php
     * $pagination = $this->getPaginationParams($request);
     * // Returns: ['page' => 1, 'limit' => 20, 'offset' => 0]
     * ```
     */
    protected function getPaginationParams(Request $request): array
    {
        // Leer parámetros de query de forma segura
        $queryParams = $_GET;

        $page = isset($queryParams['page'])
            ? max(1, filter_var($queryParams['page'], FILTER_VALIDATE_INT) ?: 1)
            : 1;

        $limit = isset($queryParams['limit'])
            ? max(1, min(self::MAX_PAGE_LIMIT, filter_var($queryParams['limit'], FILTER_VALIDATE_INT) ?: self::DEFAULT_PAGE_LIMIT))
            : self::DEFAULT_PAGE_LIMIT;

        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Validates authorization for the current request
     *
     * Checks if the current user has appropriate permissions for the requested
     * operation. Should be overridden by child controllers for specific auth logic.
     *
     * @param string $operation The operation being performed
     * @param mixed $resource Optional resource being accessed
     * @return bool True if authorized, false otherwise
     *
     * @usage
     * ```php
     * if (!$this->isAuthorized('create', $candidateData)) {
     *     $this->error('Insufficient permissions', null, 403);
     *     return;
     * }
     * ```
     */
    protected function isAuthorized(string $operation, $resource = null): bool
    {
        // Base implementation - override in child controllers
        // This is a placeholder that should be implemented based on your auth system

        Logger::info('Authorization check', [
            'controller' => static::class,
            'operation' => $operation,
            'has_resource' => $resource !== null
        ]);

        return true; // Default: allow all operations (override this!)
    }

    /**
     * Logs controller actions for audit and debugging purposes
     *
     * Provides consistent logging for controller actions with context information
     * including user identification, timestamps, and request details.
     *
     * @param string $action Action being performed
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    protected function logAction(string $action, array $context = []): void
    {
        $logData = array_merge([
            'controller' => static::class,
            'action' => $action,
            'timestamp' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $context);

        Logger::info('Controller action', $logData);
    }

    /**
     * Sanitizes input data for safe processing
     *
     * Applies security sanitization to input data while preserving data integrity.
     * Uses context-aware sanitization based on data type and intended use.
     *
     * @param mixed $data Data to sanitize
     * @param string $context Context for sanitization (html, sql, general)
     * @return mixed Sanitized data
     */
    protected function sanitizeInput($data, string $context = 'general')
    {
        if (is_array($data)) {
            return array_map(fn($item) => $this->sanitizeInput($item, $context), $data);
        }

        if (!is_string($data)) {
            return $data;
        }

        switch ($context) {
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            case 'sql':
                // For SQL context, trim and remove null bytes
                return str_replace("\0", '', trim($data));

            case 'general':
            default:
                // General sanitization - remove control characters but preserve content
                return filter_var($data, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
        }
    }
}
