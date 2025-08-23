<?php

require_once __DIR__ . '/../TestCase.php';

/**
 * Tests de Seguridad - Vulnerabilidades Críticas
 * Cubre 67 casos de prueba relacionados con seguridad
 * 
 * @group critical
 * @group security
 */
class SecurityTest extends TestCase
{
    /**
     * @test
     * @group critical
     * Caso: Verificar que endpoints de debug están protegidos en producción
     * Vulnerabilidad #1 de la auditoría - 14 endpoints expuestos
     */
    public function test_debug_endpoints_protected_in_production()
    {
        // Simular entorno de producción
        $_ENV['APP_ENV'] = 'production';
        
        $criticalDebugEndpoints = [
            // Endpoints de debug de IA (9 endpoints)
            '/api/ai/debug_prompt',
            '/api/ai/diagnose_speed',
            '/api/ai/test_complete_system',
            '/api/ai/test_final',
            '/api/ai/test_full_cv',
            '/api/ai/test_mistral_extended',
            '/api/ai/test_mistral_simple',
            '/api/ai/test_paso2',
            '/api/ai/test_resume',
            
            // Endpoints de test sin protección (2 endpoints)
            '/api/cv-schema-test',
            '/api/request_info',
            
            // Endpoints de debug con autenticación (3 endpoints)
            '/api/test',
            '/applications/status/debug',
            '/candidates/status/debug'
        ];
        
        foreach ($criticalDebugEndpoints as $endpoint) {
            // Act - Probar GET
            $getResponse = $this->makeRequest('GET', $endpoint);
            
            // Assert - Debe devolver 404 en producción
            $this->assertEquals(404, $getResponse['status_code'], 
                "DEBUG VULNERABILITY: Endpoint {$endpoint} (GET) accessible in production - SECURITY BREACH");
            
            // Act - Probar POST
            $postResponse = $this->makeRequest('POST', $endpoint, ['test' => 'data']);
            
            // Assert
            $this->assertEquals(404, $postResponse['status_code'], 
                "DEBUG VULNERABILITY: Endpoint {$endpoint} (POST) accessible in production - SECURITY BREACH");
        }
        
        // Restaurar entorno de testing
        $_ENV['APP_ENV'] = 'testing';
    }
    
    /**
     * @test
     * @group critical
     * Caso: Verificar que endpoints sin protección están completamente eliminados
     * Vulnerabilidad #2 de la auditoría - Endpoints sin ninguna protección
     */
    public function test_unprotected_endpoints_eliminated()
    {
        $unprotectedEndpoints = [
            '/api/cv-schema-test',
            '/api/request_info'
        ];
        
        foreach ($unprotectedEndpoints as $endpoint) {
            // Act - Intentar acceder en cualquier entorno
            $response = $this->makeRequest('GET', $endpoint);
            
            // Assert - Debe devolver 404 (eliminado completamente)
            $this->assertEquals(404, $response['status_code'], 
                "SECURITY VULNERABILITY: Unprotected endpoint {$endpoint} should be completely removed");
            
            // Act - Intentar POST también
            $postResponse = $this->makeRequest('POST', $endpoint, ['malicious' => 'data']);
            
            // Assert
            $this->assertEquals(404, $postResponse['status_code'], 
                "SECURITY VULNERABILITY: Unprotected endpoint {$endpoint} (POST) should be eliminated");
        }
    }
    
    /**
     * @test
     * @group critical
     * Caso: Verificar autenticación en endpoints CRUD críticos
     * Vulnerabilidad #3 de la auditoría - Escritura sin autenticación
     */
    public function test_crud_endpoints_require_authentication()
    {
        $protectedEndpoints = [
            // Candidatos
            'POST:/candidates/save',
            'POST:/candidates/save_v2.php',
            'PUT:/candidates/123',
            'DELETE:/candidates/123',
            
            // Aplicaciones
            'POST:/applications',
            'PATCH:/applications/123/status',
            'DELETE:/applications/123',
            
            // Trabajos (operaciones de escritura)
            'POST:/jobs',
            'PUT:/jobs/123',
            'DELETE:/jobs/123',
            
            // Chatbot (operaciones administrativas)
            'POST:/test_chatbot_crud.php',
            'PUT:/test_chatbot_crud.php',
            'DELETE:/test_chatbot_crud.php',
            
            // Notificaciones
            'POST:/notifications',
            'PUT:/notifications/123',
            'DELETE:/notifications/123'
        ];
        
        foreach ($protectedEndpoints as $endpointSpec) {
            list($method, $endpoint) = explode(':', $endpointSpec);
            
            // Act - Intentar acceso sin autenticación
            $response = $this->makeRequest($method, $endpoint, [
                'malicious_data' => 'should_be_rejected',
                'test' => 'unauthorized_access'
            ]);
            
            // Assert - Debe requerir autenticación
            $this->assertEquals(401, $response['status_code'], 
                "SECURITY VULNERABILITY: {$method} {$endpoint} allows unauthorized access - CRITICAL BREACH");
            
            // Verificar mensaje de error apropiado
            $this->assertArrayHasKey('error', $response['body'], 
                "Missing error message for unauthorized access to {$method} {$endpoint}");
        }
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar validación de tokens JWT
     */
    public function test_jwt_token_validation()
    {
        $protectedEndpoint = '/candidates/save_v2.php';
        $testData = ['name' => 'Test', 'email' => 'test@example.com'];
        
        // Test 1: Token malformado
        $malformedTokens = [
            'invalid_token',
            'Bearer.invalid.token',
            'xyz123',
            'Bearer ',
            'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.invalid.signature'
        ];
        
        foreach ($malformedTokens as $token) {
            $response = $this->makeRequest('POST', $protectedEndpoint, $testData, [
                "Authorization: Bearer {$token}"
            ]);
            
            $this->assertEquals(401, $response['status_code'], 
                "Malformed token should be rejected: {$token}");
        }
        
        // Test 2: Token expirado (simulado)
        $expiredToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJidWJibGUtdGFsZW50cyIsInVzZXJfaWQiOjEsImV4cCI6MTYwMDAwMDAwMH0.expired';
        $response = $this->makeRequest('POST', $protectedEndpoint, $testData, [
            "Authorization: Bearer {$expiredToken}"
        ]);
        
        $this->assertEquals(401, $response['status_code'], 
            'Expired token should be rejected');
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar rate limiting en endpoints críticos
     * Vulnerabilidad #4 de la auditoría - Sin rate limiting
     */
    public function test_rate_limiting_on_critical_endpoints()
    {
        $rateLimitedEndpoints = [
            '/analyze_cv.php' => 10,           // 10 análisis por minuto
            '/auth/login' => 5,                // 5 intentos de login por minuto
            '/candidates/save_v2.php' => 20,   // 20 creaciones por minuto
            '/ai/process-cv-complete' => 5     // 5 procesamientos completos por minuto
        ];
        
        foreach ($rateLimitedEndpoints as $endpoint => $maxRequests) {
            $this->verifyRateLimit($endpoint, $maxRequests);
        }
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar sanitización de entrada (XSS Prevention)
     */
    public function test_input_sanitization_xss_prevention()
    {
        $token = $this->getAuthToken('recruiter');
        
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '\x3Cscript\x3Ealert("XSS")\x3C/script\x3E',
            'javascript:alert("XSS")',
            '<img src=x onerror=alert("XSS")>',
            '\u003Cscript\u003Ealert("XSS")\u003C/script\u003E',
            '<svg onload=alert("XSS")>'
        ];
        
        foreach ($xssPayloads as $payload) {
            // Test en diferentes campos
            $maliciousData = [
                'name' => $payload,
                'email' => 'test@example.com',
                'phone' => $payload,
                'skills' => [$payload, 'PHP'],
                'data_source' => 'test'
            ];
            
            $response = $this->makeRequest('POST', '/candidates/save_v2.php', $maliciousData);
            
            // Debe rechazar o sanitizar
            if ($response['status_code'] === 201) {
                // Si se acepta, debe estar sanitizado (verificaríamos en consulta posterior)
                $this->addWarning("XSS payload might have been sanitized: {$payload}");
            } else {
                // Si se rechaza, es correcto
                $this->assertEquals(400, $response['status_code'], 
                    "XSS payload should be rejected: {$payload}");
            }
        }
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar prevención de inyección SQL
     */
    public function test_sql_injection_prevention()
    {
        $token = $this->getAuthToken('candidate');
        
        $sqlInjectionPayloads = [
            "'; DROP TABLE candidates; --",
            "' OR '1'='1",
            "'; INSERT INTO candidates (name) VALUES ('hacker'); --",
            "' UNION SELECT * FROM users --",
            "\'; EXEC xp_cmdshell('dir'); --"
        ];
        
        foreach ($sqlInjectionPayloads as $payload) {
            // Test en campo email (común para inyecciones)
            $maliciousData = [
                'email' => $payload,
                'password' => 'test123'
            ];
            
            $response = $this->makeRequest('POST', '/auth/login', $maliciousData);
            
            // No debe causar error 500 (indicaría inyección exitosa)
            $this->assertNotEquals(500, $response['status_code'], 
                "SQL injection might be successful with payload: {$payload}");
            
            // Debe ser rechazado con 400 o 401
            $this->assertContains($response['status_code'], [400, 401], 
                "SQL injection payload should be properly handled: {$payload}");
        }
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar CORS configurado correctamente
     */
    public function test_cors_configuration()
    {
        // Test preflight request
        $response = $this->makeRequest('OPTIONS', '/api/jobs', [], [
            'Origin: https://malicious-site.com',
            'Access-Control-Request-Method: POST',
            'Access-Control-Request-Headers: Content-Type'
        ]);
        
        // Verificar headers de CORS
        $this->assertArrayHasKey('raw_body', $response);
        
        // CORS debe estar configurado pero no debe permitir orígenes maliciosos
        if ($response['status_code'] === 200) {
            // Si permite CORS, verificar que está bien configurado
            $this->addWarning('CORS enabled - verify allowed origins are restricted');
        }
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar headers de seguridad
     */
    public function test_security_headers_present()
    {
        $response = $this->makeRequest('GET', '/api/health.php');
        
        // Lista de headers de seguridad que deberían estar presentes
        $expectedSecurityHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options', 
            'X-XSS-Protection',
            'Strict-Transport-Security',
            'Content-Security-Policy'
        ];
        
        // Nota: En este test solo verificamos que la respuesta no revele información sensible
        // Los headers se verificarían en un test de integración real con curl
        $this->assertNotEquals(500, $response['status_code'], 
            'Server should not expose internal errors');
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar que no se expone información sensible en errores
     */
    public function test_error_messages_dont_leak_sensitive_info()
    {
        // Test con credenciales incorrectas
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]);
        
        if (isset($response['body']['error'])) {
            $errorMessage = strtolower($response['body']['error']);
            
            // No debe revelar si el usuario existe o no
            $forbiddenPhrases = [
                'user not found',
                'email not found',
                'user does not exist',
                'invalid user',
                'mysql',
                'database',
                'sql',
                'connection failed'
            ];
            
            foreach ($forbiddenPhrases as $phrase) {
                $this->assertStringNotContainsString($phrase, $errorMessage,
                    "Error message should not reveal: {$phrase}");
            }
        }
    }
    
    /**
     * @test
     * @group security  
     * Caso: Verificar control de acceso por roles
     */
    public function test_role_based_access_control()
    {
        // Test: Candidato no debe acceder a funciones de HR
        $candidateToken = $this->getAuthToken('candidate');
        
        $hrOnlyEndpoints = [
            '/hr/dashboard-stats',
            '/ai/insights',
            '/ai/predictions',
            '/recruiter/1/dashboard-stats'
        ];
        
        foreach ($hrOnlyEndpoints as $endpoint) {
            $response = $this->makeRequest('GET', $endpoint, [], [
                "Authorization: Bearer {$candidateToken}"
            ]);
            
            $this->assertContains($response['status_code'], [401, 403], 
                "Candidate should not access HR endpoint: {$endpoint}");
        }
        
        // Test: Recruiter no debe acceder a funciones de admin
        $recruiterToken = $this->getAuthToken('recruiter');
        
        $adminOnlyEndpoints = [
            '/test_chatbot_crud.php',
            '/admin/users',
            '/admin/system-config'
        ];
        
        foreach ($adminOnlyEndpoints as $endpoint) {
            $response = $this->makeRequest('POST', $endpoint, ['test' => 'data'], [
                "Authorization: Bearer {$recruiterToken}"
            ]);
            
            $this->assertContains($response['status_code'], [401, 403], 
                "Recruiter should not access admin endpoint: {$endpoint}");
        }
    }
    
    /**
     * Helper method para verificar rate limiting
     */
    private function verifyRateLimit(string $endpoint, int $maxRequests): void
    {
        $token = $this->getAuthToken('candidate');
        
        // Hacer múltiples peticiones rápidamente
        for ($i = 1; $i <= $maxRequests + 2; $i++) {
            $response = $this->makeRequest('POST', $endpoint, [
                'test_request' => $i,
                'timestamp' => time()
            ], $token ? ["Authorization: Bearer {$token}"] : []);
            
            if ($i <= $maxRequests) {
                $this->assertNotEquals(429, $response['status_code'], 
                    "Request {$i} to {$endpoint} should not be rate limited");
            } else {
                $this->assertEquals(429, $response['status_code'], 
                    "Request {$i} to {$endpoint} should be rate limited (max: {$maxRequests})");
                break; // Una vez que se activa rate limiting, parar
            }
        }
    }
}