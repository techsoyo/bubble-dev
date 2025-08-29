<?php

declare(strict_types=1);

// HEALTH CHECK ENDPOINT - PÚBLICO (sin autenticación requerida)
// Este endpoint debe estar disponible para monitoreo sin autenticación

require_once __DIR__ . '/./bootstrap.php';

// Configurar headers CORS seguros para health check
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Credentials: false');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Manejar OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Health check básico - debe ser rápido y confiable
try {
    // Verificar conexión a base de datos
    $dbStatus = 'unknown';
    $dbResponseTime = 0;

    $startTime = microtime(true);
    try {
        $pdo = getDbConnection();
        // ✅ FIXED: Usar prepared statement seguro
        $stmt = $pdo->prepare('SELECT 1 as health_check');
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbStatus = ($result && isset($result['health_check'])) ? 'connected' : 'error';
    } catch (Exception $e) {
        $dbStatus = 'disconnected';
        error_log("Health check DB error: " . $e->getMessage());
    }
    $dbResponseTime = round((microtime(true) - $startTime) * 1000, 2); // ms

    // Información del sistema
    $systemInfo = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'development',
        'database' => [
            'status' => $dbStatus,
            'response_time_ms' => $dbResponseTime
        ],
        'server' => [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ]
    ];

    // Solo incluir información detallada en desarrollo
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        $systemInfo['debug'] = [
            'memory_usage' => memory_get_peak_usage(true),
            'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
            'loaded_extensions' => get_loaded_extensions()
        ];
    }

    http_response_code(200);
    echo json_encode($systemInfo, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'Service unavailable',
        'timestamp' => date('c'),
        'error_code' => 'HEALTH_CHECK_FAILED'
    ]);
}
