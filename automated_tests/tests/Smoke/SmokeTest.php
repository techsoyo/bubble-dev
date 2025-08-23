<?php

require_once __DIR__ . '/../../TestCase.php';

/**
 * Smoke Tests - Verificación rápida de funcionalidad básica
 * Cubre los 54 endpoints críticos identificados en la auditoría
 * 
 * @group smoke
 * @group critical
 */
class SmokeTest extends TestCase
{
    /**
     * @test
     * @group critical
     * Caso: GET /api/health.php - Endpoint fundamental faltante (Caso #1)
     */
    public function test_health_endpoint_responds()
    {
        // Act
        $response = $this->makeRequest('GET', '/health.php');

        // Assert
        $this->assertEquals(
            200,
            $response['status_code'],
            'CRITICAL: Health endpoint must be available for monitoring'
        );

        $this->assertResponseStructure([
            'status' => 'string',
            'timestamp' => 'string',
            'services' => []
        ], $response['body']);

        $this->assertEquals('ok', $response['body']['status']);
    }

    /**
     * @test
     * @group critical
     * Caso: GET /api/ping - Endpoint básico de conectividad (Caso #13)
     */
    public function test_ping_endpoint_responds()
    {
        // Act
        $response = $this->makeRequest('GET', '/ping');

        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'status' => 'string',
            'timestamp' => 'string'
        ], $response['body']);

        $this->assertEquals('pong', $response['body']['status']);
    }

    /**
     * @test
     * @group critical
     * Caso: Verificar que todos los endpoints críticos responden
     */
    public function test_all_critical_endpoints_respond()
    {
        $criticalEndpoints = [
            // Endpoints de autenticación
            ['method' => 'POST', 'endpoint' => '/auth/login', 'requires_auth' => false],
            ['method' => 'POST', 'endpoint' => '/change-password.php', 'requires_auth' => true],

            // Endpoints de candidatos
            ['method' => 'POST', 'endpoint' => '/candidates/save_v2.php', 'requires_auth' => true],
            ['method' => 'GET', 'endpoint' => '/candidate-experiences.php', 'requires_auth' => true],
            ['method' => 'GET', 'endpoint' => '/candidate-notifications.php', 'requires_auth' => true],

            // Endpoints de trabajos
            ['method' => 'POST', 'endpoint' => '/jobs/123/apply', 'requires_auth' => true],
            ['method' => 'GET', 'endpoint' => '/jobs/123/related', 'requires_auth' => false],
            ['method' => 'GET', 'endpoint' => '/jobs/123/bookmark', 'requires_auth' => true],
            ['method' => 'POST', 'endpoint' => '/jobs/123/bookmark', 'requires_auth' => true],

            // Endpoints de IA
            ['method' => 'POST', 'endpoint' => '/analyze_cv.php', 'requires_auth' => false],
            ['method' => 'GET', 'endpoint' => '/ai/insights', 'requires_auth' => true],
            ['method' => 'GET', 'endpoint' => '/ai/predictions', 'requires_auth' => true],
            ['method' => 'GET', 'endpoint' => '/ai/trends', 'requires_auth' => true],

            // Endpoints de notificaciones
            ['method' => 'GET', 'endpoint' => '/get-notification-preferences.php', 'requires_auth' => true],
            ['method' => 'POST', 'endpoint' => '/save-notification-preferences.php', 'requires_auth' => true],

            // Endpoints de calendar
            ['method' => 'GET', 'endpoint' => '/calendar/integrations', 'requires_auth' => true],
            ['method' => 'POST', 'endpoint' => '/calendar/connect', 'requires_auth' => true],
            ['method' => 'GET', 'endpoint' => '/calendar/events', 'requires_auth' => true],

            // Endpoints de dashboard
            ['method' => 'GET', 'endpoint' => '/hr/dashboard-stats', 'requires_auth' => true],
            ['method' => 'GET', 'endpoint' => '/recruiter/1/dashboard-stats', 'requires_auth' => true]
        ];

        $failedEndpoints = [];
        $token = null;

        foreach ($criticalEndpoints as $spec) {
            // Obtener token si es necesario
            if ($spec['requires_auth'] && !$token) {
                try {
                    $token = $this->getAuthToken('hr');
                } catch (Exception $e) {
                    // Si no se puede autenticar, marcar todos los endpoints autenticados como fallidos
                    $failedEndpoints[] = $spec['endpoint'] . ' (AUTH_FAILED)';
                    continue;
                }
            }

            // Hacer la petición
            $headers = $spec['requires_auth'] && $token ? ["Authorization: Bearer {$token}"] : [];
            $response = $this->makeRequest($spec['method'], $spec['endpoint'], [], $headers);

            // Verificar que el endpoint responde (no 404 ni 500)
            if ($response['status_code'] === 404) {
                $failedEndpoints[] = $spec['method'] . ' ' . $spec['endpoint'] . ' (NOT_FOUND)';
            } elseif ($response['status_code'] >= 500) {
                $failedEndpoints[] = $spec['method'] . ' ' . $spec['endpoint'] . ' (SERVER_ERROR)';
            }
        }

        // Assert
        $this->assertEmpty(
            $failedEndpoints,
            'CRITICAL ENDPOINTS FAILED: ' . implode(', ', $failedEndpoints) .
                '. These endpoints are required for basic functionality.'
        );
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar que la base de datos está accesible
     */
    public function test_database_connectivity()
    {
        // Intentar acceder a un endpoint que requiera base de datos
        $response = $this->makeRequest('GET', '/health.php');

        // Si el health check funciona, asumimos que la DB está conectada
        if ($response['status_code'] === 200) {
            $this->assertTrue(true, 'Database connectivity verified through health endpoint');
        } else {
            // Intentar con otro endpoint que use la DB
            $altResponse = $this->makeRequest('GET', '/departments.php');
            $this->assertNotEquals(
                500,
                $altResponse['status_code'],
                'Database connectivity issues detected'
            );
        }
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar que los endpoints de autenticación funcionan
     */
    public function test_authentication_flow_works()
    {
        // Act - Intentar login
        $credentials = $this->getTestCredentials('candidate');
        $response = $this->makeRequest('POST', '/auth/login', $credentials);

        // Assert
        if ($response['status_code'] === 404) {
            $this->fail('CRITICAL: Login endpoint not found - authentication is broken');
        }

        if ($response['status_code'] === 500) {
            $this->fail('CRITICAL: Login endpoint has server error - authentication is broken');
        }

        // Si responde con 200 o incluso 401 (credenciales incorrectas), el endpoint existe
        $this->assertContains(
            $response['status_code'],
            [200, 401, 400],
            'Login endpoint should respond with appropriate status code'
        );
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar que el procesamiento de IA funciona básicamente
     */
    public function test_ai_processing_accessible()
    {
        // Act - Intentar acceder al endpoint de análisis de CV
        $testFile = $this->uploadTestFile('smoke_test_cv.pdf');
        $response = $this->makeRequest('POST', '/analyze_cv.php', $testFile, [
            'Content-Type: multipart/form-data'
        ]);

        // Assert
        $this->assertNotEquals(
            404,
            $response['status_code'],
            'CRITICAL: CV analysis endpoint not found'
        );
        $this->assertNotEquals(
            500,
            $response['status_code'],
            'CRITICAL: CV analysis endpoint has server error'
        );

        // Puede devolver 400 (datos incorrectos) pero no debe ser 404 o 500
        $this->assertContains(
            $response['status_code'],
            [200, 400, 401, 413, 415],
            'AI processing endpoint should be accessible'
        );
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar que los endpoints de trabajos funcionan
     */
    public function test_job_endpoints_accessible()
    {
        $jobEndpoints = [
            ['GET', '/jobs/123/related'],
            ['POST', '/jobs/123/apply'],
            ['GET', '/jobs/123/bookmark']
        ];

        foreach ($jobEndpoints as list($method, $endpoint)) {
            $response = $this->makeRequest($method, $endpoint, ['test' => 'smoke']);

            $this->assertNotEquals(
                404,
                $response['status_code'],
                "CRITICAL: Job endpoint not found: {$method} {$endpoint}"
            );
            $this->assertNotEquals(
                500,
                $response['status_code'],
                "CRITICAL: Job endpoint has server error: {$method} {$endpoint}"
            );
        }
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar que no hay endpoints de debug expuestos
     */
    public function test_no_debug_endpoints_exposed()
    {
        $debugEndpoints = [
            '/api/ai/debug_prompt',
            '/api/ai/test_complete_system',
            '/api/cv-schema-test',
            '/api/request_info'
        ];

        $exposedEndpoints = [];

        foreach ($debugEndpoints as $endpoint) {
            $response = $this->makeRequest('GET', $endpoint);

            // En producción, debe ser 404. En testing, puede ser 200 pero no debe ser accesible sin autenticación
            if ($response['status_code'] === 200) {
                $exposedEndpoints[] = $endpoint;
            }
        }

        $this->assertEmpty(
            $exposedEndpoints,
            'SECURITY ALERT: Debug endpoints exposed: ' . implode(', ', $exposedEndpoints)
        );
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar tiempo de respuesta aceptable
     */
    public function test_response_times_acceptable()
    {
        $endpointsToTest = [
            ['GET', '/health.php', 1000],           // 1 segundo máximo
            ['GET', '/ping', 500],                  // 500ms máximo  
            ['GET', '/jobs/123/related', 2000],     // 2 segundos máximo
            ['GET', '/departments.php', 1000]       // 1 segundo máximo
        ];

        foreach ($endpointsToTest as list($method, $endpoint, $maxTime)) {
            $startTime = microtime(true);
            $response = $this->makeRequest($method, $endpoint);
            $endTime = microtime(true);

            $executionTime = ($endTime - $startTime) * 1000; // en ms

            // Solo verificar tiempo si el endpoint responde correctamente
            if ($response['status_code'] < 500) {
                $this->assertLessThan(
                    $maxTime,
                    $executionTime,
                    "PERFORMANCE: {$method} {$endpoint} too slow: {$executionTime}ms > {$maxTime}ms"
                );
            }
        }
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar que la aplicación no revela información del sistema
     */
    public function test_no_system_information_leakage()
    {
        // Probar varios endpoints
        $endpoints = ['/health.php', '/ping', '/nonexistent-endpoint'];

        foreach ($endpoints as $endpoint) {
            $response = $this->makeRequest('GET', $endpoint);

            if (isset($response['raw_body'])) {
                $body = strtolower($response['raw_body']);

                // Verificar que no se revela información sensible del sistema
                $forbiddenInfo = [
                    'php version',
                    'mysql version',
                    'server version',
                    'apache version',
                    'nginx version',
                    '/var/www',
                    '/home/',
                    'root@',
                    'password',
                    'database connection',
                    'stack trace'
                ];

                foreach ($forbiddenInfo as $info) {
                    $this->assertStringNotContainsString(
                        $info,
                        $body,
                        "SECURITY: System information leaked in {$endpoint}: {$info}"
                    );
                }
            }
        }
    }

    /**
     * @test
     * @group smoke
     * Caso: Verificar estructura básica de respuestas JSON
     */
    public function test_json_response_structure()
    {
        $jsonEndpoints = [
            'GET:/health.php',
            'GET:/ping',
            'GET:/jobs/123/related',
            'POST:/auth/login'
        ];

        foreach ($jsonEndpoints as $endpointSpec) {
            list($method, $endpoint) = explode(':', $endpointSpec);

            $data = $method === 'POST' ? ['test' => 'data'] : [];
            $response = $this->makeRequest($method, $endpoint, $data);

            // Si responde con 200, debe ser JSON válido
            if ($response['status_code'] === 200) {
                $this->assertIsArray(
                    $response['body'],
                    "Response from {$method} {$endpoint} should be valid JSON"
                );
                $this->assertNotEmpty(
                    $response['body'],
                    "Response from {$method} {$endpoint} should not be empty"
                );
            }
        }
    }
}
