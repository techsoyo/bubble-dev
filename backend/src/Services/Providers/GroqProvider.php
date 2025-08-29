<?php

declare(strict_types=1);

namespace Services\Providers;

use Services\Interfaces\AIProviderInterface;
use Services\Exceptions\AiUnavailableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Groq AI Provider Adapter
 *
 * Adaptador para integrar GroqApiService con la interfaz unificada de IA
 *
 * @package Backend\Services\Providers
 * @version 1.0.0
 * @since 2025-08-10
 */
class GroqProvider implements AIProviderInterface
{
  private Client $httpClient;
  private ?string $apiKey;
  private string $baseUrl;
  private string $model;
  private int $timeoutSeconds;
  private int $maxRetries;
  private bool $available = true;
  public function __construct()
  {
    try {
      // Configuración desde variables de entorno
      $this->apiKey = !empty($_ENV['GROQ_API_KEY']) ? $_ENV['GROQ_API_KEY'] : (!empty(getenv('GROQ_API_KEY')) ? getenv('GROQ_API_KEY') : null);
      $this->baseUrl = $_ENV['GROQ_BASE_URL'] ?? getenv('GROQ_BASE_URL') ?: 'https://api.groq.com';
      $this->model = $_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?: 'llama3-8b-8192';
      $this->timeoutSeconds = (int)(($_ENV['GROQ_TIMEOUT_SECONDS'] ?? getenv('GROQ_TIMEOUT_SECONDS')) ?: 120);
      $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);

      if (empty($this->apiKey)) {
        $this->available = false;
        return;
      }

      // Inicializar cliente HTTP
      $this->httpClient = new Client([
        'base_uri' => $this->baseUrl,
        'timeout' => $this->timeoutSeconds,
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
          'User-Agent' => 'BubbleOfTalents/1.0 GroqProvider'
        ],
        'verify' => false,
        'curl' => [
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_SSL_VERIFYHOST => false
        ]
      ]);
    } catch (\Exception $e) {
      $this->available = false;
      error_log("[GroqProvider] Error inicializando: " . $e->getMessage());
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
    $temperature = $options['temperature'] ?? 0.7;
    $maxTokens = $options['max_tokens'] ?? 2000;

    $payload = [
      'model' => $model,
      'messages' => [
        ['role' => 'user', 'content' => $prompt]
      ],
      'temperature' => $temperature,
      'max_tokens' => $maxTokens
    ];

    for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
      try {
        $response = $this->httpClient->post('/openai/v1/chat/completions', [
          'json' => $payload
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['choices'][0]['message']['content'])) {
          return $data['choices'][0]['message']['content'];
        }

        return null;
      } catch (GuzzleException $e) {
        error_log("[GroqProvider] Intento {$attempt} falló: " . $e->getMessage());

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
    return 'Groq';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModel(): string
  {
    return $this->model;
  }
}
