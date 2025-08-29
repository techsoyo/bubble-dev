<?php

declare(strict_types=1);

namespace Services\Providers;

use Services\Interfaces\AIProviderInterface;
use Services\Exceptions\AiUnavailableException;

/**
 * OpenAI Provider Adapter
 *
 * Adaptador para integrar OpenAIService con la interfaz unificada de IA
 * Soporta OpenAI, Azure OpenAI y servicios locales
 *
 * @package Backend\Services\Providers
 * @version 1.0.0
 * @since 2025-08-10
 */
class OpenAIProvider implements AIProviderInterface
{
  private string $provider;
  private ?string $apiKey;
  private string $apiUrl;
  private string $model;
  private int $timeoutMs;
  private int $maxTokens;
  private float $temperature;
  private bool $jsonMode;
  private int $maxRetries;
  private ?string $deployment;
  private bool $available = true;
  public function __construct()
  {
    try {
      // Cargar configuración desde .env
      $this->provider = ($_ENV['AI_PROVIDER'] ?? getenv('AI_PROVIDER')) ?: 'openai';
      $this->apiKey = !empty($_ENV['OPENAI_API_KEY']) ? $_ENV['OPENAI_API_KEY'] : (!empty(getenv('OPENAI_API_KEY')) ? getenv('OPENAI_API_KEY') : null);
      $this->model = ($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL')) ?: 'gpt-4o-mini';
      $this->timeoutMs = (int)(($_ENV['OPENAI_TIMEOUT_MS'] ?? getenv('OPENAI_TIMEOUT_MS')) ?: 20000);
      $this->maxTokens = (int)(($_ENV['OPENAI_MAX_TOKENS'] ?? getenv('OPENAI_MAX_TOKENS')) ?: 4000);
      $this->temperature = (float)(($_ENV['OPENAI_TEMPERATURE'] ?? getenv('OPENAI_TEMPERATURE')) ?: 0.3);
      $this->jsonMode = (($_ENV['OPENAI_JSON_MODE'] ?? getenv('OPENAI_JSON_MODE')) === 'true');
      $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);      // Configurar URLs y parámetros específicos del proveedor
      $this->configureProvider();

      if (!$this->apiKey) {
        $this->available = false;
      }
    } catch (\Exception $e) {
      $this->available = false;
      error_log("[OpenAIProvider] Error inicializando: " . $e->getMessage());
    }
  }

  /**
   * Configura URLs y parámetros específicos del proveedor
   */
  private function configureProvider(): void
  {
    switch ($this->provider) {
      case 'azure':
        $baseUrl = $_ENV['AZURE_OPENAI_BASE_URL'] ?? getenv('AZURE_OPENAI_BASE_URL');
        $this->deployment = $_ENV['AZURE_OPENAI_DEPLOYMENT'] ?? getenv('AZURE_OPENAI_DEPLOYMENT');
        $apiVersion = $_ENV['AZURE_OPENAI_API_VERSION'] ?? getenv('AZURE_OPENAI_API_VERSION') ?: '2023-05-15';

        if (!$baseUrl || !$this->deployment) {
          throw new \Exception('Azure OpenAI requiere AZURE_OPENAI_BASE_URL y AZURE_OPENAI_DEPLOYMENT');
        }

        $this->apiUrl = rtrim($baseUrl, '/') . "/openai/deployments/{$this->deployment}/chat/completions?api-version={$apiVersion}";
        break;

      case 'local':
        $baseUrl = $_ENV['OPENAI_BASE_URL'] ?? getenv('OPENAI_BASE_URL');
        if (!$baseUrl) {
          throw new \Exception('Proveedor local requiere OPENAI_BASE_URL');
        }
        $this->apiUrl = rtrim($baseUrl, '/') . '/api/generate';
        break;

      case 'openai':
      default:
        $baseUrl = $_ENV['OPENAI_BASE_URL'] ?? getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1';
        $this->apiUrl = rtrim($baseUrl, '/') . '/chat/completions';
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function chatCompletion(string $prompt, array $options = []): ?string
  {
    if (!$this->isAvailable()) {
      return null;
    }

    $model = $options['model'] ?? $this->model;
    $temperature = $options['temperature'] ?? $this->temperature;
    $maxTokens = $options['max_tokens'] ?? $this->maxTokens;
    $jsonMode = $options['json_mode'] ?? $this->jsonMode;

    for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
      try {
        $payload = $this->buildPayload($prompt, $model, $temperature, $maxTokens, $jsonMode);
        $response = $this->makeRequest($payload);

        if ($response && isset($response['choices'][0]['message']['content'])) {
          return $response['choices'][0]['message']['content'];
        }

        return null;
      } catch (\Exception $e) {
        error_log("[OpenAIProvider] Intento {$attempt} falló: " . $e->getMessage());

        if ($attempt === $this->maxRetries) {
          $this->available = false;
          return null;
        }

        // Esperar antes del siguiente intento
        sleep(pow(2, $attempt - 1));
      }
    }

    return null;
  }

  /**
   * Construye el payload para la solicitud
   */
  private function buildPayload(string $prompt, string $model, float $temperature, int $maxTokens, bool $jsonMode): array
  {
    $payload = [
      'model' => $model,
      'messages' => [
        ['role' => 'user', 'content' => $prompt]
      ],
      'temperature' => $temperature,
      'max_tokens' => $maxTokens
    ];

    if ($jsonMode) {
      $payload['response_format'] = ['type' => 'json_object'];
    }

    return $payload;
  }

  /**
   * Realiza la solicitud HTTP
   */
  private function makeRequest(array $payload): ?array
  {
    $ch = curl_init();

    curl_setopt_array($ch, [
      CURLOPT_URL => $this->apiUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $this->apiKey,
        'Content-Type: application/json',
        'User-Agent: BubbleOfTalents/1.0 OpenAIProvider'
      ],
      CURLOPT_TIMEOUT_MS => $this->timeoutMs,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
      throw new \Exception("HTTP {$httpCode}: {$response}");
    }

    return json_decode($response, true);
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool
  {
    return $this->available;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderName(): string
  {
    return 'OpenAI (' . ucfirst($this->provider) . ')';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModel(): string
  {
    return $this->model;
  }
}
