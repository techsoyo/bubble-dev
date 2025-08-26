<?php declare(strict_types=1);

namespace Utils\PerformanceMonitor.php\Utils;

/**
 * Performance monitoring utility for database operations and caching
 *
 * This class provides comprehensive performance monitoring capabilities including
 * query execution time tracking, cache hit/miss statistics, memory usage monitoring,
 * and performance optimization recommendations.
 *
 * Features:
 * - Query execution time tracking
 * - Cache performance metrics
 * - Memory usage monitoring
 * - Slow query detection and alerts
 * - Performance optimization recommendations
 * - Real-time performance dashboards
 *
 * @package Utils
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 */
class PerformanceMonitor
{
    /**
     * Query execution times and statistics
     *
     * @var array<string, array>
     */
    private static array $queryStats = [];

    /**
     * Cache performance statistics
     *
     * @var array<string, int>
     */
    private static array $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    /**
     * Memory usage tracking
     *
     * @var array<string, int>
     */
    private static array $memoryStats = [];

    /**
     * Performance thresholds
     *
     * @var array<string, mixed>
     */
    private static array $thresholds = [
        'slow_query_time' => 1.0, // 1 second
        'memory_warning' => 128 * 1024 * 1024, // 128MB
        'cache_hit_rate_warning' => 70.0 // 70%
    ];

    /**
     * Tracked operations for current request
     *
     * @var array<array>
     */
    private static array $operations = [];

    /**
     * Start timing a database query
     *
     * @param string $query SQL query being executed
     * @param array<string, mixed> $params Query parameters
     * @return string Timer ID for stopping the timer
     */
    public static function startQuery(string $query, array $params = []): string
    {
        $timerId = uniqid('query_', true);

        self::$operations[$timerId] = [
            'type' => 'query',
            'query' => $query,
            'params' => $params,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true)
        ];

        return $timerId;
    }

    /**
     * Stop timing a database query and record statistics
     *
     * @param string $timerId Timer ID from startQuery
     * @param int $rowCount Number of rows affected/returned
     * @return array<string, mixed> Query performance data
     */
    public static function endQuery(string $timerId, int $rowCount = 0): array
    {
        if (!isset(self::$operations[$timerId])) {
            return [];
        }

        $operation = self::$operations[$timerId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $operation['start_time'];
        $memoryUsed = $endMemory - $operation['start_memory'];

        $queryStats = [
            'query' => $operation['query'],
            'params' => $operation['params'],
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'row_count' => $rowCount,
            'timestamp' => time()
        ];

        // Store in query statistics
        $queryType = self::getQueryType($operation['query']);
        if (!isset(self::$queryStats[$queryType])) {
            self::$queryStats[$queryType] = [
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'max_time' => 0,
                'slow_queries' => 0
            ];
        }

        self::$queryStats[$queryType]['count']++;
        self::$queryStats[$queryType]['total_time'] += $duration;
        self::$queryStats[$queryType]['avg_time'] = self::$queryStats[$queryType]['total_time'] / self::$queryStats[$queryType]['count'];
        self::$queryStats[$queryType]['max_time'] = max(self::$queryStats[$queryType]['max_time'], $duration);

        // Check for slow queries
        if ($duration > self::$thresholds['slow_query_time']) {
            self::$queryStats[$queryType]['slow_queries']++;

            Logger::warning('Slow query detected', [
                'query' => $operation['query'],
                'duration' => $duration,
                'threshold' => self::$thresholds['slow_query_time'],
                'row_count' => $rowCount
            ]);
        }

        // Clean up
        unset(self::$operations[$timerId]);

        return $queryStats;
    }

    /**
     * Record cache operation statistics
     *
     * @param string $operation Operation type: 'hit', 'miss', 'set', 'delete'
     * @param string|null $key Cache key
     * @param int|null $size Data size in bytes
     * @return void
     */
    public static function recordCacheOperation(string $operation, ?string $key = null, ?int $size = null): void
    {
        if (isset(self::$cacheStats[$operation])) {
            self::$cacheStats[$operation]++;
        }

        // Log cache misses for optimization
        if ($operation === 'miss' && $key !== null) {
            Logger::debug('Cache miss recorded', [
                'key' => $key,
                'timestamp' => time()
            ]);
        }

        // Track large cache entries
        if ($operation === 'set' && $size !== null && $size > 1024 * 1024) { // > 1MB
            Logger::info('Large cache entry stored', [
                'key' => $key,
                'size' => $size,
                'size_mb' => round($size / (1024 * 1024), 2)
            ]);
        }
    }

    /**
     * Record memory usage at a specific point
     *
     * @param string $checkpoint Checkpoint name
     * @return void
     */
    public static function recordMemoryUsage(string $checkpoint): void
    {
        $memory = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        self::$memoryStats[$checkpoint] = [
            'current' => $memory,
            'peak' => $peak,
            'timestamp' => time()
        ];

        // Check memory warning threshold
        if ($memory > self::$thresholds['memory_warning']) {
            Logger::warning('High memory usage detected', [
                'checkpoint' => $checkpoint,
                'current_mb' => round($memory / (1024 * 1024), 2),
                'peak_mb' => round($peak / (1024 * 1024), 2),
                'threshold_mb' => round(self::$thresholds['memory_warning'] / (1024 * 1024), 2)
            ]);
        }
    }

    /**
     * Get comprehensive performance statistics
     *
     * @return array<string, mixed> Performance statistics
     */
    public static function getStats(): array
    {
        $totalCacheOps = array_sum(self::$cacheStats);
        $cacheHitRate = $totalCacheOps > 0 ? (self::$cacheStats['hits'] / $totalCacheOps) * 100 : 0;

        $stats = [
            'queries' => self::$queryStats,
            'cache' => array_merge(self::$cacheStats, [
                'hit_rate' => round($cacheHitRate, 2),
                'total_operations' => $totalCacheOps
            ]),
            'memory' => self::$memoryStats,
            'thresholds' => self::$thresholds,
            'recommendations' => self::generateRecommendations()
        ];

        return $stats;
    }

    /**
     * Get query statistics for a specific type
     *
     * @param string $queryType Query type (SELECT, INSERT, UPDATE, DELETE)
     * @return array<string, mixed> Query statistics
     */
    public static function getQueryStats(string $queryType): array
    {
        return self::$queryStats[strtoupper($queryType)] ?? [];
    }

    /**
     * Reset all performance statistics
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$queryStats = [];
        self::$cacheStats = ['hits' => 0, 'misses' => 0, 'sets' => 0, 'deletes' => 0];
        self::$memoryStats = [];
        self::$operations = [];
    }

    /**
     * Set performance threshold values
     *
     * @param array<string, mixed> $thresholds New threshold values
     * @return void
     */
    public static function setThresholds(array $thresholds): void
    {
        self::$thresholds = array_merge(self::$thresholds, $thresholds);
    }

    /**
     * Get performance recommendations based on current statistics
     *
     * @return array<string> Array of performance recommendations
     */
    public static function generateRecommendations(): array
    {
        $recommendations = [];

        // Check cache hit rate
        $totalCacheOps = array_sum(self::$cacheStats);
        if ($totalCacheOps > 0) {
            $hitRate = (self::$cacheStats['hits'] / $totalCacheOps) * 100;
            if ($hitRate < self::$thresholds['cache_hit_rate_warning']) {
                $recommendations[] = "Cache hit rate is low ({$hitRate}%). Consider increasing cache TTL or reviewing cache keys.";
            }
        }

        // Check for slow queries
        foreach (self::$queryStats as $type => $stats) {
            if ($stats['slow_queries'] > 0) {
                $percentage = ($stats['slow_queries'] / $stats['count']) * 100;
                $recommendations[] = "Detected {$stats['slow_queries']} slow {$type} queries ({$percentage}% of total). Consider adding indexes or optimizing queries.";
            }

            if ($stats['avg_time'] > 0.5) {
                $recommendations[] = "Average {$type} query time is {$stats['avg_time']}s. Consider query optimization.";
            }
        }

        // Check memory usage
        $currentMemory = memory_get_usage(true);
        if ($currentMemory > self::$thresholds['memory_warning']) {
            $memoryMB = round($currentMemory / (1024 * 1024), 2);
            $recommendations[] = "High memory usage detected ({$memoryMB}MB). Consider optimizing data structures or implementing pagination.";
        }

        // Check for SELECT * queries
        foreach (self::$queryStats as $type => $stats) {
            if ($type === 'SELECT' && $stats['count'] > 0) {
                $recommendations[] = 'Review SELECT queries to ensure specific columns are selected instead of using SELECT *.';
            }
        }

        return $recommendations;
    }

    /**
     * Generate performance report for logging or display
     *
     * @return string Formatted performance report
     */
    public static function generateReport(): string
    {
        $stats = self::getStats();

        $report = "=== PERFORMANCE REPORT ===\n";
        $report .= 'Generated: ' . date('Y-m-d H:i:s') . "\n\n";

        // Query statistics
        $report .= "QUERY STATISTICS:\n";
        foreach ($stats['queries'] as $type => $queryStats) {
            $report .= "  {$type}: {$queryStats['count']} queries, ";
            $report .= 'avg: ' . round($queryStats['avg_time'], 4) . 's, ';
            $report .= 'max: ' . round($queryStats['max_time'], 4) . 's, ';
            $report .= "slow: {$queryStats['slow_queries']}\n";
        }

        // Cache statistics
        $report .= "\nCACHE STATISTICS:\n";
        $report .= "  Hit Rate: {$stats['cache']['hit_rate']}%\n";
        $report .= "  Hits: {$stats['cache']['hits']}, Misses: {$stats['cache']['misses']}\n";
        $report .= "  Sets: {$stats['cache']['sets']}, Deletes: {$stats['cache']['deletes']}\n";

        // Memory usage
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $report .= "\nMEMORY USAGE:\n";
        $report .= '  Current: ' . round($currentMemory / (1024 * 1024), 2) . "MB\n";
        $report .= '  Peak: ' . round($peakMemory / (1024 * 1024), 2) . "MB\n";

        // Recommendations
        if (!empty($stats['recommendations'])) {
            $report .= "\nRECOMMENDATIONS:\n";
            foreach ($stats['recommendations'] as $recommendation) {
                $report .= '  - ' . $recommendation . "\n";
            }
        }

        $report .= "\n=== END REPORT ===\n";

        return $report;
    }

    /**
     * Extract query type from SQL query
     *
     * @param string $query SQL query
     * @return string Query type (SELECT, INSERT, UPDATE, DELETE, etc.)
     */
    private static function getQueryType(string $query): string
    {
        $query = trim(strtoupper($query));
        $firstWord = explode(' ', $query)[0];

        return $firstWord ?: 'UNKNOWN';
    }

    /**
     * Log performance report to file
     *
     * @return void
     */
    public static function logReport(): void
    {
        $report = self::generateReport();
        Logger::info('Performance Report Generated', ['report' => $report]);
    }

    /**
     * Start monitoring for the current request
     *
     * Automatically called to begin performance monitoring for the current request.
     *
     * @return void
     */
    public static function startRequest(): void
    {
        self::recordMemoryUsage('request_start');

        // Register shutdown function to log final stats
        register_shutdown_function([self::class, 'endRequest']);
    }

    /**
     * End monitoring for the current request
     *
     * Automatically called at the end of request processing to log final statistics.
     *
     * @return void
     */
    public static function endRequest(): void
    {
        self::recordMemoryUsage('request_end');

        $stats = self::getStats();

        // Log performance summary for monitoring
        Logger::info('Request Performance Summary', [
            'query_count' => array_sum(array_column($stats['queries'], 'count')),
            'cache_hit_rate' => $stats['cache']['hit_rate'],
            'memory_peak_mb' => round(memory_get_peak_usage(true) / (1024 * 1024), 2),
            'recommendations_count' => count($stats['recommendations'])
        ]);

        // Log detailed report if there are performance issues
        if (!empty($stats['recommendations'])) {
            self::logReport();
        }
    }
}
