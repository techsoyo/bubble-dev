<?php

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Clase base para todos los tests automatizados
 * Implementa utilidades comunes para los 324 casos de prueba
 */
abstract class TestCase extends BaseTestCase
{
    protected $baseUrl = TEST_BASE_URL;
    protected $testData = [];
    protected $authToken = null;

    /**
     * Setup ejecutado antes de cada test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTestData();
        test_debug("Iniciando test: " . $this->name());
    }

    /**
     * Cleanup ejecutado después de cada test
     */
    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $this->authToken = null;
        test_debug("Test completado: " . $this->name());
        parent::tearDown();
    }

    /**
     * Realizar petición HTTP con configuración completa
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ];

        if ($this->authToken) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->authToken;
        }

        $allHeaders = array_merge($defaultHeaders, $headers);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if (!empty($data) && $method !== 'GET') {
            if (in_array('Content-Type: multipart/form-data', $allHeaders)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->fail("cURL Error: $error");
        }

        $decodedResponse = json_decode($response, true) ?? [];

        test_debug("Request: $method $url", [
            'data' => $data,
            'headers' => $allHeaders,
            'response_code' => $httpCode,
            'response' => $decodedResponse
        ]);

        return [
            'status_code' => $httpCode,
            'body' => $decodedResponse,
            'raw_body' => $response
        ];
    }

    /**
     * Obtener token de autenticación para testing
     */
    protected function getAuthToken(string $role = 'candidate'): string
    {
        if ($this->authToken) {
            return $this->authToken;
        }

        $credentials = $this->getTestCredentials($role);

        $response = $this->makeRequest('POST', '/auth/login', $credentials);

        $this->assertEquals(200, $response['status_code'], 'Failed to authenticate test user');
        $this->assertArrayHasKey('token', $response['body'], 'Authentication response missing token');

        $this->authToken = $response['body']['token'];
        return $this->authToken;
    }

    /**
     * Credenciales de testing por role
     */
    protected function getTestCredentials(string $role): array
    {
        $credentials = [
            'candidate' => [
                'email' => 'test.candidate@example.com',
                'password' => 'TestPass123!'
            ],
            'recruiter' => [
                'email' => 'test.recruiter@example.com',
                'password' => 'TestPass123!'
            ],
            'hr' => [
                'email' => 'test.hr@example.com',
                'password' => 'TestPass123!'
            ],
            'admin' => [
                'email' => 'test.admin@example.com',
                'password' => 'TestPass123!'
            ]
        ];

        return $credentials[$role] ?? $credentials['candidate'];
    }

    /**
     * Subir archivo de test
     */
    protected function uploadTestFile(string $filename, string $content = null): array
    {
        if ($content === null) {
            $content = $this->generateTestPDFContent();
        }

        $filepath = TEST_UPLOADS_DIR . '/' . $filename;
        file_put_contents($filepath, $content);

        return [
            'file' => new CURLFile($filepath, mime_content_type($filepath), $filename)
        ];
    }

    /**
     * Generar contenido PDF de prueba
     */
    protected function generateTestPDFContent(): string
    {
        // Contenido PDF mínimo válido para testing
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj xref\n0 4\n0000000000 65535 f \n0000000010 00000 n \n0000000053 00000 n \n0000000125 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n203\n%%EOF";
    }

    /**
     * Verificar estructura de respuesta esperada
     */
    protected function assertResponseStructure(array $expectedStructure, array $actualResponse, string $message = ''): void
    {
        foreach ($expectedStructure as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $actualResponse, "Missing key '$key' in response. $message");

            if (is_array($expectedValue)) {
                $this->assertIsArray($actualResponse[$key], "Key '$key' should be array. $message");
                if (!empty($expectedValue)) {
                    $this->assertResponseStructure($expectedValue, $actualResponse[$key], $message);
                }
            } elseif ($expectedValue !== null) {
                $this->assertEquals(
                    gettype($expectedValue),
                    gettype($actualResponse[$key]),
                    "Key '$key' has incorrect type. $message"
                );
            }
        }
    }

    /**
     * Test de rate limiting
     */
    protected function assertRateLimit(string $endpoint, int $maxRequests = 10): void
    {
        // Hacer múltiples peticiones para activar rate limiting
        for ($i = 1; $i <= $maxRequests + 1; $i++) {
            $response = $this->makeRequest('POST', $endpoint, ['test' => $i]);

            if ($i <= $maxRequests) {
                $this->assertNotEquals(
                    429,
                    $response['status_code'],
                    "Request $i should not be rate limited"
                );
            } else {
                $this->assertEquals(
                    429,
                    $response['status_code'],
                    "Request $i should be rate limited"
                );
                break;
            }
        }
    }

    /**
     * Inicializar datos de prueba
     */
    protected function initializeTestData(): void
    {
        $this->testData = [
            'valid_candidate' => [
                'name' => 'Test Candidate',
                'email' => 'candidate.test@example.com',
                'phone' => '+1234567890',
                'skills' => ['PHP', 'JavaScript', 'MySQL'],
                'experience_years' => 3
            ],
            'valid_job' => [
                'title' => 'Senior PHP Developer',
                'description' => 'We are looking for an experienced PHP developer...',
                'requirements' => ['PHP', 'MySQL', '3+ years experience'],
                'salary_min' => 50000,
                'salary_max' => 80000,
                'location' => 'Remote',
                'department_id' => 1
            ]
        ];
    }

    /**
     * Limpiar datos de prueba
     */
    protected function cleanupTestData(): void
    {
        // Limpiar archivos de prueba
        $testFiles = glob(TEST_UPLOADS_DIR . '/test_*');
        foreach ($testFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Limpiar datos de base de datos si es necesario
        // Se ejecuta automáticamente en el shutdown del bootstrap
    }
}
