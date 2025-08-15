<?php

namespace Services;

use Domain\CvSchema;
use Services\Exceptions\AiUnavailableException;

/**
 * Servicio de IA Ollama Local para Análisis de CV
 *
 * Servicio completamente local usando Ollama sin dependencias externas
 * - Sin límites de API
 * - Completamente gratuito
 * - Privacidad total
 * - Modelo recruitment-ai especializado
 *
 * @package Backend\Services
 * @version 4.0.0
 * @since 2025-08-15
 */
class OllamaService
{
  private $apiUrl;
  private $model;
  private $timeoutMs;
  private $maxRetries;

  public function __construct()
  {
    // Cargar configuración desde .env
    $this->apiUrl = ($_ENV['OLLAMA_API_URL'] ?? getenv('OLLAMA_API_URL')) ?: 'http://localhost:11434/api';
    $this->model = ($_ENV['OLLAMA_MODEL'] ?? getenv('OLLAMA_MODEL')) ?: 'recruitment-ai';
    $this->timeoutMs = (int)(($_ENV['OLLAMA_TIMEOUT_MS'] ?? getenv('OLLAMA_TIMEOUT_MS')) ?: 180000); // 3 minutos
    $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);

    // Configurar URL completa
    $this->apiUrl = rtrim($this->apiUrl, '/') . '/generate';
  }

  /**
   * Analiza texto de CV y devuelve JSON estructurado
   *
   * @param string $rawText Texto extraído del CV
   * @return array Datos estructurados del CV
   * @throws AiUnavailableException Si Ollama no está disponible o devuelve JSON inválido
   */
  public function analyzeCvFromText(string $rawText): array
  {
    if (empty($rawText)) {
      throw new AiUnavailableException('EMPTY_TEXT');
    }

    $startTime = microtime(true);

    // Construir prompt optimizado para análisis de CV
    $prompt = $this->buildCvAnalysisPrompt($rawText);

    // Hacer llamada a Ollama con reintentos
    $response = $this->callOllamaWithRetries($prompt);

    if (!$response) {
      throw new AiUnavailableException('AI_UNAVAILABLE');
    }

    // Parsear respuesta JSON
    $cvData = $this->parseJsonResponse($response);

    if (!$cvData) {
      throw new AiUnavailableException('INVALID_JSON_RESPONSE');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[OllamaService] CV análisis completado en {$duration}ms");

    return $cvData;
  }

  /**
   * Método para compatibilidad con llamadas existentes
   */
  public function analyzeCV($rawText): array
  {
    return $this->analyzeCvFromText($rawText);
  }

  /**
   * Construye el prompt optimizado para análisis de CV
   */
  private function buildCvAnalysisPrompt(string $rawText): string
  {
    return "Analiza este CV y extrae la información en formato JSON exacto con la siguiente estructura:

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
      \"location\": \"\",
      \"description\": \"\",
      \"technologies\": []
    }
  ],
  \"education\": [
    {
      \"institution\": \"\",
      \"degree\": \"\",
      \"field\": \"\",
      \"year\": \"\",
      \"gpa\": \"\"
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
  \"projects\": [
    {
      \"name\": \"\",
      \"description\": \"\",
      \"technologies\": [],
      \"url\": \"\"
    }
  ],
  \"languages\": [
    {
      \"language\": \"\",
      \"level\": \"\"
    }
  ]
}

INSTRUCCIONES:
1. Responde SOLO con el JSON, sin texto adicional
2. Si no encuentras información para un campo, usa cadena vacía \"\" o array vacío []
3. Extrae toda la información relevante del CV
4. Normaliza los datos (nombres propios en mayúsculas, fechas consistentes)

CV A ANALIZAR:
" . trim($rawText) . "

JSON:";
  }

  /**
   * Llama a Ollama con sistema de reintentos
   */
  private function callOllamaWithRetries(string $prompt): ?string
  {
    $lastError = null;

    for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
      try {
        error_log("[OllamaService] Intento {$attempt}/{$this->maxRetries}");

        $response = $this->callOllama($prompt);

        if ($response) {
          return $response;
        }
      } catch (\Exception $e) {
        $lastError = $e->getMessage();
        error_log("[OllamaService] Error en intento {$attempt}: {$lastError}");

        if ($attempt < $this->maxRetries) {
          // Esperar antes del siguiente intento
          sleep($attempt);
        }
      }
    }

    throw new AiUnavailableException("AI_ERROR: {$lastError}");
  }

  /**
   * Realiza la llamada HTTP a Ollama
   */
  private function callOllama(string $prompt): ?string
  {
    $data = [
      'model' => $this->model,
      'prompt' => $prompt,
      'stream' => false,
      'format' => 'json'
    ];

    $ch = curl_init($this->apiUrl);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode($data),
      CURLOPT_TIMEOUT => intval($this->timeoutMs / 1000),
      CURLOPT_CONNECTTIMEOUT => 10
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
      throw new \Exception("HTTP {$httpCode}: {$response}");
    }

    $result = json_decode($response, true);
    if (!$result || !isset($result['response'])) {
      throw new \Exception("Invalid Ollama response");
    }

    return trim($result['response']);
  }

  /**
   * Parsea la respuesta JSON de Ollama
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

    // Si no hay JSON válido, intentar toda la respuesta
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
      return $data;
    }

    return null;
  }

  /**
   * Verifica si Ollama está disponible
   */
  public function isAvailable(): bool
  {
    try {
      $testUrl = str_replace('/generate', '/tags', $this->apiUrl);
      $ch = curl_init($testUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_NOBODY => true
      ]);

      curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      return $httpCode === 200;
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Obtiene información del servicio
   */
  public function getServiceInfo(): array
  {
    return [
      'provider' => 'ollama',
      'model' => $this->model,
      'api_url' => $this->apiUrl,
      'timeout_ms' => $this->timeoutMs,
      'max_retries' => $this->maxRetries,
      'available' => $this->isAvailable()
    ];
  }

  /**
   * Genera descripción de trabajo usando IA
   */
  public function generateJobDescription(array $params): string
  {
    $prompt = "Genera una descripción de trabajo profesional basada en los siguientes parámetros:\n";

    foreach ($params as $key => $value) {
      $prompt .= "- $key: $value\n";
    }

    $prompt .= "\nGenera una descripción completa, profesional y atractiva:";

    $response = $this->callOllamaWithRetries($prompt);
    return $response ?? 'Error generando descripción';
  }

  /**
   * Calcula matching entre candidato y posición
   */
  public function calculateMatching(int $candidateId, int $jobId): array
  {
    // Para implementación futura
    return [
      'percentage' => 0,
      'strengths' => [],
      'weaknesses' => [],
      'recommendations' => []
    ];
  }

  /**
   * Obtiene modelos disponibles en Ollama
   */
  public function getAvailableModels(): array
  {
    try {
      $tagsUrl = str_replace('/generate', '/tags', $this->apiUrl);
      $ch = curl_init($tagsUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['models'] ?? [];
      }

      return [];
    } catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Obtiene información del modelo actual
   */
  public function getModelInfo(): array
  {
    return [
      'model' => $this->model,
      'api_url' => $this->apiUrl,
      'available' => $this->isAvailable()
    ];
  }
}
