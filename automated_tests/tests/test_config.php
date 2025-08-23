<?php

/**
 * CONFIGURACIÓN DE TESTS - BUBBLE TALENTS
 * Configuración centralizada para toda la suite de testing
 */

class TestConfig
{
    // Configuración base
    public static $base_url = 'http://localhost:8000/api'; // Laragon con PHP server en puerto 8000 + /api prefix
    public static $test_timeout = 30;
    public static $rate_limit_window = 60; // segundos

    // Credenciales de prueba
    public static $test_credentials = [
        'candidate' => [
            'email' => 'test.candidate@example.com',
            'password' => 'TestPass123!'
        ],
        'staff' => [
            'email' => 'test.staff@example.com',
            'password' => 'StaffPass123!'
        ],
        'admin' => [
            'email' => 'admin@example.com',
            'password' => 'AdminPass123!'
        ]
    ];

    // Archivos de prueba
    public static $test_files = [
        'valid_cv' => __DIR__ . '/fixtures/test_cv.pdf',
        'invalid_cv' => __DIR__ . '/fixtures/invalid_file.txt',
        'large_cv' => __DIR__ . '/fixtures/large_cv.pdf' // >10MB
    ];

    // Endpoints críticos que deben existir
    public static $critical_endpoints = [
        'GET /api/health.php',
        'POST /api/auth/login',
        'POST /api/analyze_cv.php',
        'POST /api/candidates/save_v2.php',
        'POST /api/jobs/{id}/apply',
        'POST /api/change-password.php'
    ];

    // Endpoints de debug que deben estar protegidos
    public static $debug_endpoints = [
        '/api/ai/debug_prompt',
        '/api/ai/diagnose_speed',
        '/api/ai/test_complete_system',
        '/api/ai/test_final',
        '/api/cv-schema-test'
    ];

    // Configuración de rate limiting
    public static $rate_limit_config = [
        '/api/analyze_cv.php' => 10, // 10 requests per minute
        '/api/ai/process-cv-complete' => 10,
        '/api/candidates/upload-cv' => 20,
        '/api/auth/login' => 5 // 5 login attempts per minute
    ];

    // Headers por defecto
    public static function getDefaultHeaders($auth_token = null)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'BubbleTalents-TestSuite/1.0'
        ];

        if ($auth_token) {
            $headers['Authorization'] = 'Bearer ' . $auth_token;
        }

        return $headers;
    }

    // Configuración de entorno
    public static function getEnvironmentConfig()
    {
        return [
            'APP_ENV' => getenv('APP_ENV') ?: 'testing',
            'DB_HOST' => getenv('DB_HOST') ?: 'localhost',
            'DB_NAME' => getenv('DB_NAME') ?: 'bubble_talents_test',
            'DB_USER' => getenv('DB_USER') ?: 'root',
            'DB_PASS' => getenv('DB_PASS') ?: ''  // Laragon default
        ];
    }
}

/**
 * UTILIDADES DE TESTING
 */
class TestUtils
{

    /**
     * Realizar petición HTTP
     */
    public static function makeRequest($method, $url, $options = [])
    {
        $ch = curl_init();
        $headers = $options['headers'] ?? TestConfig::getDefaultHeaders();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => TestConfig::$test_timeout,
            CURLOPT_HTTPHEADER => self::formatHeaders($headers),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        // Datos de la petición
        if (isset($options['data'])) {
            if ($headers['Content-Type'] === 'application/json') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['data']));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $options['data']);
            }
        }

        // Archivos multipart
        if (isset($options['files'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['files']);
        }

        $start_time = microtime(true);
        $response_body = curl_exec($ch);
        $response_time = round((microtime(true) - $start_time) * 1000, 2);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return [
            'status' => $http_code,
            'body' => $response_body,
            'json' => json_decode($response_body, true),
            'response_time' => $response_time,
            'error' => $error
        ];
    }

    /**
     * Formatear headers para cURL
     */
    private static function formatHeaders($headers)
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = $key . ': ' . $value;
        }
        return $formatted;
    }

    /**
     * Generar token JWT de prueba
     */
    public static function generateTestToken($role = 'candidate', $user_id = 1)
    {
        // Simulación simple de JWT para tests
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64_encode(json_encode([
            'user_id' => $user_id,
            'role' => $role,
            'exp' => time() + 3600 // 1 hora
        ]));

        return $header . '.' . $payload . '.test_signature';
    }

    /**
     * Validar estructura de respuesta JSON
     */
    public static function validateJsonStructure($json, $expected_fields)
    {
        if (!is_array($json)) {
            return false;
        }

        foreach ($expected_fields as $field) {
            if (!array_key_exists($field, $json)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Logging de tests
     */
    public static function logTest($test_name, $status, $message = '', $details = [])
    {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'test' => $test_name,
            'status' => $status,
            'message' => $message,
            'details' => $details
        ];

        $log_file = __DIR__ . '/logs/test_results.log';
        if (!is_dir(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }

        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND);

        // Output a consola
        $color = $status === 'PASS' ? "\033[32m" : "\033[31m";
        echo $color . "[$status] $test_name" . "\033[0m" . ($message ? ": $message" : "") . "\n";
    }

    /**
     * Crear archivo de test CV válido
     */
    public static function createTestCV()
    {
        $fixtures_dir = __DIR__ . '/fixtures';
        if (!is_dir($fixtures_dir)) {
            mkdir($fixtures_dir, 0755, true);
        }

        // Crear un PDF simple de prueba (esto sería un PDF real en implementación)
        $cv_content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\nxref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n182\n%%EOF";

        file_put_contents($fixtures_dir . '/test_cv.pdf', $cv_content);
        file_put_contents($fixtures_dir . '/invalid_file.txt', 'This is not a CV');

        // Crear archivo grande (simulado)
        $large_content = str_repeat('Large CV content ', 100000); // >1MB
        file_put_contents($fixtures_dir . '/large_cv.pdf', $large_content);
    }
}

/**
 * CLASES DE ASSERTION PARA TESTS
 */
class TestAssertions
{

    public static function assertEquals($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            throw new Exception("Assertion failed: $message. Expected '$expected', got '$actual'");
        }
    }

    public static function assertTrue($condition, $message = '')
    {
        if (!$condition) {
            throw new Exception("Assertion failed: $message. Expected true, got false");
        }
    }

    public static function assertContains($needle, $haystack, $message = '')
    {
        if (is_string($haystack)) {
            if (strpos($haystack, $needle) === false) {
                throw new Exception("Assertion failed: $message. '$needle' not found in string");
            }
        } elseif (is_array($haystack)) {
            if (!in_array($needle, $haystack)) {
                throw new Exception("Assertion failed: $message. '$needle' not found in array");
            }
        }
    }

    public static function assertArrayHasKey($key, $array, $message = '')
    {
        if (!array_key_exists($key, $array)) {
            throw new Exception("Assertion failed: $message. Key '$key' not found in array");
        }
    }

    public static function assertResponseTime($response_time, $max_time = 5000, $message = '')
    {
        if ($response_time > $max_time) {
            throw new Exception("Assertion failed: $message. Response time {$response_time}ms exceeds limit {$max_time}ms");
        }
    }
}

// Inicializar archivos de prueba
TestUtils::createTestCV();

echo "✅ Configuración de tests cargada correctamente\n";
