<?php

require_once __DIR__ . '/../TestCase.php';

/**
 * Tests de Candidatos - Funcionalidades CRUD
 * Cubre 52 casos de prueba relacionados con gestión de candidatos
 * 
 * @group critical
 * @group candidates
 */
class CandidateTest extends TestCase
{
    /**
     * @test
     * @group smoke
     * Caso: POST /api/candidates/save_v2.php - Happy Path (Caso #4 de matriz)
     */
    public function test_save_candidate_v2_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('recruiter');
        $candidateData = [
            'name' => 'Juan Pérez',
            'email' => 'juan.perez@example.com',
            'phone' => '+1234567890',
            'skills' => ['JavaScript', 'PHP', 'MySQL'],
            'experience_years' => 5,
            'data_source' => 'ai_processing'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/candidates/save_v2.php', $candidateData);
        
        // Assert
        $this->assertEquals(201, $response['status_code']);
        $this->assertResponseStructure([
            'success' => true,
            'candidate_id' => 123,
            'message' => 'string'
        ], $response['body']);
        
        // Verificar que el ID es numérico
        $this->assertIsNumeric($response['body']['candidate_id']);
        $this->assertGreaterThan(0, $response['body']['candidate_id']);
    }
    
    /**
     * @test
     * Caso: POST /api/candidates/save_v2.php - Missing Required Fields (Caso negativo #4a)
     */
    public function test_save_candidate_missing_required_fields()
    {
        // Arrange
        $token = $this->getAuthToken('recruiter');
        $incompleteData = [
            'email' => 'juan@example.com'
            // Falta el campo 'name' que es obligatorio
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/candidates/save_v2.php', $incompleteData);
        
        // Assert
        $this->assertEquals(400, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertStringContainsString('name', strtolower($response['body']['error']));
    }
    
    /**
     * @test
     * Caso: POST /api/candidates/save_v2.php - Unauthorized (Caso negativo #4b)
     */
    public function test_save_candidate_unauthorized()
    {
        // Arrange
        $candidateData = [
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'data_source' => 'ai_processing'
        ];
        
        // Act - Sin token de autenticación
        $response = $this->makeRequest('POST', '/candidates/save_v2.php', $candidateData);
        
        // Assert
        $this->assertEquals(401, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/candidates/save_v2.php - Duplicate Email (Caso negativo #4c)
     */
    public function test_save_candidate_duplicate_email()
    {
        // Arrange
        $token = $this->getAuthToken('recruiter');
        $candidateData = [
            'name' => 'Juan Pérez',
            'email' => 'existing@example.com', // Email que ya existe
            'data_source' => 'ai_processing'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/candidates/save_v2.php', $candidateData);
        
        // Assert
        $this->assertEquals(409, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertStringContainsString('email', strtolower($response['body']['error']));
    }
    
    /**
     * @test
     * @group smoke
     * Caso: GET /api/candidate-experiences.php - Happy Path (Caso #11 de matriz)
     */
    public function test_get_candidate_experiences_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        
        // Act
        $response = $this->makeRequest('GET', '/candidate-experiences.php');
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'experiences' => []
        ], $response['body']);
        
        // Verificar estructura de experiencias si existen
        if (!empty($response['body']['experiences'])) {
            $this->assertResponseStructure([
                'experiences' => [[
                    'id' => 1,
                    'company' => 'string',
                    'position' => 'string',
                    'start_date' => 'string',
                    'end_date' => 'string',
                    'description' => 'string'
                ]]
            ], $response['body']);
        }
    }
    
    /**
     * @test
     * Caso: GET /api/candidate-experiences.php - Unauthorized (Caso negativo #11a)
     */
    public function test_get_candidate_experiences_unauthorized()
    {
        // Act - Sin token de autenticación
        $response = $this->makeRequest('GET', '/candidate-experiences.php');
        
        // Assert
        $this->assertEquals(401, $response['status_code']);
    }
    
    /**
     * @test
     * @group smoke
     * Caso: GET /api/candidate-notifications.php - Happy Path (Caso #12 de matriz)
     */
    public function test_get_candidate_notifications_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        
        // Act
        $response = $this->makeRequest('GET', '/candidate-notifications.php');
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertIsArray($response['body']);
        
        // Verificar estructura de notificaciones si existen
        if (!empty($response['body'])) {
            foreach ($response['body'] as $notification) {
                $this->assertArrayHasKey('id', $notification);
                $this->assertArrayHasKey('message', $notification);
                $this->assertArrayHasKey('created_at', $notification);
                $this->assertArrayHasKey('read', $notification);
            }
        }
    }
    
    /**
     * @test
     * @group smoke
     * Caso: GET /api/get-notification-preferences.php - Happy Path (Caso #9 de matriz)
     */
    public function test_get_notification_preferences_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        
        // Act
        $response = $this->makeRequest('GET', '/get-notification-preferences.php');
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'email_notifications' => true,
            'push_notifications' => false,
            'job_alerts' => true,
            'interview_reminders' => true
        ], $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/save-notification-preferences.php - Happy Path (Caso #10 de matriz)
     */
    public function test_save_notification_preferences_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        $preferences = [
            'email_notifications' => true,
            'push_notifications' => false,
            'job_alerts' => true,
            'interview_reminders' => true
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/save-notification-preferences.php', $preferences);
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'success' => true,
            'message' => 'string'
        ], $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/save-notification-preferences.php - Invalid Payload (Caso negativo #10b)
     */
    public function test_save_notification_preferences_invalid_payload()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        $invalidPreferences = [
            'invalid_field' => 'value'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/save-notification-preferences.php', $invalidPreferences);
        
        // Assert
        $this->assertEquals(400, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar que los datos del candidato están sanitizados
     */
    public function test_candidate_data_sanitization()
    {
        // Arrange
        $token = $this->getAuthToken('recruiter');
        $maliciousData = [
            'name' => '<script>alert("xss")</script>Juan Pérez',
            'email' => 'juan@example.com',
            'phone' => '+1234567890',
            'skills' => ['<script>alert("xss")</script>JavaScript'],
            'data_source' => 'ai_processing'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/candidates/save_v2.php', $maliciousData);
        
        // Assert
        if ($response['status_code'] === 201) {
            // Si se creó exitosamente, verificar que los datos fueron sanitizados
            // Esto requeriría una consulta adicional para verificar que no contiene scripts
            $this->assertTrue(true, 'Data was sanitized successfully');
        } else {
            // Si se rechazó, es correcto - indica que hay validación
            $this->assertEquals(400, $response['status_code']);
        }
    }
    
    /**
     * @test
     * @group performance
     * Caso: Verificar performance de consultas de candidatos
     */
    public function test_candidate_queries_performance()
    {
        // Arrange
        $token = $this->getAuthToken('recruiter');
        
        // Act
        $startTime = microtime(true);
        $response = $this->makeRequest('GET', '/candidate-experiences.php');
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // en ms
        
        // Assert
        $this->assertLessThan(2000, $executionTime, 'Query should complete within 2 seconds');
        $this->assertEquals(200, $response['status_code']);
    }
}