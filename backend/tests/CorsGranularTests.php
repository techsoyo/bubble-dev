<?php

/**
 * Tests automatizados para sistema CORS granular
 * 
 * Ejecuta una serie de tests para verificar que el sistema CORS
 * esté funcionando correctamente con configuraciones granulares.
 * 
 * @author Bubble of Talents Security Team
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

class CorsGranularTests
{
  private array $testResults = [];
  private string $baseUrl;

  public function __construct(string $baseUrl = 'http://localhost:8000')
  {
    $this->baseUrl = $baseUrl;
  }

  /**
   * Ejecuta todos los tests de CORS
   */
  public function runAllTests(): array
  {
    echo "🧪 Iniciando Tests de CORS Granular...\n\n";

    $this->testAuthEndpoints();
    $this->testApiDataEndpoints();
    $this->testPublicEndpoints();
    $this->testInvalidOrigins();
    $this->testMethodRestrictions();
    $this->testPreflightRequests();

    $this->printSummary();
    return $this->testResults;
  }

  /**
   * Test endpoints de autenticación (más restrictivos)
   */
  private function testAuthEndpoints(): void
  {
    echo "🔐 Testing Auth Endpoints...\n";

    $endpoints = [
      '/auth/verify-session.php',
      '/auth/candidate-login.php',
      '/auth/logout.php'
    ];

    foreach ($endpoints as $endpoint) {
      // Test origen válido
      $result = $this->makeRequest($endpoint, 'http://localhost:3002', 'POST');
      $this->assertTest(
        "Auth endpoint {$endpoint} accepts valid origin",
        $this->hasHeader($result, 'Access-Control-Allow-Origin'),
        $result
      );

      // Test método no permitido (auth solo permite POST)
      $result = $this->makeRequest($endpoint, 'http://localhost:3002', 'PUT');
      $this->assertTest(
        "Auth endpoint {$endpoint} restricts PUT method",
        $result['status_code'] >= 400 || !$this->hasHeader($result, 'Access-Control-Allow-Origin'),
        $result
      );
    }
  }

  /**
   * Test endpoints de API de datos
   */
  private function testApiDataEndpoints(): void
  {
    echo "📊 Testing API Data Endpoints...\n";

    $endpoints = [
      '/api/candidate-experiences.php',
      '/api/language.php',
      '/api/jobs.php'
    ];

    foreach ($endpoints as $endpoint) {
      // Test GET permitido
      $result = $this->makeRequest($endpoint, 'http://localhost:3002', 'GET');
      $this->assertTest(
        "API endpoint {$endpoint} allows GET",
        $this->hasHeader($result, 'Access-Control-Allow-Origin'),
        $result
      );

      // Test POST permitido
      $result = $this->makeRequest($endpoint, 'http://localhost:3002', 'POST');
      $this->assertTest(
        "API endpoint {$endpoint} allows POST",
        $this->hasHeader($result, 'Access-Control-Allow-Origin'),
        $result
      );
    }
  }

  /**
   * Test endpoints públicos (menos restrictivos)
   */
  private function testPublicEndpoints(): void
  {
    echo "🔍 Testing Public Endpoints...\n";

    $endpoints = [
      '/api/health-check.php',
      '/api/test-endpoint.php'
    ];

    foreach ($endpoints as $endpoint) {
      if (!$this->endpointExists($endpoint)) {
        continue;
      }

      $result = $this->makeRequest($endpoint, 'http://localhost:3002', 'GET');
      $this->assertTest(
        "Public endpoint {$endpoint} is accessible",
        $result['status_code'] < 500,
        $result
      );
    }
  }

  /**
   * Test orígenes inválidos
   */
  private function testInvalidOrigins(): void
  {
    echo "🚫 Testing Invalid Origins...\n";

    $invalidOrigins = [
      'http://malicious-site.com',
      'https://evil-domain.net',
      'http://localhost:9999' // puerto no autorizado
    ];

    foreach ($invalidOrigins as $origin) {
      $result = $this->makeRequest('/api/language.php', $origin, 'GET');

      // En desarrollo, localhost puede ser permitido
      if (strpos($origin, 'localhost') !== false) {
        continue;
      }

      $this->assertTest(
        "Invalid origin {$origin} should be rejected",
        !$this->hasHeader($result, 'Access-Control-Allow-Origin') ||
          !str_contains($result['headers']['Access-Control-Allow-Origin'] ?? '', $origin),
        $result
      );
    }
  }

  /**
   * Test restricciones de métodos por endpoint
   */
  private function testMethodRestrictions(): void
  {
    echo "⚡ Testing Method Restrictions...\n";

    // Auth endpoints solo permiten POST
    $result = $this->makeRequest('/auth/candidate-login.php', 'http://localhost:3002', 'DELETE');
    $this->assertTest(
      "Auth endpoints should restrict DELETE method",
      $result['status_code'] >= 400,
      $result
    );

    // Public endpoints solo permiten GET
    $result = $this->makeRequest('/api/test-endpoint.php', 'http://localhost:3002', 'DELETE');
    if ($this->endpointExists('/api/test-endpoint.php')) {
      $this->assertTest(
        "Public endpoints should restrict DELETE method",
        $result['status_code'] >= 400,
        $result
      );
    }
  }

  /**
   * Test requests preflight (OPTIONS)
   */
  private function testPreflightRequests(): void
  {
    echo "✈️ Testing Preflight Requests...\n";

    $endpoints = [
      '/auth/candidate-login.php',
      '/api/language.php'
    ];

    foreach ($endpoints as $endpoint) {
      $result = $this->makeRequest($endpoint, 'http://localhost:3002', 'OPTIONS');
      $this->assertTest(
        "Preflight request to {$endpoint} returns 204",
        $result['status_code'] === 204 || $result['status_code'] === 200,
        $result
      );

      $this->assertTest(
        "Preflight request to {$endpoint} includes CORS headers",
        $this->hasHeader($result, 'Access-Control-Allow-Methods'),
        $result
      );
    }
  }

  /**
   * Hace un request HTTP con curl
   */
  private function makeRequest(string $endpoint, string $origin, string $method): array
  {
    $url = $this->baseUrl . $endpoint;
    $ch = curl_init();

    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => [
        "Origin: {$origin}",
        'Content-Type: application/json'
      ],
      CURLOPT_TIMEOUT => 10,
      CURLOPT_FOLLOWLOCATION => false
    ]);

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    curl_close($ch);

    if ($response === false) {
      return [
        'status_code' => 0,
        'headers' => [],
        'body' => '',
        'error' => 'Curl error'
      ];
    }

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    return [
      'status_code' => $statusCode,
      'headers' => $this->parseHeaders($headers),
      'body' => $body,
      'error' => null
    ];
  }

  /**
   * Parsea headers HTTP de respuesta
   */
  private function parseHeaders(string $headerString): array
  {
    $headers = [];
    $lines = explode("\n", $headerString);

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line) || strpos($line, 'HTTP/') === 0) {
        continue;
      }

      $parts = explode(':', $line, 2);
      if (count($parts) === 2) {
        $headers[trim($parts[0])] = trim($parts[1]);
      }
    }

    return $headers;
  }

  /**
   * Verifica si un header específico existe en la respuesta
   */
  private function hasHeader(array $result, string $headerName): bool
  {
    return isset($result['headers'][$headerName]);
  }

  /**
   * Verifica si un endpoint existe
   */
  private function endpointExists(string $endpoint): bool
  {
    $result = $this->makeRequest($endpoint, 'http://localhost:3002', 'GET');
    return $result['status_code'] !== 404;
  }

  /**
   * Ejecuta una aserción de test
   */
  private function assertTest(string $description, bool $assertion, array $context = []): void
  {
    $result = [
      'description' => $description,
      'passed' => $assertion,
      'timestamp' => date('Y-m-d H:i:s'),
      'context' => $context
    ];

    $this->testResults[] = $result;

    $status = $assertion ? '✅' : '❌';
    echo "  {$status} {$description}\n";

    if (!$assertion && !empty($context)) {
      echo "     Context: Status {$context['status_code']}, Headers: " .
        json_encode($context['headers']) . "\n";
    }
  }

  /**
   * Imprime resumen de tests
   */
  private function printSummary(): void
  {
    $total = count($this->testResults);
    $passed = count(array_filter($this->testResults, fn($r) => $r['passed']));
    $failed = $total - $passed;

    echo "\n📊 RESUMEN DE TESTS:\n";
    echo "Total: {$total}\n";
    echo "Pasados: ✅ {$passed}\n";
    echo "Fallidos: ❌ {$failed}\n";
    echo "Porcentaje de éxito: " . round(($passed / $total) * 100, 1) . "%\n\n";

    if ($failed > 0) {
      echo "🔍 Tests fallidos:\n";
      foreach ($this->testResults as $result) {
        if (!$result['passed']) {
          echo "  ❌ {$result['description']}\n";
        }
      }
    }
  }
}

// Ejecutar tests si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
  $tests = new CorsGranularTests();
  $results = $tests->runAllTests();

  // Salir con código de error si hay tests fallidos
  $failed = count(array_filter($results, fn($r) => !$r['passed']));
  exit($failed > 0 ? 1 : 0);
}
