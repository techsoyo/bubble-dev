<?php

declare(strict_types=1);

namespace Models;

use PDO;
use PDOException;
use Utils\Logger;

/**
 * User model providing user-specific database operations and authentication
 *
 * This model extends the BaseModel to provide specialized functionality for user
 * management including authentication, password handling, account verification,
 * and security features like password reset tokens.
 *
 * Features:
 * - Secure password hashing using PHP's password_hash()
 * - Email uniqueness validation
 * - Password reset token management
 * - Credential verification with timing attack protection
 * - Comprehensive logging for security events
 * - Input validation and sanitization
 *
 * Security Considerations:
 * - Passwords are automatically hashed before storage
 * - Password verification uses constant-time comparison
 * - Reset tokens have expiration times
 * - All operations are logged for audit trails
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 */
class User extends BaseModel
{
    /**
     * The database table name for users
     *
     * @var string
     */
    protected string $table = 'users';

    /**
     * The primary key field name
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * Fields that can be mass assigned
     *
     * @var array<string>
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'avatar',
        'phone',
        'bio',
        'company',
        'position'
    ];

    /**
     * Fields that should be hidden from arrays/JSON
     *
     * @var array<string>
     */
    protected array $hidden = [
        'password',
        'reset_token',
        'reset_token_expiry',
        'api_token'
    ];

    /**
     * Find a user by their unique identifier
     *
     * Overrides the base method to provide user-specific functionality
     * and enhanced logging for security purposes.
     *
     * @param int|string $id User unique identifier
     * @return array<string, mixed>|null User data or null if not found
     *
     * @throws \InvalidArgumentException If ID is invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function findById($id): ?array
    {
        try {
            $user = parent::findById($id);

            if ($user) {
                Logger::info('User found by ID', [
                    'user_id' => $id,
                    'email' => $user['email'] ?? 'unknown'
                ]);
            }

            return $user;
        } catch (\Exception $e) {
            Logger::error('Failed to find user by ID', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Find a user by their email address
     *
     * Provides secure email-based user lookup with comprehensive validation
     * and security logging. Email comparison is case-insensitive.
     *
     * @param string $email User email address
     * @return array<string, mixed>|null User data or null if not found
     *
     * @throws \InvalidArgumentException If email format is invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function findByEmail(string $email): ?array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        try {
            $user = $this->findOneBy('email', strtolower(trim($email)));

            Logger::debug('User lookup by email', [
                'email' => $email,
                'found' => $user !== null
            ]);

            return $user;
        } catch (\Exception $e) {
            Logger::error('Failed to find user by email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if an email address is already registered
     *
     * Validates email uniqueness in the system with secure lookup
     * and comprehensive error handling.
     *
     * @param string $email Email address to check
     * @return bool True if email exists, false otherwise
     *
     * @throws \InvalidArgumentException If email format is invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function emailExists(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        try {
            $exists = $this->findByEmail($email) !== null;

            Logger::debug('Email existence check', [
                'email' => $email,
                'exists' => $exists
            ]);

            return $exists;
        } catch (\Exception $e) {
            Logger::error('Failed to check email existence', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a new user with secure password handling
     *
     * Creates a new user account with automatic password hashing, email validation,
     * and comprehensive security logging. Validates required fields and ensures
     * email uniqueness before creation.
     *
     * @param array<string, mixed> $data User data including password
     * @return mixed Newly created user ID or false on failure
     *
     * @throws \InvalidArgumentException If required data is missing or invalid
     * @throws \RuntimeException If database operation fails or email exists
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function create(array $data)
    {
        // Validate required fields
        if (empty($data['email']) || empty($data['password'])) {
            throw new \InvalidArgumentException('Email and password are required');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        // Normalize email
        $data['email'] = strtolower(trim($data['email']));

        // Check email uniqueness
        if ($this->emailExists($data['email'])) {
            throw new \RuntimeException('Email address already registered');
        }

        // Hash password securely
        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                throw new \InvalidArgumentException('Password must be at least 8 characters long');
            }

            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Set default values
        $data['status'] = $data['status'] ?? 'active';
        $data['role'] = $data['role'] ?? 'user';
        $data['created_at'] = date('Y-m-d H:i:s');

        try {
            $userId = parent::create($data);

            // Invalidate user-related caches
            $this->invalidateCache();

            Logger::info('New user created successfully', [
                'user_id' => $userId,
                'email' => $data['email'],
                'role' => $data['role']
            ]);

            return $userId;
        } catch (\Exception $e) {
            Logger::error('Failed to create user', [
                'email' => $data['email'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update user data with secure password handling
     *
     * Updates user information with automatic password hashing when password
     * is changed, email validation, and comprehensive audit logging.
     *
     * @param mixed $id User ID to update
     * @param array<string, mixed> $data Data to update
     * @return bool True if update was successful, false otherwise
     *
     * @throws \InvalidArgumentException If ID or data is invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function update($id, array $data): bool
    {
        // Validate email if provided
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email format');
            }

            $data['email'] = strtolower(trim($data['email']));

            // Check email uniqueness (excluding current user)
            $existingUser = $this->findByEmail($data['email']);
            if ($existingUser && $existingUser['id'] != $id) {
                throw new \RuntimeException('Email address already in use');
            }
        }

        // Hash password if provided
        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                throw new \InvalidArgumentException('Password must be at least 8 characters long');
            }

            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Update timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $result = parent::update($id, $data);

            // Invalidate user-related caches
            $this->invalidateCache();

            Logger::info('User updated successfully', [
                'user_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to update user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verify user credentials for authentication
     *
     * Securely verifies user login credentials using constant-time password
     * verification to prevent timing attacks. Includes comprehensive logging
     * for security monitoring.
     *
     * @param string $email User email address
     * @param string $password Plain text password
     * @return array<string, mixed>|null User data if credentials are valid, null otherwise
     *
     * @throws \InvalidArgumentException If email or password is empty
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        if (empty($email) || empty($password)) {
            throw new \InvalidArgumentException('Email and password are required');
        }

        try {
            $user = $this->findByEmail($email);

            if (!$user) {
                Logger::warning('Login attempt with non-existent email', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return null;
            }

            // Use constant-time comparison to prevent timing attacks
            if (!password_verify($password, $user['password'])) {
                Logger::warning('Login attempt with invalid password', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return null;
            }

            // Check account status
            if ($user['status'] !== 'active') {
                Logger::warning('Login attempt with inactive account', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'status' => $user['status']
                ]);
                return null;
            }

            Logger::info('Successful user authentication', [
                'user_id' => $user['id'],
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return $user;
        } catch (\Exception $e) {
            Logger::error('Failed to verify credentials', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update password reset token for user account recovery
     *
     * Sets a secure password reset token with expiration time for account
     * recovery processes. Includes comprehensive logging for security monitoring.
     *
     * @param mixed $id User ID
     * @param string $token Secure reset token
     * @param string $expiry Token expiration timestamp
     * @return bool True if update was successful, false otherwise
     *
     * @throws \InvalidArgumentException If parameters are invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function updateResetToken($id, string $token, string $expiry): bool
    {
        if (empty($token) || empty($expiry)) {
            throw new \InvalidArgumentException('Token and expiry are required');
        }

        // Validate expiry format
        if (!strtotime($expiry)) {
            throw new \InvalidArgumentException('Invalid expiry date format');
        }

        try {
            $result = $this->update($id, [
                'reset_token' => $token,
                'reset_token_expiry' => $expiry
            ]);

            Logger::info('Password reset token updated', [
                'user_id' => $id,
                'token_expiry' => $expiry
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to update reset token', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Find user by password reset token
     *
     * Locates a user account using their password reset token with
     * automatic expiration checking and security validation.
     *
     * @param string $token Password reset token
     * @return array<string, mixed>|null User data if valid token, null otherwise
     *
     * @throws \InvalidArgumentException If token is empty
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function findByResetToken(string $token): ?array
    {
        if (empty($token)) {
            throw new \InvalidArgumentException('Reset token cannot be empty');
        }

        try {
            $user = $this->findOneBy('reset_token', $token);

            if ($user) {
                // Check token expiration
                $expiry = strtotime($user['reset_token_expiry']);
                if ($expiry < time()) {
                    Logger::warning('Expired reset token used', [
                        'user_id' => $user['id'],
                        'token_expiry' => $user['reset_token_expiry']
                    ]);
                    return null;
                }

                Logger::info('Valid reset token found', [
                    'user_id' => $user['id']
                ]);
            } else {
                Logger::warning('Invalid reset token attempted', [
                    'token' => substr($token, 0, 8) . '...'
                ]);
            }

            return $user;
        } catch (\Exception $e) {
            Logger::error('Failed to find user by reset token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update user password and clear reset tokens
     *
     * Securely updates user password with automatic hashing and clears
     * any existing reset tokens for security. Includes comprehensive logging.
     *
     * @param mixed $id User ID
     * @param string $password New plain text password
     * @return bool True if update was successful, false otherwise
     *
     * @throws \InvalidArgumentException If password is invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function updatePassword($id, string $password): bool
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long');
        }

        try {
            $result = $this->update($id, [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'reset_token' => null,
                'reset_token_expiry' => null
            ]);

            Logger::info('User password updated successfully', [
                'user_id' => $id
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to update password', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get users by role with optional filtering and caching
     *
     * Retrieves users filtered by role with additional filtering options,
     * pagination support, and performance optimizations including caching.
     *
     * @param string $role User role to filter by
     * @param array<string, mixed> $additionalFilters Additional filter criteria
     * @param int $page Page number for pagination
     * @param int $limit Records per page
     * @param bool $useCache Whether to use caching (default: true)
     * @return array<array<string, mixed>> Array of matching users
     *
     * @throws \InvalidArgumentException If role is invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function getUsersByRole(string $role, array $additionalFilters = [], int $page = 1, int $limit = 20, bool $useCache = true): array
    {
        if (empty($role)) {
            throw new \InvalidArgumentException('Role cannot be empty');
        }

        $filters = array_merge(['role' => $role], $additionalFilters);
        $cacheTtl = $useCache ? 300 : null; // 5-minute cache

        try {
            // Use optimized query with specific columns for better performance
            return $this->findOptimized(
                $filters,
                $page,
                $limit,
                ['name' => 'ASC'],
                ['id', 'name', 'email', 'role', 'status', 'created_at'], // Only needed columns
                $cacheTtl
            );
        } catch (\Exception $e) {
            Logger::error('Failed to get users by role', [
                'role' => $role,
                'filters' => $additionalFilters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get active users with performance optimization
     *
     * Retrieves only active users with specific columns for better performance
     * and caching support.
     *
     * @param int $page Page number
     * @param int $limit Records per page
     * @param bool $useCache Whether to enable caching
     * @return array<array<string, mixed>> Array of active users
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function getActiveUsers(int $page = 1, int $limit = 20, bool $useCache = true): array
    {
        $cacheTtl = $useCache ? 600 : null; // 10-minute cache for active users

        return $this->findOptimized(
            ['status' => 'active'],
            $page,
            $limit,
            ['last_login' => 'DESC', 'name' => 'ASC'],
            ['id', 'name', 'email', 'role', 'last_login', 'avatar'], // Minimal columns
            $cacheTtl
        );
    }

    /**
     * Get user statistics with caching
     *
     * Returns comprehensive user statistics with caching for performance.
     *
     * @param bool $useCache Whether to use caching
     * @return array<string, mixed> User statistics
     *
     * @usage
     * ```php
     * $stats = $userModel->getUserStats();
     * // Returns: ['total' => 150, 'active' => 120, 'inactive' => 30, 'by_role' => [...]]
     * ```
     */
    public function getUserStats(bool $useCache = true): array
    {
        $cacheTtl = $useCache ? 1800 : null; // 30-minute cache
        $cacheKey = $this->generateCacheKey('getUserStats');

        if ($cacheTtl !== null) {
            try {
                $stats = \Utils\Cache::get($cacheKey, $cacheTtl, function () {
                    return $this->calculateUserStats();
                });
                return $stats;
            } catch (\Exception $e) {
                Logger::warning('User stats cache failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->calculateUserStats();
    }

    /**
     * Calculate user statistics from database
     *
     * @return array<string, mixed> Calculated statistics
     */
    private function calculateUserStats(): array
    {
        try {
            $stats = [
                'total' => $this->countAll(),
                'active' => $this->countAll(['status' => 'active']),
                'inactive' => $this->countAll(['status' => 'inactive']),
                'by_role' => []
            ];

            // Get role distribution
            $stmt = $this->db->prepare("SELECT role, COUNT(*) as count FROM `{$this->table}` GROUP BY role");
            $stmt->execute();
            $roleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($roleStats as $roleStat) {
                $stats['by_role'][$roleStat['role']] = (int)$roleStat['count'];
            }

            return $stats;
        } catch (PDOException $e) {
            Logger::error('Failed to calculate user statistics', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to calculate statistics: ' . $e->getMessage());
        }
    }

    /**
     * Override getLargeColumns to exclude large user data fields
     *
     * @return array<string> Array of large column names to exclude from standard queries
     */
    protected function getLargeColumns(): array
    {
        return [
            'bio',
            'preferences',
            'settings',
            'metadata',
            'profile_data',
            'additional_info'
        ];
    }

    /**
     * Find user by email with optimized query and caching
     *
     * Enhanced version of findByEmail with caching and specific column selection.
     *
     * @param string $email User email address
     * @param bool $useCache Whether to use caching
     * @return array<string, mixed>|null User data or null if not found
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function findByEmailOptimized(string $email, bool $useCache = true): ?array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        $cacheTtl = $useCache ? 300 : null; // 5-minute cache
        $email = strtolower(trim($email));

        try {
            $results = $this->findOptimized(
                ['email' => $email],
                1,
                1,
                [],
                ['id', 'name', 'email', 'role', 'status', 'created_at', 'last_login'], // Essential columns only
                $cacheTtl
            );

            return !empty($results) ? $results[0] : null;
        } catch (\Exception $e) {
            Logger::error('Failed to find user by email (optimized)', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Deactivate a user account
     *
     * Safely deactivates a user account by setting status to inactive
     * while preserving all data for potential reactivation.
     *
     * @param mixed $id User ID to deactivate
     * @return bool True if deactivation was successful, false otherwise
     *
     * @throws \InvalidArgumentException If ID is invalid
     * @throws \RuntimeException If database operation fails
     *
     * Uso de referencia disponible en la documentación interna.
     */
    public function deactivateUser($id): bool
    {
        try {
            $result = $this->update($id, [
                'status' => 'inactive',
                'deactivated_at' => date('Y-m-d H:i:s')
            ]);

            Logger::info('User account deactivated', [
                'user_id' => $id
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to deactivate user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
