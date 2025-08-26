<?php declare(strict_types=1);
namespace Services;

use Exception;

/**
 * Codespaces AI Integration Service
 * Conecta con servidor IA corriendo en GitHub Codespaces
 */
class CodespacesService
{
  private $apiUrl;
  private $timeout;
  private $retries;

  public function __construct()
  {
    $this->apiUrl = rtrim(getenv('CODESPACES_AI_URL') ?: 'http://localhost:5000', '/');
    $this->timeout = (int) (getenv('CODESPACES_TIMEOUT_MS') ?: 30000) / 1000;
    $this->retries = (int) (getenv('AI_MAX_RETRIES') ?: 3);
  }

  /**
   * Verificar si el servicio estÃƒÆ’Ã‚Â¡ disponible
   */
  public function isAvailable(): bool
  {
    try {
      $response = $this->makeRequest('GET', '/health');
      return $response && isset($response['status']) && $response['status'] === 'healthy';
    } catch (Exception $e) {
      error_log("CodespacesService no disponible: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Generar score de matching para un candidato
   */
  public function generateMatchingScore(array $candidateData, array $jobData): ?array
  {
    try {
      $payload = [
        'candidate' => $candidateData,
        'job' => $jobData
      ];

      $response = $this->makeRequest('POST', '/match', $payload);

      if ($response && $response['success'] === true) {
        return $response['data'];
      }

      return null;
    } catch (Exception $e) {
      error_log("Error en CodespacesService::generateMatchingScore: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Ranking de mÃƒÆ’Ã‚Âºltiples candidatos
   */
  public function rankCandidates(array $candidates, array $jobData): ?array
  {
    try {
      $payload = [
        'candidates' => $candidates,
        'job' => $jobData
      ];

      $response = $this->makeRequest('POST', '/batch-match', $payload);

      if ($response && $response['success'] === true) {
        return $response['data']['ranked_candidates'];
      }

      return null;
    } catch (Exception $e) {
      error_log("Error en CodespacesService::rankCandidates: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Obtener informaciÃƒÆ’Ã‚Â³n del modelo
   */
  public function getModelInfo(): ?array
  {
    try {
      $response = $this->makeRequest('GET', '/health');

      if ($response) {
        return [
          'provider' => 'codespaces',
          'model' => $response['model'] ?? 'unknown',
          'gpu_available' => $response['gpu_available'] ?? false,
          'gpu_name' => $response['gpu_name'] ?? null,
          'status' => $response['status'] ?? 'unknown'
        ];
      }

      return null;
    } catch (Exception $e) {
      error_log("Error en CodespacesService::getModelInfo: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Realizar peticiÃƒÆ’Ã‚Â³n HTTP al servidor IA
   */
  private function makeRequest(string $method, string $endpoint, ?array $data = null): ?array
  {
    $url = $this->apiUrl . $endpoint;
    $attempt = 0;

    while ($attempt < $this->retries) {
      try {
        $ch = curl_init();

        curl_setopt_array($ch, [
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_TIMEOUT => $this->timeout,
          CURLOPT_CONNECTTIMEOUT => 10,
          CURLOPT_CUSTOMREQUEST => $method,
          CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: BubbleOfTalents/1.0'
          ]
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
          throw new Exception("cURL error: " . $error);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
          $decoded = json_decode($response, true);
          if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
          }
          throw new Exception("Invalid JSON response");
        }

        if ($httpCode >= 500) {
          // Error del servidor - reintentar
          $attempt++;
          if ($attempt < $this->retries) {
            sleep(pow(2, $attempt)); // Backoff exponencial
            continue;
          }
        }

        throw new Exception("HTTP {$httpCode}: " . substr($response, 0, 200));
      } catch (Exception $e) {
        $attempt++;
        if ($attempt >= $this->retries) {
          throw $e;
        }

        error_log("CodespacesService attempt {$attempt} failed: " . $e->getMessage());
        sleep(pow(2, $attempt));
      }
    }

    return null;
  }

  /**
   * Test de conectividad bÃƒÆ’Ã‚Â¡sico
   */
  public function testConnection(): array
  {
    $start = microtime(true);

    try {
      $health = $this->makeRequest('GET', '/health');
      $duration = round((microtime(true) - $start) * 1000);

      if ($health) {
        return [
          'success' => true,
          'response_time_ms' => $duration,
          'model' => $health['model'] ?? 'unknown',
          'gpu' => $health['gpu_available'] ?? false,
          'status' => $health['status'] ?? 'unknown'
        ];
      } else {
        return [
          'success' => false,
          'error' => 'No response from health endpoint',
          'response_time_ms' => $duration
        ];
      }
    } catch (Exception $e) {
      $duration = round((microtime(true) - $start) * 1000);
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'response_time_ms' => $duration
      ];
    }
  }
}
