<?php

declare(strict_types=1);

namespace Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Domain\CvSchema;
use Services\Exceptions\AiUnavailableException;
use Services\PdfTextService;

/**
 * Servicio de IA usando Qwen API (Alibaba Cloud)
 * 
 * Servicio de producción que utiliza la API de Qwen (ÃƒÂ§Ã¢â€žÂ¢Ã‚Â¾ÃƒÂ§Ã¢â‚¬Å¡Ã‚Â¼) de Alibaba Cloud
 * como alternativa a Ollama local. Incluye 1M tokens gratuitos y precios muy bajos.
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-20
 */
class QwenApiService
{
  private Client $httpClient;
  private string $apiKey;
  private string $baseUrl;
  private string $model;
  private int $timeoutSeconds;
  private int $maxRetries;

  public function __construct()
  {
    // Configuración desde variables de entorno
    $this->apiKey = $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY');
    $this->baseUrl = $_ENV['QWEN_BASE_URL'] ?? getenv('QWEN_BASE_URL') ?: 'https://dashscope.aliyuncs.com/compatible-mode/v1';
    $this->model = $_ENV['QWEN_MODEL'] ?? getenv('QWEN_MODEL') ?: 'qwen-plus'; // qwen-plus es parte de qwen3
    $this->timeoutSeconds = (int)(($_ENV['QWEN_TIMEOUT_SECONDS'] ?? getenv('QWEN_TIMEOUT_SECONDS')) ?: 180);
    $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);

    if (empty($this->apiKey)) {
      throw new \InvalidArgumentException('QWEN_API_KEY requerido. Obtenerla en: https://help.aliyun.com/zh/model-studio/developer-reference/get-api-key');
    }

    // Inicializar cliente HTTP con configuración optimizada
    $this->httpClient = new Client([
      'base_uri' => $this->baseUrl,
      'timeout' => $this->timeoutSeconds,
      'headers' => [
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json',
        'User-Agent' => 'BubbleOfTalents/1.0 QwenApiService'
      ]
    ]);
  }

  /**
   * Analiza PDF de CV usando Qwen API con extracción de texto
   *
   * @param string $pdfPath Ruta al archivo PDF del CV
   * @return array Datos estructurados del CV según CvFormData del frontend
   * @throws AiUnavailableException Si la API no estí¡ disponible o devuelve JSON inví¡lido
   */
  public function analyzeCvFromPdf(string $pdfPath): array
  {
    if (!file_exists($pdfPath)) {
      throw new AiUnavailableException('PDF_NOT_FOUND');
    }

    $startTime = microtime(true);

    // ESTRATEGIA: Usar smalot/pdfparser para extraer texto del PDF
    try {
      $parser = new \Smalot\PdfParser\Parser();
      $pdf = $parser->parseFile($pdfPath);
      $extractedText = $pdf->getText();

      if (empty($extractedText)) {
        throw new AiUnavailableException('PDF_TEXT_EXTRACTION_FAILED');
      }

      error_log("[QwenApiService] Texto extraí­do del PDF: " . strlen($extractedText) . " caracteres");

      // Analizar el texto extraí­do
      $cvData = $this->analyzeCvFromText($extractedText);
    } catch (\Exception $e) {
      error_log("[QwenApiService] Error extrayendo texto del PDF: " . $e->getMessage());
      throw new AiUnavailableException('PDF_TEXT_EXTRACTION_FAILED');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[QwenApiService] CV PDF Anáslisis completado en {$duration}ms");

    return $cvData;
  }

  /**
   * Analiza texto de CV usando Qwen API
   *
   * @param string $rawText Texto del CV a analizar
   * @return array Datos estructurados del CV
   * @throws AiUnavailableException Si la API falla o devuelve respuesta inví¡lida
   */
  public function analyzeCvFromText(string $rawText): array
  {
    if (empty($rawText)) {
      throw new AiUnavailableException('EMPTY_TEXT');
    }

    $startTime = microtime(true);

    $prompt = $this->buildCvAnalysisPrompt($rawText);
    $response = $this->callQwenApiWithRetries($prompt);

    if (!$response) {
      throw new AiUnavailableException('AI_UNAVAILABLE');
    }

    $cvData = $this->parseJsonResponse($response);

    if (!$cvData) {
      throw new AiUnavailableException('INVALID_JSON_RESPONSE');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[QwenApiService] CV Anáslisis de texto completado en {$duration}ms");

    return $cvData;
  }

  /**
   * Método de compatibilidad con OllamaService
   */
  public function analyzeCV($rawText): array
  {
    return $this->analyzeCvFromText($rawText);
  }

  /**
   * Construye prompt optimizado para Anáslisis de CV con Qwen
   */
  private function buildCvAnalysisPrompt(string $cvText): string
  {
    $schema = CvSchema::PROMPT_MINIMAL; // Usar la constante disponible
    $schemaJson = json_encode($schema, JSON_PRETTY_PRINT);

    return "Eres un experto analizador de currí­culos vitae. Analiza el siguiente CV y extrae la información en formato JSON estrictamente ví¡lido.

**TEXTO DEL CV:**
{$cvText}

**INSTRUCCIONES:**
1. Extrae SOLO la información que estí¡ presente en el CV
2. Si un campo no tiene información, usa null o array vací­o según corresponda
3. Responde ÚNICAMENTE con JSON ví¡lido, sin texto adicional ni explicaciones
4. Usa el esquema exacto que se proporciona abajo

**ESQUEMA JSON REQUERIDO:**
{$schemaJson}

**REGLAS IMPORTANTES:**
- nombre: Solo el nombre completo de la persona
- email: Solo direcciones de email ví¡lidas
- telefono: Solo números de teléfono
- resumen_profesional: Un resumen conciso de 2-3 oraciones
- hard_skills: Array de habilidades técnicas especí­ficas
- soft_skills: Array de habilidades interpersonales
- experiencia_laboral: Array con trabajos previos (empresa, puesto, periodo)
- educacion: Array con estudios (institucion, titulo, aí±o)
- idiomas: Array con idiomas y niveles
- certificaciones: Array con certificaciones relevantes

Responde SOLO con el JSON:";
  }

  /**
   * Realiza llamada a Qwen API con sistema de reintentos
   */
  private function callQwenApiWithRetries(string $prompt): ?string
  {
    $attempt = 0;
    $lastException = null;

    while ($attempt < $this->maxRetries) {
      $attempt++;

      try {
        error_log("[QwenApiService] Intento {$attempt}/{$this->maxRetries} - Llamando a Qwen API");

        $payload = [
          'model' => $this->model,
          'messages' => [
            [
              'role' => 'user',
              'content' => $prompt
            ]
          ],
          'temperature' => 0.1, // Baja temperatura para consistencia
          'max_tokens' => 2048,
          'stream' => false
        ];

        $response = $this->httpClient->post('/chat/completions', [
          'json' => $payload
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        error_log("[QwenApiService] Respuesta HTTP {$statusCode}, body length: " . strlen($body));

        if ($statusCode === 200) {
          $data = json_decode($body, true);

          if ($data && isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            error_log("[QwenApiService] Éxito en intento {$attempt}");
            return $content;
          } else {
            error_log("[QwenApiService] Respuesta sin contenido ví¡lido: " . substr($body, 0, 500));
          }
        } else {
          error_log("[QwenApiService] Error HTTP {$statusCode}: " . substr($body, 0, 500));
        }
      } catch (GuzzleException $e) {
        $lastException = $e;
        error_log("[QwenApiService] Exception en intento {$attempt}: " . $e->getMessage());

        if ($attempt < $this->maxRetries) {
          $delay = pow(2, $attempt) * 1000; // Backoff exponencial
          error_log("[QwenApiService] Esperando {$delay}ms antes del siguiente intento");
          usleep($delay * 1000);
        }
      }
    }

    error_log("[QwenApiService] Todos los intentos fallaron");
    if ($lastException) {
      error_log("[QwenApiService] Última excepción: " . $lastException->getMessage());
    }

    return null;
  }

  /**
   * Parsea y valida respuesta JSON de Qwen
   */
  private function parseJsonResponse(string $response): ?array
  {
    if (empty($response)) {
      error_log("[QwenApiService] Respuesta vací­a");
      return null;
    }

    // Limpiar respuesta si contiene markdown o texto adicional
    $cleanedResponse = $this->cleanJsonResponse($response);

    $cvData = json_decode($cleanedResponse, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("[QwenApiService] Error JSON: " . json_last_error_msg());
      error_log("[QwenApiService] Respuesta problemí¡tica: " . substr($response, 0, 1000));
      return null;
    }

    // Validar estructura bí¡sica
    if (!is_array($cvData) || empty($cvData)) {
      error_log("[QwenApiService] JSON ví¡lido pero estructura inví¡lida");
      return null;
    }

    error_log("[QwenApiService] JSON parseado exitosamente - campos: " . count($cvData));
    return $cvData;
  }

  /**
   * Limpia respuesta JSON de markdown y texto adicional
   */
  private function cleanJsonResponse(string $response): string
  {
    // Remover bloques de código markdown
    $response = preg_replace('/```json\s*/', '', $response);
    $response = preg_replace('/```\s*$/', '', $response);
    $response = preg_replace('/^```/', '', $response);

    // Buscar JSON ví¡lido en la respuesta
    $start = strpos($response, '{');
    $end = strrpos($response, '}');

    if ($start !== false && $end !== false && $end > $start) {
      $response = substr($response, $start, $end - $start + 1);
    }

    return trim($response);
  }

  /**
   * Verifica si la API estí¡ disponible
   */
  public function isAvailable(): bool
  {
    try {
      $response = $this->httpClient->get('/models', [
        'timeout' => 10
      ]);

      return $response->getStatusCode() === 200;
    } catch (\Exception $e) {
      error_log("[QwenApiService] Check disponibilidad falló: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Obtiene información de uso/estadí­sticas (si estí¡ disponible en la API)
   */
  public function getUsageInfo(): array
  {
    return [
      'service' => 'Qwen API (Alibaba Cloud)',
      'model' => $this->model,
      'base_url' => $this->baseUrl,
      'timeout' => $this->timeoutSeconds . 's',
      'max_retries' => $this->maxRetries
    ];
  }
}
