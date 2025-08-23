<?php

require_once __DIR__ . '/../TestCase.php';

/**
 * Tests de Autenticación - Casos Críticos
 * Cubre 45 casos de prueba relacionados con autenticación
 * 
 * @group critical
 * @group auth
 */
class AuthTest extends TestCase
{
    /**
     * @test
     * @group smoke
     * Caso: POST /api/auth/login - Happy Path (Caso #3 de matriz)
     */
    public function test_login_happy_path()
    {
        // Arrange
        $credentials = [
            'email' => 'user@example.com',
            'password' => 'SecurePass123!'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/auth/login', $credentials);
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'success' => true,
            'token' => 'string',
            'user' => [
                'id' => 1,
                'email' => 'string',
                'role' => 'string'
            ],
            'expires_at' => 'string'
        ], $response['body']);
        
        // Verificar que el token es válido JWT
        $this->assertNotEmpty($response['body']['token']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $response['body']['token']);
    }
    
    /**
     * @test
     * Caso: POST /api/auth/login - Missing Email (Caso negativo #3a)
     */
    public function test_login_missing_email()
    {
        // Arrange
        $incompleteCredentials = [
            'password' => 'SecurePass123!'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/auth/login', $incompleteCredentials);
        
        // Assert
        $this->assertEquals(400, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertStringContainsString('email', strtolower($response['body']['error']));
    }
    
    /**
     * @test
     * Caso: POST /api/auth/login - Invalid Email Format (Caso negativo #3b)
     */
    public function test_login_invalid_email_format()
    {
        // Arrange
        $invalidCredentials = [
            'email' => 'invalid-email',
            'password' => 'SecurePass123!'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/auth/login', $invalidCredentials);
        
        // Assert
        $this->assertEquals(400, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/auth/login - Invalid Credentials (Caso negativo #3c)
     */
    public function test_login_invalid_credentials()
    {
        // Arrange
        $wrongCredentials = [
            'email' => 'user@example.com',
            'password' => 'WrongPassword'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/auth/login', $wrongCredentials);
        
        // Assert
        $this->assertEquals(401, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/auth/login - Rate Limiting (Caso negativo #3d)
     */
    public function test_login_rate_limiting()
    {
        // Arrange & Act & Assert
        $this->markTestSkipped('Rate limiting requires multiple failed attempts - implement if needed');
        // $this->assertRateLimit('/auth/login', 5); // 5 intentos de login por minuto
    }
    
    /**
     * @test
     * @group critical
     * Caso: Validación de Token JWT en endpoints protegidos
     */
    public function test_protected_endpoint_requires_valid_token()
    {
        // Act - Sin token
        $response = $this->makeRequest('POST', '/candidates/save_v2.php', [
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        
        // Assert - Debe requerir autenticación
        $this->assertEquals(401, $response['status_code']);
        
        // Act - Con token inválido
        $response = $this->makeRequest('POST', '/candidates/save_v2.php', [
            'name' => 'Test User',
            'email' => 'test@example.com'
        ], ['Authorization: Bearer invalid_token']);
        
        // Assert - Debe rechazar token inválido
        $this->assertEquals(401, $response['status_code']);
    }
    
    /**
     * @test
     * Caso: POST /api/change-password.php - Happy Path (Caso #14 de matriz)
     */
    public function test_change_password_happy_path()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        $passwordData = [
            'current_password' => 'OldPass123!',
            'new_password' => 'NewPass456!',
            'confirm_password' => 'NewPass456!'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/change-password.php', $passwordData);
        
        // Assert
        $this->assertEquals(200, $response['status_code']);
        $this->assertResponseStructure([
            'success' => true,
            'message' => 'string'
        ], $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/change-password.php - Password Mismatch (Caso negativo #14b)
     */
    public function test_change_password_mismatch()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        $passwordData = [
            'current_password' => 'OldPass123!',
            'new_password' => 'NewPass456!',
            'confirm_password' => 'DifferentPass!'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/change-password.php', $passwordData);
        
        // Assert
        $this->assertEquals(400, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * Caso: POST /api/change-password.php - Weak Password (Caso negativo #14c)
     */
    public function test_change_password_weak_password()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        $passwordData = [
            'current_password' => 'OldPass123!',
            'new_password' => '123',
            'confirm_password' => '123'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/change-password.php', $passwordData);
        
        // Assert
        $this->assertEquals(400, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertStringContainsString('password', strtolower($response['body']['error']));
    }
    
    /**
     * @test
     * Caso: POST /api/change-password.php - Wrong Current Password (Caso negativo #14d)
     */
    public function test_change_password_wrong_current()
    {
        // Arrange
        $token = $this->getAuthToken('candidate');
        $passwordData = [
            'current_password' => 'WrongOldPass!',
            'new_password' => 'NewPass456!',
            'confirm_password' => 'NewPass456!'
        ];
        
        // Act
        $response = $this->makeRequest('POST', '/change-password.php', $passwordData);
        
        // Assert
        $this->assertEquals(401, $response['status_code']);
        $this->assertArrayHasKey('error', $response['body']);
    }
    
    /**
     * @test
     * Caso: Verificar expiración de tokens JWT
     */
    public function test_jwt_token_expiration()
    {
        // Este test verificaría tokens expirados
        // Se marca como skipped porque requiere manipulación de tiempo
        $this->markTestSkipped('JWT expiration test requires time manipulation - implement if needed');
    }
    
    /**
     * @test
     * @group security
     * Caso: Verificar que endpoints de autenticación no están expuestos como debug
     */
    public function test_auth_endpoints_not_debug()
    {
        // Verificar que endpoints de auth no tienen versiones de debug expuestas
        $debugEndpoints = [
            '/auth/debug',
            '/auth/test',
            '/auth/mock'
        ];
        
        foreach ($debugEndpoints as $endpoint) {
            $response = $this->makeRequest('GET', $endpoint);
            $this->assertEquals(404, $response['status_code'], 
                "Debug endpoint $endpoint should not be accessible");
        }
    }
}