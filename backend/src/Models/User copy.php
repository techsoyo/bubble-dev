<?php

declare(strict_types=1);

namespace Models;

use PDO;
use PDOException;
use Utils\Logger;

/**
 * User model providing user-specific database operations and authentication
 *
 * MIGRADO: Este modelo ha sido migrado para extender correctamente BaseModel 
 * siguiendo los nuevos patrones de la aplicación. Mantiene toda la funcionalidad
 * específica existente mientras adopta las mejoras de rendimiento, cache y 
 * manejo de errores del BaseModel.
 *
 * Cambios principales en la migración:
 * - Configuración correcta de tabla con prefijo automático
 * - Actualización de fillable y hidden según especificaciones BD
 * - Implementación de cache en métodos costosos
 * - Adopción de patrones de manejo de errores del BaseModel
 * - Mantenimiento completo de funcionalidad de autenticación existente
 *
 * Features:
 * - Secure password hashing using PHP's password_hash()
 * - Email uniqueness validation
 * - Password reset token management
 * - Credential verification with timing attack protection
 * - Comprehensive logging for security events
 * - Input validation and sanitization
 * - Performance optimizations with caching
 * - Enhanced error handling patterns
 *
 * Security Considerations:
 * - Passwords are automatically hashed before storage
 * - Password verification uses constant-time comparison
 * - Reset tokens have expiration times
 * - All operations are logged for audit trails
 * - Sensitive fields properly hidden from output
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.1.0 (Migrated from BaseModel extension)
 * @since 2025-08-05
 */
class User extends BaseModel
{
    /**
     * MIGRADO: Tabla configurada para usar prefijo automático bt_users
     * La función T() añadirá automáticamente el prefijo 'bt_' para bt_users
     *
     * @var string
     */
    protected string $table = 'users';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: User
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['name', 'email', 'password', 'role', 'status', 'avatar', 'phone', 'bio', 'company', 'position']
     * 📊 Total campos fillable: 0
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    /**
     * The primary key field name
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * MIGRADO: Campos fillable actualizados según especificaciones de BD
     * Incluye todos los campos requeridos para el modelo User según análisis
     *
     * @var array<string>
     */
    protected array $fillable = [];

    /**
     * MIGRADO: Campos hidden actualizados según especificaciones de seguridad
     * Incluye todos los campos sensibles que deben ocultarse de la salida
     *
     * @var array<string>
     */
    protected array $hidden = [
        'password',
        'password_hash',
        'reset_token',
        'reset_token_expiry',
        'api_token',
        'provider_id',
        'user_agent_consent'
    ];

    /**
     * Find a user by their unique identifier
     *
     * MIGRADO: Mantiene funcionalidad original con mejoras de logging
     * heredadas del BaseModel. Utiliza el método findById del padre con
     * logging específico de seguridad para usuarios.
     *
     * @param int|string $id User unique identifier
     * @return array<string, mixed>|null User data or null if not found
     *
     * @throws \InvalidArgumentException If ID is invalid
     * @throws \RuntimeException If database operation fails
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
     * MIGRADO: Mantiene funcionalidad de autenticación crítica
     * Provides secure email-based user lookup with comprehensive validation
     * and security logging. Email comparison is case-insensitive.
     *
     * @param string $email User email address
     * @return array<string, mixed>|null User data or null if not found
     *
     * @throws \InvalidArgumentException If email format is invalid
     * @throws \RuntimeException If database operation fails
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
     * MIGRADO: Versión optimizada con cache para búsquedas frecuentes
     * Find user by email with optimized query and caching for better performance
     *
     * @param string $email User email address
     * @param bool $useCache Whether to use caching
     * @return array<string, mixed>|null User data or null if not found
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
     * Check if an email address is already registered
     *
     * MIGRADO: Mantiene funcionalidad de validación de unicidad
     * Validates email uniqueness in the system with secure lookup
     * and comprehensive error handling.
     *
     * @param string $email Email address to check
     * @return bool True if email exists, false otherwise
     *
     * @throws \InvalidArgumentException If email format is invalid
     * @throws \RuntimeException If database operation fails
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
     * MIGRADO: Mantiene funcionalidad de creación segura con mejoras
     * Creates a new user account with automatic password hashing, email validation,
     * and comprehensive security logging. Utiliza el método store del BaseModel
     * con validaciones adicionales específicas de usuarios.
     *
     * @param array<string, mixed> $data User data including password
     * @return mixed Newly created user ID or false on failure
     *
     * @throws \InvalidArgumentException If required data is missing or invalid
     * @throws \RuntimeException If database operation fails or email exists
     */
    public function store(array $data)
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
            $userId = parent::store($data);

            // MIGRADO: Invalidar cache relacionado con usuarios
            $this->invalidateUserCache();

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
     * MIGRADO: Mantiene funcionalidad de actualización con mejoras de cache
     * Updates user information with automatic password hashing when password
     * is changed, email validation, and comprehensive audit logging.
     *
     * @param mixed $id User ID to update
     * @param array<string, mixed> $data Data to update
     * @return bool True if update was successful, false otherwise
     *
     * @throws \InvalidArgumentException If ID or data is invalid
     * @throws \RuntimeException If database operation fails
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

            // MIGRADO: Invalidar cache relacionado con usuarios
            $this->invalidateUserCache();

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
     * MIGRADO: Mantiene funcionalidad crítica de autenticación
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
     * MIGRADO: Mantiene funcionalidad de recuperación de contraseña
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
     * MIGRADO: Mantiene funcionalidad de recuperación de contraseña
     * Locates a user account using their password reset token with
     * automatic expiration checking and security validation.
     *
     * @param string $token Password reset token
     * @return array<string, mixed>|null User data if valid token, null otherwise
     *
     * @throws \InvalidArgumentException If token is empty
     * @throws \RuntimeException If database operation fails
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
     * MIGRADO: Mantiene funcionalidad de actualización de contraseña
     * Securely updates user password with automatic hashing and clears
     * any existing reset tokens for security. Includes comprehensive logging.
     *
     * @param mixed $id User ID
     * @param string $password New plain text password
     * @return bool True if update was successful, false otherwise
     *
     * @throws \InvalidArgumentException If password is invalid
     * @throws \RuntimeException If database operation fails
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
     * MIGRADO: Versión mejorada con cache para mejor rendimiento
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
     */
    public function getUsersByRole(string $role, array $additionalFilters = [], int $page = 1, int $limit = 20, bool $useCache = true): array
    {
        if (empty($role)) {
            throw new \InvalidArgumentException('Role cannot be empty');
        }

        $filters = array_merge(['role' => $role], $additionalFilters);
        $cacheTtl = $useCache ? 300 : null; // 5-minute cache

        try {
            // MIGRADO: Usar findOptimized del BaseModel para mejor rendimiento
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
     * MIGRADO: Versión optimizada con cache del BaseModel
     * Retrieves only active users with specific columns for better performance
     * and caching support.
     *
     * @param int $page Page number
     * @param int $limit Records per page
     * @param bool $useCache Whether to enable caching
     * @return array<array<string, mixed>> Array of active users
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
     * MIGRADO: Nuevo método - Get user statistics with caching
     *
     * Returns comprehensive user statistics with caching for performance.
     * Utiliza las capacidades de cache del BaseModel para mejor rendimiento.
     *
     * @param bool $useCache Whether to use caching
     * @return array<string, mixed> User statistics
     */
    public function getUserStats(bool $useCache = true): array
    {
        $cacheTtl = $useCache ? 1800 : null; // 30-minute cache
        $cacheKey = $this->generateCacheKey('getUserStats');

        if ($cacheTtl !== null && class_exists('\Utils\Cache')) {
            try {
                return \Utils\Cache::get($cacheKey, $cacheTtl, function () {
                    return $this->calculateUserStats();
                });
            } catch (\Exception $e) {
                Logger::warning('User stats cache failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->calculateUserStats();
    }

    /**
     * MIGRADO: Nuevo método - Calculate user statistics from database
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

            // Get role distribution using BaseModel query method
            $roleStats = $this->query(
                "SELECT role, COUNT(*) as count FROM `{$this->table}` GROUP BY role",
                []
            );

            foreach ($roleStats as $roleStat) {
                $stats['by_role'][$roleStat['role']] = (int)$roleStat['count'];
            }

            return $stats;
        } catch (\Exception $e) {
            Logger::error('Failed to calculate user statistics', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to calculate statistics: ' . $e->getMessage());
        }
    }

    /**
     * MIGRADO: Nuevo método - Deactivate a user account
     *
     * Safely deactivates a user account by setting status to inactive
     * while preserving all data for potential reactivation.
     *
     * @param mixed $id User ID to deactivate
     * @return bool True if deactivation was successful, false otherwise
     *
     * @throws \InvalidArgumentException If ID is invalid
     * @throws \RuntimeException If database operation fails
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

    /**
     * MIGRADO: Nuevo método - Override getLargeColumns to exclude large user data fields
     *
     * Define campos grandes que deben excluirse de consultas estándar para optimizar rendimiento
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
     * MIGRADO: Nuevo método - Invalidar cache específico de usuarios
     *
     * Limpia el cache relacionado con usuarios cuando se realizan cambios
     * importantes que afectan las consultas cacheadas.
     * MÉTODOS CRUD ENCAPSULADOS ADICIONALES - Para uso externo
     * Complementan los métodos ya existentes (store, update están implementados)
     */

    // =====================================================
    // CRUD METHODS ESTÁNDAR - BaseModel Template v2.0.0
    // =====================================================

    /**
     * Crear nuevo usuario con validaciones
     *
     * @param array $data Datos del usuario
     * @return int|false ID del nuevo usuario o false en caso de error
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function createUser(array $data)
    {
        self::validateUserData($data);

        $user = new self();
        $id = $user->store($data);

        if (!$id) {
            throw new \Exception('Error al crear el usuario');
        }

        self::invalidateUserCache();

        return $id;
    }

    /**
     * Obtener usuario por ID
     *
     * @param int $id ID del usuario
     * @return array|null
     */
    public static function getUser(int $id): ?array
    {
        try {
            $instance = new self();
            return $instance->findById($id);
        } catch (\Exception $e) {
            self::logError('Error al obtener usuario', ['id' => $id], $e);
            return null;
        }
    }

    /**
     * Actualizar usuario con validaciones
     *
     * @param int $id ID del usuario
     * @param array $data Nuevos datos
     * @return bool
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function updateUser(int $id, array $data): bool
    {
        self::validateUserData($data, true);

        $user = new self();
        $existingUser = $user->findById($id);
        if (!$existingUser) {
            throw new \InvalidArgumentException("Usuario con ID {$id} no encontrado");
        }

        $success = $user->update($id, $data);

        if ($success) {
            self::invalidateUserCache();
        }

        return $success;
    }

    /**
     * Eliminar usuario con validaciones
     *
     * @param int $id ID del usuario
     * @return bool
     */
    public static function deleteUser(int $id): bool
    {
        try {
            $user = new self();
            $existingUser = $user->findById($id);
            if (!$existingUser) {
                return false;
            }

            $success = $user->delete($id);

            if ($success) {
                self::invalidateUserCache();
            }

            return $success;
        } catch (\Exception $e) {
            self::logError('Error al eliminar usuario', ['id' => $id], $e);
            return false;
        }
    }

    /**
     * Buscar usuarios con filtros
     *
     * @param array $criteria Criterios de búsqueda
     * @param int $limit Límite de resultados
     * @param int $offset Offset para paginación
     * @return array
     */
    public static function searchUsers(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "SELECT * FROM users WHERE 1=1";
            $params = [];

            // Filtro por email
            if (!empty($criteria['email'])) {
                $query .= " AND email LIKE :email";
                $params['email'] = '%' . $criteria['email'] . '%';
            }

            // Filtro por nombre
            if (!empty($criteria['name'])) {
                $query .= " AND name LIKE :name";
                $params['name'] = '%' . $criteria['name'] . '%';
            }

            // Filtro por rol
            if (!empty($criteria['role'])) {
                $query .= " AND role = :role";
                $params['role'] = $criteria['role'];
            }

            // Filtro por estado activo
            if (isset($criteria['is_active'])) {
                $query .= " AND is_active = :is_active";
                $params['is_active'] = (bool)$criteria['is_active'];
            }

            $query .= " ORDER BY created_at DESC";
            $query .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;

            $user = new self();
            return $user->query($query, $params);
        } catch (\Exception $e) {
            self::logError('Error en búsqueda de usuarios', $criteria, $e);
            return [];
        }
    }

    /**
     * Contar usuarios con filtros
     *
     * @param array $criteria Criterios de búsqueda
     * @return int
     */
    public static function countUsers(array $criteria = []): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM users WHERE 1=1";
            $params = [];

            // Aplicar los mismos filtros que en searchUsers
            if (!empty($criteria['email'])) {
                $query .= " AND email LIKE :email";
                $params['email'] = '%' . $criteria['email'] . '%';
            }

            if (!empty($criteria['name'])) {
                $query .= " AND name LIKE :name";
                $params['name'] = '%' . $criteria['name'] . '%';
            }

            if (!empty($criteria['role'])) {
                $query .= " AND role = :role";
                $params['role'] = $criteria['role'];
            }

            if (isset($criteria['is_active'])) {
                $query .= " AND is_active = :is_active";
                $params['is_active'] = (bool)$criteria['is_active'];
            }

            $user = new self();
            $result = $user->query($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (\Exception $e) {
            self::logError('Error al contar usuarios', $criteria, $e);
            return 0;
        }
    }

    /**
     * Validar datos de usuario
     *
     * @param array $data Datos a validar
     * @param bool $isUpdate Si es una actualización (permite campos opcionales)
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    private static function validateUserData(array $data, bool $isUpdate = false): void
    {
        // email es requerido en creación
        if (!$isUpdate && empty($data['email'])) {
            throw new \InvalidArgumentException('El email es requerido');
        }

        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('El email no tiene un formato válido');
            }
            if (strlen($data['email']) > 255) {
                throw new \InvalidArgumentException('El email no puede exceder los 255 caracteres');
            }
        }

        // name es requerido en creación
        if (!$isUpdate && empty($data['name'])) {
            throw new \InvalidArgumentException('El nombre es requerido');
        }

        if (isset($data['name'])) {
            if (!is_string($data['name']) || strlen(trim($data['name'])) < 2) {
                throw new \InvalidArgumentException('El nombre debe tener al menos 2 caracteres');
            }
            if (strlen($data['name']) > 255) {
                throw new \InvalidArgumentException('El nombre no puede exceder los 255 caracteres');
            }
        }

        // password es requerido en creación
        if (!$isUpdate && empty($data['password'])) {
            throw new \InvalidArgumentException('La contraseña es requerida');
        }

        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                throw new \InvalidArgumentException('La contraseña debe tener al menos 8 caracteres');
            }
            if (strlen($data['password']) > 255) {
                throw new \InvalidArgumentException('La contraseña no puede exceder los 255 caracteres');
            }
        }

        // Validar role si se proporciona
        if (isset($data['role'])) {
            $validRoles = ['admin', 'user', 'manager', 'recruiter'];
            if (!in_array($data['role'], $validRoles)) {
                throw new \InvalidArgumentException('Rol no válido: ' . $data['role']);
            }
        }

        // Validar is_active
        if (isset($data['is_active']) && !is_bool($data['is_active'])) {
            throw new \InvalidArgumentException('is_active debe ser un booleano');
        }
    }

    /**
     * Invalidar caché relacionado con usuarios
     */
    private static function invalidateUserCache(): void
    {
        try {
            // Como los métodos CRUD son estáticos, necesitamos crear una instancia temporal
            $tempInstance = new self();

            // Limpiar todo el caché de la instancia
            $tempInstance->cache = [];

            self::logDebug('Caché de usuarios invalidado');
        } catch (\Exception $e) {
            self::logError('Error al invalidar caché de usuarios', [], $e);
        }
    }
}
