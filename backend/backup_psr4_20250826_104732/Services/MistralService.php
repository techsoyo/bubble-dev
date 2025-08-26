<?php declare(strict_types=1);

namespace Services\MistralService.php\Services;

use Services\Exceptions\AiUnavailableException;

/**
 * Servicio de IA Mistral via Hugging Face Inference API
 *
 * Servicio gratuito usando Mistral 7B a travÃ©s de Hugging Face
 * - Completamente gratuito (1000 requests/mes)
 * - Sin instalaciÃ³n local requerida
 * - Modelo Mistral 7B optimizado
 * - API simple y directa
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-15
 */
class MistralService
{
  private $apiUrl;
  private $token;
  private $model;
  private $timeoutMs;
  private $maxRetries;

  public function __construct()
  {
    // Cargar configuraciÃ³n desde .env
    $this->token = ($_ENV['HUGGINGFACE_TOKEN'] ?? getenv('HUGGINGFACE_TOKEN'));
    $this->model = ($_ENV['MISTRAL_MODEL'] ?? getenv('MISTRAL_MODEL')) ?: 'mistralai/Mistral-7B-Instruct-v0.2';
    $this->timeoutMs = (int)(($_ENV['MISTRAL_TIMEOUT_MS'] ?? getenv('MISTRAL_TIMEOUT_MS')) ?: 120000); // 2 minutos
    $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);

    // Construir URL de la API
    $this->apiUrl = "https://api-inference.huggingface.co/models/{$this->model}";

    if (!$this->token) {
      throw new \Exception('Token de Hugging Face no configurado');
    }
  }

  /**
   * Analiza texto de CV y devuelve JSON estructurado
   *
   * @param string $rawText Texto extraÃ­do del CV
   * @return array Datos estructurados del CV
   * @throws AiUnavailableException Si Hugging Face no estÃ¡ disponible o devuelve JSON invÃ¡lido
   */
  public function analyzeCvFromText(string $rawText): array
  {
    if (empty($rawText)) {
      throw new AiUnavailableException('EMPTY_TEXT');
    }

    $startTime = microtime(true);

    // Construir prompt optimizado para anÃ¡lisis de CV
    $prompt = $this->buildCvAnalysisPrompt($rawText);

    // Hacer llamada a Hugging Face con reintentos
    $response = $this->callHuggingFaceWithRetries($prompt);

    if (!$response) {
      throw new AiUnavailableException('AI_UNAVAILABLE');
    }

    // Parsear respuesta JSON
    $cvData = $this->parseJsonResponse($response);

    if (!$cvData) {
      throw new AiUnavailableException('INVALID_JSON_RESPONSE');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[MistralService] CV anÃ¡lisis completado en {$duration}ms");

    return $cvData;
  }

  /**
   * MÃ©todo para compatibilidad con llamadas existentes
   */
  public function analyzeCV($rawText): array
  {
    return $this->analyzeCvFromText($rawText);
  }

  /**
   * Construye el prompt optimizado para anÃ¡lisis de CV
   */
  private function buildCvAnalysisPrompt(string $rawText): string
  {
    return "[INST] Analiza este CV y extrae la informaciÃ³n en formato JSON exacto. Responde SOLO con JSON vÃ¡lido, sin explicaciones adicionales.

Estructura requerida:
{
  \"personal_info\": {
    \"full_name\": \"\",
    \"email\": \"\",
    \"phone\": \"\",
    \"location\": \"\",
    \"linkedin\": \"\",
    \"github\": \"\"
  },
  \"professional_summary\": \"\",
  \"work_experience\": [
    {
      \"company\": \"\",
      \"position\": \"\",
      \"duration\": \"\",
      \"description\": \"\",
      \"technologies\": []
    }
  ],
  \"education\": [
    {
      \"institution\": \"\",
      \"degree\": \"\",
      \"field\": \"\",
      \"year\": \"\"
    }
  ],
  \"skills\": {
    \"technical\": [],
    \"languages\": [],
    \"frameworks\": [],
    \"tools\": [],
    \"soft_skills\": []
  },
  \"certifications\": [],
  \"languages\": [
    {
      \"language\": \"\",
      \"level\": \"\"
    }
  ]
}

CV a analizar:
" . trim($rawText) . "

JSON: [/INST]";
  }

  /**
   * Llama a Hugging Face con sistema de reintentos
   */
  private function callHuggingFaceWithRetries(string $prompt): ?string
  {
    $lastError = null;

    for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
      try {
        error_log("[MistralService] Intento {$attempt}/{$this->maxRetries}");

        $response = $this->callHuggingFace($prompt);

        if ($response) {
          return $response;
        }
      } catch (\Exception $e) {
        $lastError = $e->getMessage();
        error_log("[MistralService] Error en intento {$attempt}: {$lastError}");

        if ($attempt < $this->maxRetries) {
          // Esperar mÃ¡s tiempo si el modelo se estÃ¡ cargando
          $waitTime = str_contains($lastError, 'loading') ? 20 : $attempt * 2;
          error_log("[MistralService] Esperando {$waitTime}s antes del siguiente intento...");
          sleep($waitTime);
        }
      }
    }

    throw new AiUnavailableException("AI_ERROR: {$lastError}");
  }

  /**
   * Realiza la llamada HTTP a Hugging Face
   */
  private function callHuggingFace(string $prompt): ?string
  {
    $data = [
      'inputs' => $prompt,
      'parameters' => [
        'max_new_tokens' => 2000,
        'temperature' => 0.1,
        'return_full_text' => false
      ]
    ];

    $ch = curl_init($this->apiUrl);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $this->token,
        'Content-Type: application/json'
      ],
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_TIMEOUT => intval($this->timeoutMs / 1000),
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
      $error = curl_error($ch);
      curl_close($ch);
      throw new \Exception("cURL error: {$error}");
    }

    curl_close($ch);

    if ($httpCode !== 200) {
      $errorInfo = json_decode($response, true);
      $errorMsg = $errorInfo['error'] ?? $response;

      // Si el modelo se estÃ¡ cargando, es un error temporal
      if (str_contains($errorMsg, 'loading')) {
        throw new \Exception("Model is loading, please wait");
      }

      throw new \Exception("HTTP {$httpCode}: {$errorMsg}");
    }

    $result = json_decode($response, true);

    if (!$result || !is_array($result) || empty($result)) {
      throw new \Exception("Invalid Hugging Face response format");
    }

    // Hugging Face devuelve un array con el texto generado
    if (isset($result[0]['generated_text'])) {
      return trim($result[0]['generated_text']);
    }

    throw new \Exception("No generated text in response");
  }

  /**
   * Parsea la respuesta JSON del modelo
   */
  private function parseJsonResponse(string $response): ?array
  {
    // Limpiar la respuesta
    $response = trim($response);

    // Buscar el JSON en la respuesta
    $jsonStart = strpos($response, '{');
    $jsonEnd = strrpos($response, '}');

    if ($jsonStart !== false && $jsonEnd !== false) {
      $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
      $data = json_decode($jsonString, true);

      if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        return $data;
      }
    }

    // Si no hay JSON vÃ¡lido, intentar toda la respuesta
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
      return $data;
    }

    return null;
  }

  /**
   * Verifica si Hugging Face estÃ¡ disponible
   */
  public function isAvailable(): bool
  {
    try {
      $ch = curl_init($this->apiUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
          'Authorization: Bearer ' . $this->token,
          'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
          'inputs' => 'test',
          'parameters' => ['max_new_tokens' => 1]
        ]),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
      ]);

      curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      return in_array($httpCode, [200, 503]); // 503 = modelo cargÃ¡ndose, pero disponible
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Obtiene informaciÃ³n del servicio
   */
  public function getServiceInfo(): array
  {
    return [
      'provider' => 'huggingface',
      'model' => $this->model,
      'api_url' => $this->apiUrl,
      'timeout_ms' => $this->timeoutMs,
      'max_retries' => $this->maxRetries,
      'available' => $this->isAvailable()
    ];
  }

  /**
   * Genera descripciÃ³n de trabajo usando IA
   */
  public function generateJobDescription(array $params): string
  {
    $prompt = "[INST] Genera una descripciÃ³n de trabajo profesional basada en:\n";

    foreach ($params as $key => $value) {
      $prompt .= "- $key: $value\n";
    }

    $prompt .= "\nGenera una descripciÃ³n completa y atractiva: [/INST]";

    try {
      $response = $this->callHuggingFaceWithRetries($prompt);
      return $response ?? 'Error generando descripciÃ³n';
    } catch (\Exception $e) {
      return 'Error generando descripciÃ³n: ' . $e->getMessage();
    }
  }
}
