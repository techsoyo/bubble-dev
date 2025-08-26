<?php

declare(strict_types=1);

namespace Utils;

/**
 * Advanced caching system with multiple storage backends and performance optimizations
 *
 * This class provides a comprehensive caching solution with support for multiple
 * storage backends, automatic cache invalidation, cache tags for group operations,
 * and performance monitoring capabilities.
 *
 * Features:
 * - File-based caching with automatic cleanup
 * - Memory-based caching for session-level data
 * - Cache tags for group invalidation
 * - Automatic cache expiration and cleanup
 * - Cache statistics and performance monitoring
 * - Compression support for large cache entries
 * - Thread-safe operations with file locking
 *
 * @package Utils
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 */
class Cache
{
    /**
     * Default cache directory
     *
     * @var string
     */
    private static string $cacheDirectory = '';

    /**
     * Default cache TTL in seconds (1 hour)
     *
     * @var int
     */
    private static int $defaultTtl = 3600;

    /**
     * Memory cache for current request
     *
     * @var array<string, mixed>
     */
    private static array $memoryCache = [];

    /**
     * Cache statistics
     *
     * @var array<string, int>
     */
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];

    /**
     * Maximum cache file size before compression (1MB)
     *
     * @var int
     */
    private static int $compressionThreshold = 1048576;

    /**
     * Initialize the cache system
     *
     * Sets up the cache directory with appropriate permissions and
     * creates necessary directory structure.
     *
     * @param string|null $customDirectory Custom cache directory path
     * @return void
     *
     * @throws \RuntimeException If cache directory cannot be created
     */
    public static function init(?string $customDirectory = null): void
    {
        self::$cacheDirectory = $customDirectory ?: realpath(__DIR__ . '/../../cache') ?: __DIR__ . '/../../cache';

        // Create cache directory if it doesn't exist
        if (!is_dir(self::$cacheDirectory)) {
            if (!mkdir(self::$cacheDirectory, 0755, true)) {
                throw new \RuntimeException('Failed to create cache directory: ' . self::$cacheDirectory);
            }
        }

        // Ensure proper permissions
        chmod(self::$cacheDirectory, 0755);

        // Create .htaccess to prevent web access
        $htaccessPath = self::$cacheDirectory . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n");
        }

        // Schedule cleanup of expired cache files
        register_shutdown_function([self::class, 'cleanup']);
    }

    /**
     * Get cached data or generate it using a callback
     *
     * This is the primary method for cache operations. It attempts to retrieve
     * cached data and generates it using the provided callback if not found.
     *
     * @param string $key Cache key
     * @param int|null $ttl Time to live in seconds (null for default)
     * @param callable $dataCallback Callback to generate data if cache miss
     * @param array<string> $tags Optional cache tags for group operations
     * @return mixed Cached or generated data
     *
     * @throws \InvalidArgumentException If key is empty or callback is invalid
     *
     * @usage
     * ```php
     * $users = Cache::get('active_users', 300, function() {
     *     return UserModel::getActiveUsers();
     * }, ['users', 'active']);
     * ```
     */
    public static function get(string $key, ?int $ttl, callable $dataCallback, array $tags = [])
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key cannot be empty');
        }

        self::initIfNeeded();

        // Check memory cache first
        if (isset(self::$memoryCache[$key])) {
            self::$stats['hits']++;
            return self::$memoryCache[$key]['data'];
        }

        // Check file cache
        $cacheData = self::getFromFile($key);
        if ($cacheData !== null) {
            self::$stats['hits']++;
            // Store in memory cache for faster subsequent access
            self::$memoryCache[$key] = $cacheData;
            return $cacheData['data'];
        }

        // Cache miss - generate data
        self::$stats['misses']++;
        $data = $dataCallback();

        // Store in cache
        self::set($key, $data, $ttl ?? self::$defaultTtl, $tags);

        return $data;
    }

    /**
     * Set cache data with optional expiration and tags
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int|null $ttl Time to live in seconds
     * @param array<string> $tags Optional cache tags
     * @return bool True if successful, false otherwise
     *
     * @usage
     * ```php
     * Cache::set('user_123', $userData, 1800, ['users', 'profiles']);
     * ```
     */
    public static function set(string $key, $data, ?int $ttl = null, array $tags = []): bool
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key cannot be empty');
        }

        self::initIfNeeded();

        $ttl = $ttl ?? self::$defaultTtl;
        $expiration = time() + $ttl;

        $cacheData = [
            'data' => $data,
            'expires' => $expiration,
            'created' => time(),
            'tags' => $tags,
            'size' => strlen(serialize($data))
        ];

        // Store in memory cache
        self::$memoryCache[$key] = $cacheData;

        // Store in file cache
        $success = self::setToFile($key, $cacheData);

        if ($success) {
            self::$stats['writes']++;

            // Update tag index
            self::updateTagIndex($key, $tags);

            Logger::debug('Cache entry created', [
                'key' => $key,
                'ttl' => $ttl,
                'size' => $cacheData['size'],
                'tags' => $tags
            ]);
        }

        return $success;
    }

    /**
     * Delete cached data by key
     *
     * @param string $key Cache key to delete
     * @return bool True if successful, false otherwise
     */
    public static function delete(string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        self::initIfNeeded();

        // Remove from memory cache
        unset(self::$memoryCache[$key]);

        // Remove from file cache
        $cacheFile = self::getCacheFilePath($key);
        $success = false;

        if (file_exists($cacheFile)) {
            $success = unlink($cacheFile);
            if ($success) {
                self::$stats['deletes']++;
                self::removeFromTagIndex($key);
            }
        }

        return $success;
    }

    /**
     * Delete all cache entries with specific tags
     *
     * @param array<string> $tags Tags to match
     * @return int Number of entries deleted
     *
     * @usage
     * ```php
     * // Delete all user-related cache entries
     * Cache::deleteByTags(['users']);
     * ```
     */
    public static function deleteByTags(array $tags): int
    {
        if (empty($tags)) {
            return 0;
        }

        self::initIfNeeded();

        $deleted = 0;
        $tagIndex = self::getTagIndex();

        foreach ($tags as $tag) {
            if (isset($tagIndex[$tag])) {
                foreach ($tagIndex[$tag] as $key) {
                    if (self::delete($key)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Clear all cache entries
     *
     * @return bool True if successful, false otherwise
     */
    public static function clear(): bool
    {
        self::initIfNeeded();

        // Clear memory cache
        self::$memoryCache = [];

        // Clear file cache
        $files = glob(self::$cacheDirectory . DIRECTORY_SEPARATOR . '*.cache');
        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        // Clear tag index
        $tagIndexFile = self::$cacheDirectory . DIRECTORY_SEPARATOR . 'tags.index';
        if (file_exists($tagIndexFile)) {
            unlink($tagIndexFile);
        }

        // Reset stats
        self::$stats = ['hits' => 0, 'misses' => 0, 'writes' => 0, 'deletes' => 0];

        return $success;
    }

    /**
     * Get cache statistics
     *
     * @return array<string, mixed> Cache statistics
     */
    public static function getStats(): array
    {
        $totalRequests = self::$stats['hits'] + self::$stats['misses'];
        $hitRate = $totalRequests > 0 ? (self::$stats['hits'] / $totalRequests) * 100 : 0;

        return array_merge(self::$stats, [
            'total_requests' => $totalRequests,
            'hit_rate' => round($hitRate, 2),
            'memory_entries' => count(self::$memoryCache),
            'cache_directory' => self::$cacheDirectory
        ]);
    }

    /**
     * Check if cache key exists and is not expired
     *
     * @param string $key Cache key
     * @return bool True if exists and valid, false otherwise
     */
    public static function exists(string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        // Check memory cache first
        if (isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key]['expires'] > time();
        }

        // Check file cache
        $cacheData = self::getFromFile($key);
        return $cacheData !== null;
    }

    /**
     * Get cache data from file
     *
     * @param string $key Cache key
     * @return array<string, mixed>|null Cache data or null if not found/expired
     */
    private static function getFromFile(string $key): ?array
    {
        $cacheFile = self::getCacheFilePath($key);

        if (!file_exists($cacheFile)) {
            return null;
        }

        try {
            $content = file_get_contents($cacheFile);
            if ($content === false) {
                return null;
            }

            // Check if file is compressed
            if (substr($content, 0, 2) === "\x1f\x8b") {
                $content = gzdecode($content);
                if ($content === false) {
                    return null;
                }
            }

            $cacheData = unserialize($content);
            if ($cacheData === false) {
                return null;
            }

            // Check expiration
            if ($cacheData['expires'] <= time()) {
                unlink($cacheFile);
                return null;
            }

            return $cacheData;
        } catch (\Throwable $e) {
            Logger::warning('Failed to read cache file', [
                'key' => $key,
                'file' => $cacheFile,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store cache data to file
     *
     * @param string $key Cache key
     * @param array<string, mixed> $cacheData Cache data to store
     * @return bool True if successful, false otherwise
     */
    private static function setToFile(string $key, array $cacheData): bool
    {
        $cacheFile = self::getCacheFilePath($key);

        try {
            $content = serialize($cacheData);

            // Compress large cache entries
            if (strlen($content) > self::$compressionThreshold) {
                $compressed = gzencode($content, 6);
                if ($compressed !== false) {
                    $content = $compressed;
                }
            }

            $success = file_put_contents($cacheFile, $content, LOCK_EX) !== false;

            if ($success) {
                chmod($cacheFile, 0644);
            }

            return $success;
        } catch (\Throwable $e) {
            Logger::warning('Failed to write cache file', [
                'key' => $key,
                'file' => $cacheFile,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get cache file path for a key
     *
     * @param string $key Cache key
     * @return string Cache file path
     */
    private static function getCacheFilePath(string $key): string
    {
        $hashedKey = md5($key);
        return self::$cacheDirectory . DIRECTORY_SEPARATOR . $hashedKey . '.cache';
    }

    /**
     * Update tag index for cache key
     *
     * @param string $key Cache key
     * @param array<string> $tags Tags to associate
     * @return void
     */
    private static function updateTagIndex(string $key, array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $tagIndex = self::getTagIndex();

        foreach ($tags as $tag) {
            if (!isset($tagIndex[$tag])) {
                $tagIndex[$tag] = [];
            }
            if (!in_array($key, $tagIndex[$tag])) {
                $tagIndex[$tag][] = $key;
            }
        }

        self::saveTagIndex($tagIndex);
    }

    /**
     * Remove key from tag index
     *
     * @param string $key Cache key to remove
     * @return void
     */
    private static function removeFromTagIndex(string $key): void
    {
        $tagIndex = self::getTagIndex();

        foreach ($tagIndex as $tag => $keys) {
            $index = array_search($key, $keys);
            if ($index !== false) {
                unset($tagIndex[$tag][$index]);
                $tagIndex[$tag] = array_values($tagIndex[$tag]);

                // Remove tag if no keys left
                if (empty($tagIndex[$tag])) {
                    unset($tagIndex[$tag]);
                }
            }
        }

        self::saveTagIndex($tagIndex);
    }

    /**
     * Get tag index
     *
     * @return array<string, array<string>> Tag index
     */
    private static function getTagIndex(): array
    {
        $tagIndexFile = self::$cacheDirectory . DIRECTORY_SEPARATOR . 'tags.index';

        if (!file_exists($tagIndexFile)) {
            return [];
        }

        $content = file_get_contents($tagIndexFile);
        if ($content === false) {
            return [];
        }

        $index = unserialize($content);
        return is_array($index) ? $index : [];
    }

    /**
     * Save tag index
     *
     * @param array<string, array<string>> $tagIndex Tag index to save
     * @return void
     */
    private static function saveTagIndex(array $tagIndex): void
    {
        $tagIndexFile = self::$cacheDirectory . DIRECTORY_SEPARATOR . 'tags.index';
        file_put_contents($tagIndexFile, serialize($tagIndex), LOCK_EX);
    }

    /**
     * Initialize cache if not already done
     *
     * @return void
     */
    private static function initIfNeeded(): void
    {
        if (empty(self::$cacheDirectory)) {
            self::init();
        }
    }

    /**
     * Cleanup expired cache files
     *
     * This method is automatically called on script shutdown and
     * periodically removes expired cache files.
     *
     * @return void
     */
    public static function cleanup(): void
    {
        if (empty(self::$cacheDirectory) || !is_dir(self::$cacheDirectory)) {
            return;
        }

        // Only run cleanup occasionally to avoid performance impact
        if (mt_rand(1, 100) > 5) { // 5% chance
            return;
        }

        $files = glob(self::$cacheDirectory . DIRECTORY_SEPARATOR . '*.cache');
        if ($files === false) {
            return;
        }

        $currentTime = time();
        $cleaned = 0;

        foreach ($files as $file) {
            try {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                // Handle compressed files
                if (substr($content, 0, 2) === "\x1f\x8b") {
                    $content = gzdecode($content);
                    if ($content === false) {
                        continue;
                    }
                }

                $cacheData = unserialize($content);
                if ($cacheData === false) {
                    continue;
                }

                // Remove expired files
                if ($cacheData['expires'] <= $currentTime) {
                    unlink($file);
                    $cleaned++;
                }
            } catch (\Throwable $e) {
                // Remove corrupted cache files
                unlink($file);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            Logger::debug('Cache cleanup completed', ['files_cleaned' => $cleaned]);
        }
    }
}
