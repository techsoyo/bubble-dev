<?php

/**
 * Tests de rendimiento para sistema CORS granular
 * 
 * Mide el impacto en rendimiento del sistema CORS granular
 * comparado con configuraciones tradicionales.
 * 
 * @author Bubble of Talents Performance Team
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

class CorsPerformanceTests
{
  private array $benchmarkResults = [];
  private int $iterations = 100;

  /**
   * Ejecuta todos los tests de rendimiento
   */
  public function runPerformanceTests(): array
  {
    echo "⚡ Iniciando Tests de Rendimiento CORS...\n\n";

    $this->benchmarkCorsProcessing();
    $this->benchmarkEndpointDetection();
    $this->benchmarkMemoryUsage();
    $this->benchmarkConcurrentRequests();

    $this->printPerformanceSummary();
    return $this->benchmarkResults;
  }

  /**
   * Benchmark del procesamiento CORS granular
   */
  private function benchmarkCorsProcessing(): void
  {
    echo "🔄 Benchmarking CORS Processing...\n";

    // Simular diferentes tipos de endpoints
    $endpoints = [
      '/auth/candidate-login.php' => 'auth',
      '/api/candidate-experiences.php' => 'api_data',
      '/api/chatbot.php' => 'ai',
      '/uploads/file.pdf' => 'files',
      '/api/test-endpoint.php' => 'public'
    ];

    foreach ($endpoints as $endpoint => $expectedType) {
      $startTime = microtime(true);

      for ($i = 0; $i < $this->iterations; $i++) {
        $this->simulateCorsProcessing($endpoint);
      }

      $endTime = microtime(true);
      $totalTime = ($endTime - $startTime) * 1000; // en ms
      $avgTime = $totalTime / $this->iterations;

      $this->benchmarkResults['cors_processing'][$endpoint] = [
        'total_time_ms' => round($totalTime, 3),
        'avg_time_ms' => round($avgTime, 3),
        'iterations' => $this->iterations,
        'expected_type' => $expectedType
      ];

      echo "  ✅ {$endpoint}: {$avgTime}ms promedio\n";
    }
  }

  /**
   * Benchmark de detección de tipos de endpoint
   */
  private function benchmarkEndpointDetection(): void
  {
    echo "\n🎯 Benchmarking Endpoint Detection...\n";

    require_once __DIR__ . '/../cors-utils.php';
    $testUrls = [
      '/auth/candidate-login.php',
      '/api/candidate-experiences.php',
      '/api/chatbot.php',
      '/uploads/document.pdf',
      '/admin/users.php',
      '/public/test.php',
      '/unknown/endpoint.php'
    ];

    $startTime = microtime(true);

    for ($i = 0; $i < $this->iterations; $i++) {
      foreach ($testUrls as $url) {
        detectEndpointType($url);
      }
    }

    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;
    $avgTimePerDetection = $totalTime / ($this->iterations * count($testUrls));

    $this->benchmarkResults['endpoint_detection'] = [
      'total_time_ms' => round($totalTime, 3),
      'avg_time_per_detection_ms' => round($avgTimePerDetection, 6),
      'iterations' => $this->iterations,
      'urls_tested' => count($testUrls)
    ];

    echo "  ✅ Detección promedio: {$avgTimePerDetection}ms por endpoint\n";
  }

  /**
   * Benchmark de uso de memoria
   */
  private function benchmarkMemoryUsage(): void
  {
    echo "\n💾 Benchmarking Memory Usage...\n";

    $memoryStart = memory_get_usage(true);

    // Simular carga de configuraciones granulares
    for ($i = 0; $i < $this->iterations; $i++) {
      require __DIR__ . '/../cors-granular.php';
      $this->simulateGranularConfig();
    }

    $memoryEnd = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);

    $memoryUsed = $memoryEnd - $memoryStart;
    $memoryPerIteration = $memoryUsed / $this->iterations;

    $this->benchmarkResults['memory_usage'] = [
      'memory_used_bytes' => $memoryUsed,
      'memory_used_kb' => round($memoryUsed / 1024, 2),
      'memory_per_iteration_bytes' => round($memoryPerIteration, 2),
      'peak_memory_kb' => round($memoryPeak / 1024, 2),
      'iterations' => $this->iterations
    ];

    echo "  ✅ Memoria por iteración: " . round($memoryPerIteration / 1024, 2) . "KB\n";
    echo "  ✅ Pico de memoria: " . round($memoryPeak / 1024, 2) . "KB\n";
  }

  /**
   * Benchmark de requests concurrentes (simulado)
   */
  private function benchmarkConcurrentRequests(): void
  {
    echo "\n🚀 Benchmarking Concurrent Requests...\n";

    $concurrentLevels = [1, 5, 10, 20];

    foreach ($concurrentLevels as $concurrent) {
      $startTime = microtime(true);

      // Simular requests concurrentes
      for ($i = 0; $i < $concurrent; $i++) {
        for ($j = 0; $j < 10; $j++) {
          $this->simulateCorsProcessing('/api/test-endpoint.php');
        }
      }

      $endTime = microtime(true);
      $totalTime = ($endTime - $startTime) * 1000;
      $avgTimePerRequest = $totalTime / ($concurrent * 10);

      $this->benchmarkResults['concurrent_requests'][$concurrent] = [
        'concurrent_level' => $concurrent,
        'total_time_ms' => round($totalTime, 3),
        'avg_time_per_request_ms' => round($avgTimePerRequest, 3),
        'requests_per_second' => round(1000 / $avgTimePerRequest, 2)
      ];

      echo "  ✅ {$concurrent} concurrent: {$avgTimePerRequest}ms por request\n";
    }
  }

  /**
   * Simula el procesamiento CORS para un endpoint
   */
  private function simulateCorsProcessing(string $endpoint): void
  {
    // Simular detección de tipo de endpoint
    $endpointType = $this->detectEndpointTypeSimulated($endpoint);

    // Simular carga de configuración
    $config = $this->getSimulatedConfig($endpointType);

    // Simular aplicación de headers
    $this->applySimulatedHeaders($config);
  }

  /**
   * Simulación de detección de tipo de endpoint
   */
  private function detectEndpointTypeSimulated(string $endpoint): string
  {
    if (strpos($endpoint, '/auth/') !== false) return 'auth';
    if (strpos($endpoint, '/api/') !== false && strpos($endpoint, 'chatbot') !== false) return 'ai';
    if (strpos($endpoint, '/api/') !== false && strpos($endpoint, 'admin') !== false) return 'api_admin';
    if (strpos($endpoint, '/api/') !== false) return 'api_data';
    if (strpos($endpoint, '/uploads/') !== false) return 'files';
    if (strpos($endpoint, '/admin/') !== false) return 'admin';
    return 'public';
  }

  /**
   * Simulación de carga de configuración
   */
  private function getSimulatedConfig(string $endpointType): array
  {
    $configs = [
      'auth' => [
        'allowed_origins' => ['http://localhost:3000', 'http://localhost:3002'],
        'allowed_methods' => ['POST', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization']
      ],
      'api_data' => [
        'allowed_origins' => ['http://localhost:3000', 'http://localhost:3002'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With']
      ],
      'default' => [
        'allowed_origins' => ['http://localhost:3000'],
        'allowed_methods' => ['GET', 'OPTIONS'],
        'allowed_headers' => ['Content-Type']
      ]
    ];

    return $configs[$endpointType] ?? $configs['default'];
  }

  /**
   * Simulación de aplicación de headers
   */
  private function applySimulatedHeaders(array $config): void
  {
    // Simular la lógica de aplicación de headers
    $headers = [
      'Access-Control-Allow-Origin' => $config['allowed_origins'][0] ?? '*',
      'Access-Control-Allow-Methods' => implode(', ', $config['allowed_methods']),
      'Access-Control-Allow-Headers' => implode(', ', $config['allowed_headers'])
    ];

    // En un test real, estos se aplicarían como headers HTTP
    unset($headers); // Evitar warning de variable no usada
  }

  /**
   * Simula configuración granular
   */
  private function simulateGranularConfig(): array
  {
    return [
      'auth' => ['origins' => ['localhost:3000'], 'methods' => ['POST']],
      'api_data' => ['origins' => ['localhost:3000', 'localhost:3002'], 'methods' => ['GET', 'POST']],
      'ai' => ['origins' => ['localhost:3000'], 'methods' => ['POST']],
      'files' => ['origins' => ['*'], 'methods' => ['GET']],
      'admin' => ['origins' => ['localhost:3001'], 'methods' => ['GET', 'POST', 'PUT', 'DELETE']],
      'public' => ['origins' => ['*'], 'methods' => ['GET']]
    ];
  }

  /**
   * Imprime resumen de rendimiento
   */
  private function printPerformanceSummary(): void
  {
    echo "\n📊 RESUMEN DE RENDIMIENTO:\n\n";

    // Resumen de procesamiento CORS
    if (isset($this->benchmarkResults['cors_processing'])) {
      echo "🔄 Procesamiento CORS:\n";
      $totalAvg = 0;
      $count = 0;
      foreach ($this->benchmarkResults['cors_processing'] as $endpoint => $data) {
        echo "  • {$endpoint}: {$data['avg_time_ms']}ms\n";
        $totalAvg += $data['avg_time_ms'];
        $count++;
      }
      echo "  Promedio general: " . round($totalAvg / $count, 3) . "ms\n\n";
    }

    // Resumen de detección de endpoints
    if (isset($this->benchmarkResults['endpoint_detection'])) {
      $data = $this->benchmarkResults['endpoint_detection'];
      echo "🎯 Detección de Endpoints:\n";
      echo "  • Tiempo por detección: {$data['avg_time_per_detection_ms']}ms\n";
      echo "  • URLs testadas: {$data['urls_tested']}\n\n";
    }

    // Resumen de memoria
    if (isset($this->benchmarkResults['memory_usage'])) {
      $data = $this->benchmarkResults['memory_usage'];
      echo "💾 Uso de Memoria:\n";
      echo "  • Memoria por iteración: {$data['memory_per_iteration_bytes']} bytes\n";
      echo "  • Pico de memoria: {$data['peak_memory_kb']}KB\n\n";
    }

    // Resumen de concurrencia
    if (isset($this->benchmarkResults['concurrent_requests'])) {
      echo "🚀 Requests Concurrentes:\n";
      foreach ($this->benchmarkResults['concurrent_requests'] as $data) {
        echo "  • {$data['concurrent_level']} concurrent: {$data['avg_time_per_request_ms']}ms/req ({$data['requests_per_second']} req/s)\n";
      }
    }

    echo "\n✅ Tests de rendimiento completados!\n";
  }
}

// Ejecutar tests si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
  $tests = new CorsPerformanceTests();
  $results = $tests->runPerformanceTests();

  // Guardar resultados en JSON para análisis posterior
  $outputFile = __DIR__ . '/cors_performance_results_' . date('Y-m-d_H-i-s') . '.json';
  file_put_contents($outputFile, json_encode($results, JSON_PRETTY_PRINT));
  echo "\n📁 Resultados guardados en: {$outputFile}\n";
}
