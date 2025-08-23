<?php
/**
 * SMOKE TESTS - BUBBLE TALENTS
 * Tests rápidos de conectividad y disponibilidad básica
 * Tiempo estimado: 30-60 segundos
 */

require_once __DIR__ . '/test_config.php';

class SmokeTests {
    private $base_url;
    private $results = [];
    
    public function __construct() {
        $this->base_url = TestConfig::$base_url;
        echo "🔥 INICIANDO SMOKE TESTS\n";
        echo "URL Base: {$this->base_url}\n";
        echo "========================\n";
    }
    
    /**
     * Test básico de conectividad del servidor
     */
    public function testServerConnectivity() {
        echo "\n🌐 Testing Server Connectivity...\n";
        
        try {
            $response = TestUtils::makeRequest('GET', $this->base_url . '/hello.txt');
            
            TestAssertions::assertEquals(200, $response['status'], 'Server should be accessible');
            TestAssertions::assertResponseTime($response['response_time'], 3000, 'Server should respond quickly');
            
            TestUtils::logTest('server_connectivity', 'PASS', 'Server is accessible');
            return true;
            
        } catch (Exception $e) {
            TestUtils::logTest('server_connectivity', 'FAIL', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test de endpoints críticos existentes
     */
    public function testCriticalEndpointsExistence() {
        echo "\n📍 Testing Critical Endpoints Existence...\n";
        
        $existing_endpoints = [
            'GET /api/auth.php' => 'Auth endpoint',
            'GET /api/bootstrap.php' => 'Bootstrap endpoint',
            'POST /api/candidates.php' => 'Candidates endpoint',
            'GET /api/departments.php' => 'Departments endpoint',
            'POST /api/applications.php' => 'Applications endpoint'
        ];
        
        $passed = 0;
        $total = count($existing_endpoints);
        
        foreach ($existing_endpoints as $endpoint => $description) {
            try {
                list($method, $path) = explode(' ', $endpoint);
                $response = TestUtils::makeRequest($method, $this->base_url . $path);
                
                // Verificar que el endpoint existe (no 404)
                if ($response['status'] !== 404) {
                    TestUtils::logTest("endpoint_exists_$path", 'PASS', "$description exists");
                    $passed++;
                } else {
                    TestUtils::logTest("endpoint_exists_$path", 'FAIL', "$description returns 404");
                }
                
            } catch (Exception $e) {
                TestUtils::logTest("endpoint_exists_$path", 'FAIL', $e->getMessage());
            }
        }
        
        echo "Endpoints existentes: $passed/$total\n";
        return $passed === $total;
    }
    
    /**
     * Test de conectividad a base de datos
     */
    public function testDatabaseConnectivity() {
        echo "\n🗄️  Testing Database Connectivity...\n";
        
        try {
            // Intentar acceder a un endpoint que requiere DB
            $response = TestUtils::makeRequest('GET', $this->base_url . '/api/departments.php');
            
            // Si no hay error 500 (problema de DB), la conexión funciona
            if ($response['status'] !== 500) {
                TestUtils::logTest('database_connectivity', 'PASS', 'Database connection works');
                return true;
            } else {
                TestUtils::logTest('database_connectivity', 'FAIL', 'Database connection error');
                return false;
            }
            
        } catch (Exception $e) {
            TestUtils::logTest('database_connectivity', 'FAIL', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test de CORS headers
     */
    public function testCorsHeaders() {
        echo "\n🌍 Testing CORS Headers...\n";
        
        try {
            $response = TestUtils::makeRequest('OPTIONS', $this->base_url . '/api/auth.php', [
                'headers' => [
                    'Origin' => 'http://localhost:3000',
                    'Access-Control-Request-Method' => 'POST',
                    'Access-Control-Request-Headers' => 'Content-Type'
                ]
            ]);
            
            // Verificar que se manejan peticiones OPTIONS
            if (in_array($response['status'], [200, 204])) {
                TestUtils::logTest('cors_support', 'PASS', 'CORS preflight handled correctly');
                return true;
            } else {
                TestUtils::logTest('cors_support', 'WARN', 'CORS preflight may not be configured');
                return true; // No crítico para smoke tests
            }
            
        } catch (Exception $e) {
            TestUtils::logTest('cors_support', 'WARN', 'CORS test failed: ' . $e->getMessage());
            return true; // No crítico
        }
    }
    
    /**
     * Test básico de autenticación
     */
    public function testBasicAuth() {
        echo "\n🔐 Testing Basic Auth Response...\n";
        
        try {
            $response = TestUtils::makeRequest('POST', $this->base_url . '/api/auth.php', [
                'data' => [
                    'email' => 'test@example.com',
                    'password' => 'wrongpassword'
                ]
            ]);
            
            // Verificar que hay alguna respuesta de autenticación
            if ($response['status'] !== 404) {
                TestUtils::logTest('auth_endpoint_response', 'PASS', 'Auth endpoint responds');
                return true;
            } else {
                TestUtils::logTest('auth_endpoint_response', 'FAIL', 'Auth endpoint not found');
                return false;
            }
            
        } catch (Exception $e) {
            TestUtils::logTest('auth_endpoint_response', 'FAIL', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test de endpoints de IA disponibles
     */
    public function testAIServiceAvailability() {
        echo "\n🤖 Testing AI Service Availability...\n";
        
        $ai_endpoints = [
            'GET /api/ai/health.php' => 'AI Health Check',
            'POST /api/analyze_cv.php' => 'CV Analysis Service'
        ];
        
        $available = 0;
        $total = count($ai_endpoints);
        
        foreach ($ai_endpoints as $endpoint => $description) {
            try {
                list($method, $path) = explode(' ', $endpoint);
                $response = TestUtils::makeRequest($method, $this->base_url . $path);
                
                if ($response['status'] !== 404) {
                    TestUtils::logTest("ai_service_$path", 'PASS', "$description available");
                    $available++;
                } else {
                    TestUtils::logTest("ai_service_$path", 'FAIL', "$description not found");
                }
                
            } catch (Exception $e) {
                TestUtils::logTest("ai_service_$path", 'FAIL', $e->getMessage());
            }
        }
        
        echo "Servicios AI disponibles: $available/$total\n";
        return $available > 0; // Al menos uno debe estar disponible
    }
    
    /**
     * Test de tiempo de respuesta general
     */
    public function testResponseTimes() {
        echo "\n⏱️  Testing Response Times...\n";
        
        $endpoints_to_test = [
            'GET /api/auth.php',
            'GET /api/departments.php',
            'GET /api/candidates.php'
        ];
        
        $slow_responses = 0;
        $total_time = 0;
        $count = 0;
        
        foreach ($endpoints_to_test as $endpoint) {
            try {
                list($method, $path) = explode(' ', $endpoint);
                $response = TestUtils::makeRequest($method, $this->base_url . $path);
                
                $total_time += $response['response_time'];
                $count++;
                
                if ($response['response_time'] > 2000) { // >2 segundos
                    $slow_responses++;
                    TestUtils::logTest('response_time_' . basename($path), 'WARN', 
                        "Slow response: {$response['response_time']}ms");
                } else {
                    TestUtils::logTest('response_time_' . basename($path), 'PASS', 
                        "Good response time: {$response['response_time']}ms");
                }
                
            } catch (Exception $e) {
                TestUtils::logTest('response_time_' . basename($path), 'FAIL', $e->getMessage());
            }
        }
        
        $avg_time = $count > 0 ? round($total_time / $count, 2) : 0;
        echo "Tiempo promedio de respuesta: {$avg_time}ms\n";
        echo "Respuestas lentas (>2s): $slow_responses\n";
        
        return $slow_responses === 0;
    }
    
    /**
     * Ejecutar todos los smoke tests
     */
    public function runAll() {
        $tests = [
            'testServerConnectivity',
            'testCriticalEndpointsExistence',
            'testDatabaseConnectivity',
            'testCorsHeaders',
            'testBasicAuth',
            'testAIServiceAvailability',
            'testResponseTimes'
        ];
        
        $passed = 0;
        $total = count($tests);
        $start_time = microtime(true);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                $passed++;
            }
        }
        
        $execution_time = round((microtime(true) - $start_time), 2);
        
        echo "\n" . str_repeat('=', 50) . "\n";
        echo "🔥 SMOKE TESTS COMPLETADOS\n";
        echo "Tiempo de ejecución: {$execution_time}s\n";
        echo "Tests pasados: $passed/$total\n";
        
        if ($passed === $total) {
            echo "✅ TODOS LOS SMOKE TESTS PASARON\n";
            return true;
        } else {
            echo "❌ ALGUNOS SMOKE TESTS FALLARON\n";
            return false;
        }
    }
}

// Ejecutar smoke tests si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $smokeTests = new SmokeTests();
    $result = $smokeTests->runAll();
    exit($result ? 0 : 1);
}
?>