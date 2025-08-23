<?php

/**
 * PHPUnit Bootstrap para Suite de Testing Automatizado
 * Basado en los 324 casos de prueba identificados en la auditoría
 */

// Configurar timezone
date_default_timezone_set('UTC');

// Definir constantes de testing
define('TESTING_MODE', true);
define('TEST_BASE_URL', 'http://localhost:8000/api');
define('TEST_UPLOADS_DIR', __DIR__ . '/fixtures/uploads');
define('TEST_DB_NAME', 'test_bubble_talents');

// Auto-loading
require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/TestCase.php';

// Configurar variables de entorno para testing
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_NAME'] = TEST_DB_NAME;
$_ENV['JWT_SECRET'] = 'test_jwt_secret_key_for_testing_only';
$_ENV['UPLOAD_MAX_SIZE'] = '10485760'; // 10MB
$_ENV['RATE_LIMIT_REQUESTS'] = '10';
$_ENV['RATE_LIMIT_WINDOW'] = '60';

// Crear directorio de reportes si no existe
if (!is_dir(__DIR__ . '/reports')) {
    mkdir(__DIR__ . '/reports', 0755, true);
}

// Crear directorio de fixtures si no existe
if (!is_dir(TEST_UPLOADS_DIR)) {
    mkdir(TEST_UPLOADS_DIR, 0755, true);
}

// Función helper para debug
function test_debug($message, $data = null)
{
    if (getenv('TEST_DEBUG') === 'true') {
        echo "\n[TEST DEBUG] $message";
        if ($data) {
            echo "\n" . json_encode($data, JSON_PRETTY_PRINT);
        }
        echo "\n";
    }
}

// Función helper para limpiar base de datos entre tests
function cleanup_test_database()
{
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=" . TEST_DB_NAME,
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASS'] ?? ''
        );

        // Limpiar tablas de testing
        $tables = ['test_candidates', 'test_applications', 'test_jobs', 'test_users'];
        foreach ($tables as $table) {
            $pdo->exec("DELETE FROM $table WHERE 1");
        }

        test_debug("Database cleaned successfully");
    } catch (Exception $e) {
        test_debug("Database cleanup failed: " . $e->getMessage());
    }
}

// Registrar cleanup automático
register_shutdown_function('cleanup_test_database');

echo "\n[BOOTSTRAP] Suite de Testing Automatizado inicializada";
echo "\n[BOOTSTRAP] Casos de prueba: 324";
echo "\n[BOOTSTRAP] Entorno: " . ($_ENV['APP_ENV'] ?? 'undefined');
echo "\n[BOOTSTRAP] URL Base: " . TEST_BASE_URL;
echo "\n";
