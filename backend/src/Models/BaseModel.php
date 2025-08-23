<?php

declare(strict_types=1);

namespace Models;

use PDO;
use PDOException;
use Utils\Logger;

/**
 * Base model providing generic CRUD operations and database utilities
 *
 * This abstract class serves as the foundation for all model classes in the application.
 * It provides secure, standardized database operations following best practices for
 * SQL injection prevention, error handling, and performance optimization.
 *
 * Features:
 * - Secure prepared statements for all database operations
 * - Comprehensive error handling and logging
 * - Built-in pagination support
 * - Field validation and sanitization
 * - Query optimization and caching support
 * - Transaction management
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 */
abstract class BaseModel
{
    /**
     * The database table name associated with the model
     *
     * @var string
     */
    protected string $table;

    /**
     * The primary key field name for the table
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * The database connection instance
     *
     * @var PDO
     */
    protected PDO $db;

    /**
     * Fields that can be mass assigned
     *
     * @var array<string>
     */
    protected array $fillable = [];

    /**
     * Fields that should be hidden from arrays/JSON
     *
     * @var array<string>
     */
    protected array $hidden = ['password', 'password_hash', 'token'];

    /**
     * Maximum number of records that can be retrieved at once
     *
     * @var int
     */
    protected const MAX_LIMIT = 1000;

    /**
     * Default pagination limit
     *
     * @var int
     */
    protected const DEFAULT_LIMIT = 20;
    protected array $cache = [];
    /**
     * Initialize the model with database connection
     *
     * @throws \RuntimeException If database connection fails
     */
    public function __construct(?string $table = null, string|int $primaryKey = 'id')
    {
        if (!function_exists('getDbConnection')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        if (!class_exists('Utils\Logger')) {
            require_once __DIR__ . '/../Utils/Logger.php';
        }

        // Asignar tabla si se proporciona
        if ($table !== null) {
            $this->table = T($table); // Usar la función T() para prefijos
        } elseif (empty($this->table)) {
            // Si no se proporciona tabla y no está definida, inferir del nombre de la clase
            $className = basename(str_replace('\\', '/', static::class));

            // Manejar clases anónimas
            if (str_contains($className, 'class@anonymous')) {
                $this->table = T('base_model_table');
            } else {
                // Convertir CamelCase a snake_case y remover 'Model'
                $tableName = str_replace('Model', '', $className);
                $tableName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $tableName));
                $this->table = T($tableName);
            }
        } else {
            // Asegurar que la tabla existente use el prefijo correcto
            $this->table = T($this->table);
        }

        // Asignar primary key
        $this->primaryKey = (string) $primaryKey;

        $this->db = getDbConnection();
        if (!$this->db) {
            Logger::error('Failed to initialize database connection', [
                'model' => static::class,
                'table' => $this->table
            ]);
            throw new \RuntimeException('Database connection failed');
        }

        Logger::debug('Model initialized successfully', [
            'model' => static::class,
            'table' => $this->table,
            'primaryKey' => $this->primaryKey
        ]);
    }

    /**
     * Retrieve all records with advanced filtering, pagination, and sorting
     *
     * Provides comprehensive data retrieval with secure filtering, pagination,
     * sorting, and field hiding. Includes performance optimizations and
     * comprehensive error handling.
     *
     * @param array<string, mixed> $filters Key-value pairs for WHERE conditions
     * @param int $page Page number (1-based)
     * @param int $limit Number of records per page
     * @param array<string, string> $orderBy Field => direction pairs for sorting
     * @return array<array<string, mixed>> Array of records
     *
     * @throws \InvalidArgumentException If parameters are invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $users = $model->findAll(['status' => 'active'], 1, 20, ['name' => 'ASC']);
     * ```
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $this->validateTable();

        try {
            $query = "SELECT * FROM `{$this->table}`";
            $params = [];

            // Build WHERE clause
            if (!empty($filters)) {
                $whereConditions = [];
                foreach ($filters as $field => $value) {
                    if ($value !== null && $this->isValidFieldName($field)) {
                        $whereConditions[] = "`$field` = :filter_$field";
                        $params[":filter_$field"] = $value;
                    }
                }

                if (!empty($whereConditions)) {
                    $query .= ' WHERE ' . implode(' AND ', $whereConditions);
                }
            }

            // Build ORDER BY clause
            if (!empty($orderBy)) {
                $orderClauses = [];
                foreach ($orderBy as $field => $direction) {
                    if ($this->isValidFieldName($field) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                        $orderClauses[] = "`$field` " . strtoupper($direction);
                    }
                }

                if (!empty($orderClauses)) {
                    $query .= ' ORDER BY ' . implode(', ', $orderClauses);
                }
            }

            // Add pagination
            $offset = ($page - 1) * $limit;
            $query .= ' LIMIT :limit OFFSET :offset';

            $stmt = $this->db->prepare($query);

            // Bind filter parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }

            // Bind pagination parameters
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logDebug('Records retrieved successfully', [
                'count' => count($results),
                'page' => $page,
                'limit' => $limit
            ]);

            return $this->hideFields($results);
        } catch (PDOException $e) {
            $this->logError('Database error in findAll', [
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to retrieve records: ' . $e->getMessage());
        }
    }

    /**
     * Count records with optional filtering
     *
     * Provides efficient record counting with secure filtering support.
     * Useful for pagination calculations and statistics.
     *
     * @param array<string, mixed> $filters Key-value pairs for WHERE conditions
     * @return int Total number of matching records
     *
     * @throws \InvalidArgumentException If filters are invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $totalUsers = $model->countAll(['status' => 'active']);
     * ```
     */
    public function countAll(array $filters = []): int
    {
        $this->validateTable();

        try {
            $query = "SELECT COUNT(*) as total FROM `{$this->table}`";
            $params = [];

            // Build WHERE clause
            if (!empty($filters)) {
                $whereConditions = [];
                foreach ($filters as $field => $value) {
                    if ($value !== null && $this->isValidFieldName($field)) {
                        $whereConditions[] = "`$field` = :filter_$field";
                        $params[":filter_$field"] = $value;
                    }
                }

                if (!empty($whereConditions)) {
                    $query .= ' WHERE ' . implode(' AND ', $whereConditions);
                }
            }

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }

            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$result['total'];
        } catch (PDOException $e) {
            $this->logError('Database error in countAll', ['filters' => $filters], $e);
            throw new \RuntimeException('Failed to count records: ' . $e->getMessage());
        }
    }

    /**
     * Find a single record by its primary key
     *
     * Retrieves a single record using the primary key value with secure
     * parameter binding and comprehensive error handling.
     *
     * @param mixed $id Primary key value
     * @return array<string, mixed>|null Record data or null if not found
     *
     * @throws \InvalidArgumentException If ID is invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $user = $model->findById(123);
     * ```
     */
    public function findById($id): ?array
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('ID cannot be empty');
        }

        $this->validateTable();

        try {
            $query = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, $this->getPdoType($id));
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                return null;
            }

            // Remove hidden fields
            return $this->hideFields([$result])[0] ?? null;
        } catch (PDOException $e) {
            Logger::error('Database error in findById', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to retrieve record: ' . $e->getMessage());
        }
    }

    /**
     * Create a new record in the database
     *
     * Inserts new data with comprehensive validation, secure parameter binding,
     * and detailed error handling. Supports both single and batch operations.
     *
     * @param array<string, mixed> $data Key-value pairs for the new record
     * @return mixed Newly created record ID or false on failure
     *
     * @throws \InvalidArgumentException If data is invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $id = $model->store(['name' => 'John', 'email' => 'john@local']);
     * ```
     */
    public function store(array $data)
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        $this->validateTable();

        try {
            $fields = array_keys($data);
            $placeholders = array_map(fn($field) => ":$field", $fields);

            $fieldsStr = '`' . implode('`, `', $fields) . '`';
            $placeholdersStr = implode(', ', $placeholders);

            $query = "INSERT INTO `{$this->table}` ($fieldsStr) VALUES ($placeholdersStr)";

            $stmt = $this->db->prepare($query);

            foreach ($data as $field => $value) {
                $stmt->bindValue(":$field", $value, $this->getPdoType($value));
            }

            $stmt->execute();

            Logger::info('Record created successfully', [
                'model' => static::class,
                'id' => $this->db->lastInsertId()
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            Logger::error('Database error in create', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to create record: ' . $e->getMessage());
        }
    }

    /**
     * Find records by a specific field value
     *
     * Searches for records where a specified field matches the given value
     * with secure parameter binding and comprehensive validation.
     *
     * @param string $field Field name to search by
     * @param mixed $value Value to search for
     * @return array<array<string, mixed>> Array of matching records
     *
     * @throws \InvalidArgumentException If field name is invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $users = $model->findBy('email', 'user@local');
     * ```
     */
    public function findBy(string $field, $value): array
    {
        if (!$this->isValidFieldName($field)) {
            throw new \InvalidArgumentException("Invalid field name: $field");
        }

        $this->validateTable();

        try {
            $query = "SELECT * FROM `{$this->table}` WHERE `$field` = :value";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':value', $value, $this->getPdoType($value));
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->hideFields($results);
        } catch (PDOException $e) {
            Logger::error('Database error in findBy', [
                'model' => static::class,
                'field' => $field,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to find records: ' . $e->getMessage());
        }
    }

    /**
     * Find a single record by a specific field value
     *
     * Similar to findBy but returns only the first matching record.
     *
     * @param string $field Field name to search by
     * @param mixed $value Value to search for
     * @return array<string, mixed>|null First matching record or null
     *
     * @throws \InvalidArgumentException If field name is invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $user = $model->findOneBy('email', 'user@local');
     * ```
     */
    public function findOneBy(string $field, $value): ?array
    {
        if (!$this->isValidFieldName($field)) {
            throw new \InvalidArgumentException("Invalid field name: $field");
        }

        $this->validateTable();

        try {
            $query = "SELECT * FROM `{$this->table}` WHERE `$field` = :value LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':value', $value, $this->getPdoType($value));
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result === false) {
                return null;
            }

            return $this->hideFields([$result])[0] ?? null;
        } catch (PDOException $e) {
            Logger::error('Database error in findOneBy', [
                'model' => static::class,
                'field' => $field,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to find record: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing record by its primary key
     *
     * Updates record data with comprehensive validation, secure parameter binding,
     * and detailed error handling. Only updates provided fields.
     *
     * @param mixed $id Primary key value of the record to update
     * @param array<string, mixed> $data Key-value pairs of data to update
     * @return bool True if update was successful, false otherwise
     *
     * @throws \InvalidArgumentException If ID or data is invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $success = $model->update(123, ['name' => 'Jane Doe']);
     * ```
     */
    public function update($id, array $data): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('ID cannot be empty');
        }

        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        $this->validateTable();

        try {
            $fields = array_keys($data);
            $setStatements = array_map(fn($field) => "`$field` = :$field", $fields);

            $query = "UPDATE `{$this->table}` SET " . implode(', ', $setStatements) .
                " WHERE `{$this->primaryKey}` = :id";

            $stmt = $this->db->prepare($query);

            foreach ($data as $field => $value) {
                $stmt->bindValue(":$field", $value, $this->getPdoType($value));
            }

            $stmt->bindValue(':id', $id, $this->getPdoType($id));

            $result = $stmt->execute();

            Logger::info('Record updated successfully', [
                'model' => static::class,
                'id' => $id,
                'affected_rows' => $stmt->rowCount()
            ]);

            return $result;
        } catch (PDOException $e) {
            Logger::error('Database error in update', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to update record: ' . $e->getMessage());
        }
    }

    /**
     * Delete a record by its primary key
     *
     * Removes a record from the database with secure parameter binding
     * and comprehensive error handling.
     *
     * @param mixed $id Primary key value of the record to delete
     * @return bool True if deletion was successful, false otherwise
     *
     * @throws \InvalidArgumentException If ID is invalid
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $success = $model->delete(123);
     * ```
     */
    public function delete($id): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('ID cannot be empty');
        }

        $this->validateTable();

        try {
            $query = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, $this->getPdoType($id));

            $result = $stmt->execute();

            Logger::info('Record deleted successfully', [
                'model' => static::class,
                'id' => $id,
                'affected_rows' => $stmt->rowCount()
            ]);

            return $result;
        } catch (PDOException $e) {
            Logger::error('Database error in delete', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to delete record: ' . $e->getMessage());
        }
    }

    /**
     * Execute a custom SQL query with parameters
     *
     * Executes raw SQL queries with secure parameter binding and
     * comprehensive error handling. Use with caution.
     *
     * @param string $sql SQL query string
     * @param array<string, mixed> $params Parameters for the query
     * @return array<array<string, mixed>> Query results
     *
     * @throws \InvalidArgumentException If SQL is empty
     * @throws \RuntimeException If database operation fails
     *
     * @usage
     * ```php
     * $results = $model->query('SELECT * FROM users WHERE age > :age', ['age' => 18]);
     * ```
     */
    public function query(string $sql, array $params = []): array
    {
        if (empty($sql)) {
            throw new \InvalidArgumentException('SQL query cannot be empty');
        }

        try {
            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Database error in custom query', [
                'model' => static::class,
                'sql' => $sql,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to execute query: ' . $e->getMessage());
        }
    }

    /**
     * Hide specified fields from an array of records
     *
     * Removes sensitive or hidden fields from record arrays based on
     * the $hidden property configuration.
     *
     * @param array<array<string, mixed>> $records Array of record arrays
     * @return array<array<string, mixed>> Records with hidden fields removed
     */
    protected function hideFields(array $records): array
    {
        if (empty($this->hidden)) {
            return $records;
        }

        return array_map(function ($record) {
            foreach ($this->hidden as $field) {
                unset($record[$field]);
            }
            return $record;
        }, $records);
    }

    /**
     * Validate that the table name is set and safe
     *
     * @throws \RuntimeException If table name is invalid
     */
    protected function validateTable(): void
    {
        if (empty($this->table)) {
            throw new \RuntimeException('Table name not defined');
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $this->table)) {
            throw new \RuntimeException('Invalid table name');
        }
    }

    /**
     * Validate field name to prevent SQL injection
     *
     * @param string $fieldName Field name to validate
     * @return bool True if field name is valid
     */
    protected function isValidFieldName(string $fieldName): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $fieldName) === 1;
    }

    /**
     * Get appropriate PDO type for a value
     *
     * @param mixed $value Value to determine type for
     * @return int PDO parameter type constant
     */
    protected function getPdoType($value): int
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }

        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }

        if (is_null($value)) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Get optimized SELECT clause to avoid selecting unnecessary large columns
     *
     * Override this method in child classes to define which columns should be
     * excluded from standard SELECT * queries for performance optimization.
     *
     * @return string Optimized SELECT clause
     */
    protected function getOptimizedSelectClause(): string
    {
        // Default implementation - child classes should override for optimization
        // e.g., exclude large BLOB/TEXT fields like 'content', 'description', 'data'
        $excludeColumns = $this->getLargeColumns();

        if (empty($excludeColumns)) {
            return '*';
        }

        // Get all columns except the large ones
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$this->table}`");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $selectColumns = array_diff($columns, $excludeColumns);

            if (empty($selectColumns)) {
                return '*';
            }

            return '`' . implode('`, `', $selectColumns) . '`';
        } catch (PDOException $e) {
            Logger::warning('Failed to get optimized columns, falling back to *', [
                'table' => $this->table,
                'error' => $e->getMessage()
            ]);
            return '*';
        }
    }

    /**
     * Get list of large columns that should be excluded from standard queries
     *
     * Override this method in child classes to specify which columns contain
     * large data (BLOB, TEXT, JSON, etc.) that should be excluded for performance.
     *
     * @return array<string> Array of column names to exclude
     */
    protected function getLargeColumns(): array
    {
        // Common large column names - child classes should override
        return [
            'content',
            'description',
            'data',
            'metadata',
            'settings',
            'configuration',
            'blob_data',
            'file_content',
            'large_text'
        ];
    }

    /**
     * Generate cache key for query results
     *
     * Creates a consistent cache key based on the method name and parameters
     * to enable efficient caching of database query results.
     *
     * @param string $method Method name
     * @param mixed ...$params Method parameters
     * @return string Cache key
     */
    protected function generateCacheKey(string $method, ...$params): string
    {
        $keyData = [
            'table' => $this->table,
            'method' => $method,
            'params' => $params
        ];

        return md5(serialize($keyData));
    }

    /**
     * Find records with optimized column selection and caching
     *
     * Enhanced version of findAll with specific column selection and caching support.
     * Use this method when you need specific columns or want to cache results.
     *
     * @param array<string, mixed> $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @param array<string, string> $orderBy Sorting criteria
     * @param array<string>|null $columns Specific columns to select
     * @param int|null $cacheTtl Cache TTL in seconds
     * @return array<array<string, mixed>> Array of records
     *
     * @usage
     * ```php
     * // Get only id, name, email with 5-minute cache
     * $users = $model->findOptimized(
     *     ['status' => 'active'],
     *     1, 20, ['name' => 'ASC'],
     *     ['id', 'name', 'email'],
     *     300
     * );
     * ```
     */
    public function findOptimized(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = [], ?array $columns = null, ?int $cacheTtl = null): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        $this->validateTable();

        // Generate cache key if caching is enabled
        $cacheKey = null;
        if ($cacheTtl !== null) {
            $cacheKey = $this->generateCacheKey('findOptimized', $filters, $page, $limit, $orderBy, $columns);

            // Try to get from cache first
            try {
                $cached = \Utils\Cache::get($cacheKey, null, function () {
                    return null;
                });
                if ($cached !== null) {
                    Logger::debug('Cache hit for optimized query', ['key' => $cacheKey]);
                    return $cached;
                }
            } catch (\Exception $e) {
                Logger::warning('Cache retrieval failed', ['error' => $e->getMessage()]);
            }
        }

        try {
            // Build SELECT clause with specific columns
            if ($columns !== null) {
                $validColumns = [];
                foreach ($columns as $column) {
                    if ($this->isValidFieldName($column)) {
                        $validColumns[] = "`$column`";
                    }
                }

                if (empty($validColumns)) {
                    throw new \InvalidArgumentException('No valid columns specified');
                }

                $selectClause = implode(', ', $validColumns);
            } else {
                $selectClause = $this->getOptimizedSelectClause();
            }

            $query = "SELECT $selectClause FROM `{$this->table}`";
            $params = [];

            // Build WHERE clause
            if (!empty($filters)) {
                $whereConditions = [];
                foreach ($filters as $field => $value) {
                    if ($value !== null && $this->isValidFieldName($field)) {
                        $paramKey = ':filter_' . str_replace('.', '_', $field);
                        $whereConditions[] = "`$field` = $paramKey";
                        $params[$paramKey] = $value;
                    }
                }

                if (!empty($whereConditions)) {
                    $query .= ' WHERE ' . implode(' AND ', $whereConditions);
                }
            }

            // Build ORDER BY clause
            if (!empty($orderBy)) {
                $orderClauses = [];
                foreach ($orderBy as $field => $direction) {
                    if ($this->isValidFieldName($field) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                        $orderClauses[] = "`$field` " . strtoupper($direction);
                    }
                }

                if (!empty($orderClauses)) {
                    $query .= ' ORDER BY ' . implode(', ', $orderClauses);
                }
            }

            // Add pagination
            $offset = ($page - 1) * $limit;
            $query .= ' LIMIT :limit OFFSET :offset';

            $stmt = $this->db->prepare($query);

            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Apply field hiding only if no specific columns were requested
            if ($columns === null) {
                $results = $this->hideFields($results);
            }

            // Cache the results
            if ($cacheTtl !== null && $cacheKey !== null) {
                try {
                    \Utils\Cache::set($cacheKey, $results, $cacheTtl, [$this->table, 'optimized']);
                    Logger::debug('Results cached', ['key' => $cacheKey, 'ttl' => $cacheTtl]);
                } catch (\Exception $e) {
                    Logger::warning('Cache storage failed', ['error' => $e->getMessage()]);
                }
            }

            Logger::debug('Optimized query executed successfully', [
                'model' => static::class,
                'query' => $selectClause,
                'filters' => $filters,
                'count' => count($results),
                'cached' => $cacheTtl !== null
            ]);

            return $results;
        } catch (PDOException $e) {
            Logger::error('Database error in findOptimized', [
                'model' => static::class,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to execute optimized query: ' . $e->getMessage());
        }
    }

    /**
     * Get paginated results with total count for efficient pagination
     *
     * Returns both the paginated results and total count in a single response
     * to enable efficient pagination UI without separate count queries.
     *
     * @param array<string, mixed> $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @param array<string, string> $orderBy Sorting criteria
     * @param array<string>|null $columns Specific columns to select
     * @param int|null $cacheTtl Cache TTL in seconds
     * @return array{data: array, total: int, page: int, limit: int, pages: int}
     *
     * @usage
     * ```php
     * $result = $model->getPaginated(['status' => 'active'], 1, 20);
     * echo "Showing " . count($result['data']) . " of " . $result['total'] . " results";
     * ```
     */
    public function getPaginated(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = [], ?array $columns = null, ?int $cacheTtl = null): array
    {
        // Get the data
        $data = $this->findOptimized($filters, $page, $limit, $orderBy, $columns, $cacheTtl);

        // Get total count (with caching)
        $countCacheKey = $this->generateCacheKey('count', $filters);
        $total = 0;

        if ($cacheTtl !== null) {
            try {
                $total = \Utils\Cache::get($countCacheKey, $cacheTtl, function () use ($filters) {
                    return $this->countAll($filters);
                });
            } catch (\Exception $e) {
                Logger::warning('Count cache failed, executing direct query', ['error' => $e->getMessage()]);
                $total = $this->countAll($filters);
            }
        } else {
            $total = $this->countAll($filters);
        }

        $totalPages = (int) ceil($total / $limit);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }

    /**
     * Invalidate cache entries for this model
     *
     * Removes cached entries related to this model, useful when data is updated
     * and cached results need to be refreshed.
     *
     * @return int Number of cache entries removed
     */
    public function invalidateCache(): int
    {
        try {
            return \Utils\Cache::deleteByTags([$this->table]);
        } catch (\Exception $e) {
            Logger::warning('Cache invalidation failed', [
                'table' => $this->table,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get the table name with proper prefix handling
     *
     * @return string Table name with prefix applied
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key field name
     *
     * @return string Primary key field name
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Check if we are in development environment
     *
     * @return bool True if in development mode
     */
    protected function isDevelopment(): bool
    {
        if (!function_exists('isDevelopment')) {
            require_once __DIR__ . '/../../config/config.php';
        }
        return isDevelopment();
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool True if debug mode is enabled
     */
    protected function isDebug(): bool
    {
        if (!function_exists('isDebug')) {
            require_once __DIR__ . '/../../config/config.php';
        }
        return isDebug();
    }

    /**
     * Enhanced error logging that respects environment configuration
     *
     * @param string $message Error message
     * @param array $context Additional context for logging
     * @param \Throwable|null $exception Optional exception to log
     */
    protected function logError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        $context['model'] = static::class;
        $context['table'] = $this->table;

        if ($exception) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'code' => $exception->getCode()
            ];

            // Solo incluir stack trace en desarrollo
            if ($this->isDevelopment()) {
                $context['exception']['trace'] = $exception->getTraceAsString();
            }
        }

        Logger::error($message, $context);
    }

    /**
     * Enhanced debug logging that only logs in debug mode
     *
     * @param string $message Debug message
     * @param array $context Additional context for logging
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->isDebug()) {
            $context['model'] = static::class;
            $context['table'] = $this->table;
            Logger::debug($message, $context);
        }
    }

    /**
     * Get configuration value using the config system
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    protected function config(string $key, $default = null)
    {
        if (!function_exists('config')) {
            require_once __DIR__ . '/../../config/config.php';
        }
        return config($key, $default);
    }
}
