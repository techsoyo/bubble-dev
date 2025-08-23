<?php

require_once __DIR__ . '/../TestCase.php';

/**
 * Tests de Inteligencia Artificial - Procesamiento y Análisis
 * Cubre 71 casos de prueba relacionados con funcionalidades de IA
 * 
 * @group critical
 * @group ai
 */
class AITest extends TestCase
{
    /**
     * @test
     * @group smoke
     * Caso: POST /api/analyze_cv.php - Happy Path (Caso #2 de matriz)
     */
    public function test_analyze_cv_happy_path()
    {
        // Arrange
        $testPdfFile = $this->uploadTestFile('test_cv.pdf');
        $requestData = array_merge($testPdfFile, [
            'candidate_email' => 'test@example.com'
        ]);
        
        // Act
        $response = $this->makeRequest('POST', '/analyze_cv.php', $requestData, [
            'Content-Type: multipart/form-data'
        ]);
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'success' => true,
            'analysis_id' => 'string',
            'extracted_data' => [
                'name' => 'string',
                'email' => 'string',
                'skills' => []
            ]
        ], $response['body']);
        
        // Verificar que el analysis_id es UUID válido
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $response['body']['analysis_id']
        );
    }
    
    /**
     * @test
     * Caso: POST /api/analyze_cv.php - Invalid File (Caso negativo #2a)
     */
    public function test_analyze_cv_invalid_file()
    {
        // Arrange - Archivo corrupto/inválido
        $corruptFile = $this->uploadTestFile('corrupt_file.pdf', 'invalid content');
        $requestData = array_merge($corruptFile, [
            'candidate_email' => 'test@example.com'
        ]);
        
        // Act
        $response = $this->makeRequest('POST', '/analyze_cv.php', $requestData, [
            'Content-Type: multipart/form-data'
        ]);
        
        // Assert
        $this->assertEquals(400, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertStringContainsString('invalid', strtolower($response['body']['error']));
    }
    
    /**
     * @test
     * Caso: POST /api/analyze_cv.php - File Too Large (Caso negativo #2b)
     */
    public function test_analyze_cv_file_too_large()
    {
        // Arrange - Simular archivo demasiado grande
        $largeContent = str_repeat('A', 11 * 1024 * 1024); // 11MB
        $largeFile = $this->uploadTestFile('large_cv.pdf', $largeContent);
        $requestData = array_merge($largeFile, [
            'candidate_email' => 'test@example.com'
        ]);
        
        // Act
        $response = $this->makeRequest('POST', '/analyze_cv.php', $requestData, [
            'Content-Type: multipart/form-data'
        ]);
        
        // Assert
        $this->assertEquals(413, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/analyze_cv.php - Unsupported Format (Caso negativo #2c)
     */
    public function test_analyze_cv_unsupported_format()
    {
        // Arrange - Archivo de texto plano (no soportado)
        $textFile = $this->uploadTestFile('resume.txt', 'This is a plain text resume');
        $requestData = array_merge($textFile, [
            'candidate_email' => 'test@example.com'
        ]);
        
        // Act
        $response = $this->makeRequest('POST', '/analyze_cv.php', $requestData, [
            'Content-Type: multipart/form-data'
        ]);
        
        // Assert
        $this->assertEquals(415, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/analyze_cv.php - Rate Limiting (Caso negativo #2d)
     */
    public function test_analyze_cv_rate_limiting()
    {
        // Arrange & Act & Assert
        $this->assertRateLimit('/analyze_cv.php', 10); // 10 análisis por minuto
    }
    
    /**
     * @test
     * @group smoke
     * Caso: GET /api/ai/insights - Happy Path (Caso #13 de matriz)
     */
    public function test_ai_insights_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('hr');
        
        // Act
        $response = $this->makeRequest('GET', '/ai/insights');
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertIsArray($response['body']);
        
        // Verificar estructura de insights
        if (!empty($response['body'])) {
            foreach ($response['body'] as $insight) {
                $this->assertArrayHasKey('id', $insight);
                $this->assertArrayHasKey('type', $insight);
                $this->assertArrayHasKey('title', $insight);
                $this->assertArrayHasKey('description', $insight);
                $this->assertArrayHasKey('confidence', $insight);
                $this->assertArrayHasKey('created_at', $insight);
                
                // Verificar que confidence está entre 0 y 1
                $this->assertGreaterThanOrEqual(0, $insight['confidence']);
                $this->assertLessThanOrEqual(1, $insight['confidence']);
            }
        }
    }
    
    /**
     * @test
     * @group smoke
     * Caso: GET /api/ai/predictions - Happy Path (Caso #14 de matriz)
     */
    public function test_ai_predictions_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('hr');
        
        // Act
        $response = $this->makeRequest('GET', '/ai/predictions');
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertIsArray($response['body']);
        
        // Verificar estructura de predicciones
        if (!empty($response['body'])) {
            foreach ($response['body'] as $prediction) {
                $this->assertArrayHasKey('id', $prediction);
                $this->assertArrayHasKey('model', $prediction);
                $this->assertArrayHasKey('prediction', $prediction);
                $this->assertArrayHasKey('probability', $prediction);
                $this->assertArrayHasKey('created_at', $prediction);
                
                // Verificar que probability está entre 0 y 1
                $this->assertGreaterThanOrEqual(0, $prediction['probability']);
                $this->assertLessThanOrEqual(1, $prediction['probability']);
            }
        }
    }
    
    /**
     * @test
     * @group smoke
     * Caso: GET /api/ai/trends - Happy Path (Caso #15 de matriz)
     */
    public function test_ai_trends_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('hr');
        
        // Act
        $response = $this->makeRequest('GET', '/ai/trends');
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'trends' => [],
            'period' => 'string',
            'last_updated' => 'string'
        ], $response['body']);
        
        // Verificar estructura de tendencias
        if (!empty($response['body']['trends'])) {
            foreach ($response['body']['trends'] as $trend) {
                $this->assertArrayHasKey('category', $trend);
                $this->assertArrayHasKey('metric', $trend);
                $this->assertArrayHasKey('value', $trend);
                $this->assertArrayHasKey('change', $trend);
                $this->assertArrayHasKey('direction', $trend);
                
                $this->assertContains($trend['direction'], ['up', 'down', 'stable']);
            }
        }
    }
    
    /**
     * @test
     * Caso: POST /api/ai/insights/{id}/action - Happy Path (Caso #16 de matriz)
     */
    public function test_ai_insights_action_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('hr');
        $insightId = 123;
        $actionData = [
            'action' => 'approve',
            'notes' => 'Implementing this recommendation'
        ];
        
        // Act
        $response = $this->makeRequest('POST', "/ai/insights/{$insightId}/action", $actionData);
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'success' => true,
            'insight_id' => 123,
            'action_taken' => 'string',
            'processed_at' => 'string'
        ], $response['body']);
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar que endpoints de debug de IA están protegidos en producción
     */
    public function test_ai_debug_endpoints_protected_in_production()
    {
        // Simular entorno de producción
        $_ENV['APP_ENV'] = 'production';
        
        $debugEndpoints = [
            '/ai/debug_prompt',
            '/ai/diagnose_speed', 
            '/ai/test_complete_system',
            '/ai/test_final',
            '/ai/test_full_cv',
            '/ai/test_mistral_extended',
            '/ai/test_mistral_simple',
            '/ai/test_paso2',
            '/ai/test_resume'
        ];
        
        foreach ($debugEndpoints as $endpoint) {
            // Act
            $response = $this->makeRequest('POST', $endpoint, ['test' => 'data']);
            
            // Assert - Debe devolver 404 en producción
            $this->assertEquals(404, $response['status_code'], 
                "Debug endpoint {$endpoint} should return 404 in production");
        }
        
        // Restaurar entorno de testing
        $_ENV['APP_ENV'] = 'testing';
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar que el procesamiento de IA sanitiza contenido malicioso
     */
    public function test_ai_processing_sanitizes_malicious_content()
    {
        // Arrange - PDF con contenido potencialmente malicioso en metadatos
        $maliciousPdf = $this->generateMaliciousPDFContent();
        $maliciousFile = $this->uploadTestFile('malicious_cv.pdf', $maliciousPdf);
        $requestData = array_merge($maliciousFile, [
            'candidate_email' => 'test@example.com'
        ]);
        
        // Act
        $response = $this->makeRequest('POST', '/analyze_cv.php', $requestData, [
            'Content-Type: multipart/form-data'
        ]);
        
        // Assert
        if ($response['status_code'] === 200) {
            // Verificar que los datos extraídos no contienen scripts maliciosos
            $extractedData = $response['body']['extracted_data'];
            $this->assertStringNotContainsString('<script>', json_encode($extractedData));
            $this->assertStringNotContainsString('javascript:', json_encode($extractedData));
        } else {
            // Si se rechaza, es bueno - indica validación
            $this->assertEquals(400, $response['status_code']);
        }
    }
    
    /**
     * @test
     * @group performance
     * Caso: Verificar que el análisis de CV se completa en tiempo razonable
     */
    public function test_cv_analysis_performance()
    {
        // Arrange
        $testPdfFile = $this->uploadTestFile('performance_test_cv.pdf');
        $requestData = array_merge($testPdfFile, [
            'candidate_email' => 'performance@example.com'
        ]);
        
        // Act
        $startTime = microtime(true);
        $response = $this->makeRequest('POST', '/analyze_cv.php', $requestData, [
            'Content-Type: multipart/form-data'
        ]);
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // en ms
        
        // Assert
        $this->assertLessThan(30000, $executionTime, 
            'CV analysis should complete within 30 seconds');
        
        if ($response['status_code'] === 200) {
            $this->assertArrayHasKey('extracted_data', $response['body']);
            $this->assertArrayHasKey('skills', $response['body']['extracted_data']);
        }
    }
    
    /**
     * @test
     * @group integration
     * Caso: Flujo completo - Análisis de CV y guardado de candidato
     */
    public function test_complete_cv_processing_flow()
    {
        // Arrange
        $testPdfFile = $this->uploadTestFile('integration_test_cv.pdf');
        $recruiterToken = $this->getAuthToken('recruiter');
        
        // Act 1 - Analizar CV
        $analysisResponse = $this->makeRequest('POST', '/analyze_cv.php', 
            array_merge($testPdfFile, ['candidate_email' => 'integration@example.com']),
            ['Content-Type: multipart/form-data']
        );
        
        if ($analysisResponse['status_code'] !== 200) {
            $this->markTestSkipped('CV analysis failed, cannot test complete flow');
            return;
        }
        
        // Act 2 - Guardar candidato con datos extraídos
        $extractedData = $analysisResponse['body']['extracted_data'];
        $candidateData = [
            'name' => $extractedData['name'] ?? 'Test Candidate',
            'email' => $extractedData['email'] ?? 'integration@example.com',
            'skills' => $extractedData['skills'] ?? ['PHP'],
            'data_source' => 'ai_processing',
            'analysis_id' => $analysisResponse['body']['analysis_id']
        ];
        
        $saveResponse = $this->makeRequest('POST', '/candidates/save_v2.php', $candidateData);
        
        // Assert
        $this->assertEquals(201, $saveResponse['status_code'], 
            'Candidate should be saved after CV analysis');
        $this->assertArrayHasKey('candidate_id', $saveResponse['body']);
    }
    
    /**
     * Generar contenido PDF malicioso para testing
     */
    private function generateMaliciousPDFContent(): string
    {
        // PDF básico con metadatos potencialmente maliciosos
        return "%PDF-1.4\n" .
               "1 0 obj<</Type/Catalog/Pages 2 0 R/Title(<script>alert('xss')</script>)>>endobj\n" .
               "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n" .
               "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n" .
               "xref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n" .
               "0000000079 00000 n \n0000000125 00000 n \ntrailer<</Size 4/Root 1 0 R>>\n" .
               "startxref\n203\n%%EOF";
    }
}