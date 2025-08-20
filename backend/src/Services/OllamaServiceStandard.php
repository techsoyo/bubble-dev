<?php

namespace Services;

use Hanwoolderink\Ollama\Ollama;
use GuzzleHttp\Client;
use Domain\CvSchema;
use Services\Exceptions\AiUnavailableException;
use Services\PdfTextService;

/**
 * Servicio de IA Ollama con Cliente Estándar
 * 
 * Migración del OllamaService original usando hanwoolderink/ollama-php-client
 * para reemplazar las implementaciones cURL por una librería estándar
 *
 * @package Backend\Services
 * @version 5.0.0
 * @since 2025-08-20
 */
class OllamaServiceStandard
{
  private Ollama $client;
  private string $model;
  private int $timeoutMs;
  private int $maxRetries;
  private string $baseUrl;

  public function __construct(Ollama $client = null)
  {
    // Configuración desde variables de entorno
    $this->baseUrl = ($_ENV['OLLAMA_API_URL'] ?? getenv('OLLAMA_API_URL')) ?: 'http://localhost:11434';
    $this->model = ($_ENV['OLLAMA_MODEL'] ?? getenv('OLLAMA_MODEL')) ?: 'llama3.2:latest';
    $this->timeoutMs = (int)(($_ENV['OLLAMA_TIMEOUT_MS'] ?? getenv('OLLAMA_TIMEOUT_MS')) ?: 180000);
    $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);

    // Inicializar cliente Ollama con Guzzle configurado
    if ($client === null) {
      $guzzleClient = new Client([
        'base_uri' => $this->baseUrl,
        'timeout' => intval($this->timeoutMs / 1000)
      ]);
      $this->client = new Ollama($guzzleClient);
    } else {
      $this->client = $client;
    }
  }

  /**
   * Analiza PDF de CV directamente con Llama3.2-Vision usando cliente estándar
   *
   * @param string $pdfPath Ruta al archivo PDF del CV
   * @return array Datos estructurados del CV según CvFormData del frontend
   * @throws AiUnavailableException Si Ollama no está disponible o devuelve JSON inválido
   */
  public function analyzeCvFromPdf(string $pdfPath): array
  {
    if (!file_exists($pdfPath)) {
      throw new AiUnavailableException('PDF_NOT_FOUND');
    }

    $startTime = microtime(true);

    // Validar PDF y obtener path
    $pdfTextService = new PdfTextService();
    $validatedPdfPath = $pdfTextService->validateAndReturnPath($pdfPath);

    // Convertir PDF a base64
    $pdfBase64 = base64_encode(file_get_contents($validatedPdfPath));

    if (!$pdfBase64) {
      throw new AiUnavailableException('PDF_ENCODING_FAILED');
    }

    // Procesar PDF directamente con cliente estándar
    $cvData = $this->analyzePdfDirectlyWithClient($pdfBase64);

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[OllamaServiceStandard] CV PDF análisis completado en {$duration}ms");

    return $cvData;
  }

  /**
   * LEGACY: Analiza texto de CV usando cliente estándar (método mantenido para compatibilidad)
   */
  public function analyzeCvFromText(string $rawText): array
  {
    if (empty($rawText)) {
      throw new AiUnavailableException('EMPTY_TEXT');
    }

    $startTime = microtime(true);

    $prompt = $this->buildLegacyTextAnalysisPrompt($rawText);
    $response = $this->callOllamaWithRetries($prompt);

    if (!$response) {
      throw new AiUnavailableException('AI_UNAVAILABLE');
    }

    $cvData = $this->parseJsonResponse($response);

    if (!$cvData) {
      throw new AiUnavailableException('INVALID_JSON_RESPONSE');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[OllamaServiceStandard] CV análisis de texto completado en {$duration}ms");

    return $cvData;
  }

  /**
   * Método de compatibilidad
   */
  public function analyzeCV($rawText): array
  {
    return $this->analyzeCvFromText($rawText);
  }

  /**
   * Analiza PDF directamente usando el cliente estándar con soporte para imágenes
   */
  private function analyzePdfDirectlyWithClient(string $pdfBase64): array
  {
    if (empty($pdfBase64)) {
      throw new AiUnavailableException('EMPTY_PDF_DATA');
    }

    $startTime = microtime(true);
    $prompt = $this->buildDirectPdfAnalysisPrompt($pdfBase64);

    // Realizar llamada con reintentos usando cliente estándar (SIN parámetro images)
    $response = $this->callOllamaWithRetries($prompt);

    if (!$response) {
      throw new AiUnavailableException('AI_UNAVAILABLE');
    }

    $cvData = $this->parseJsonResponse($response);

    if (!$cvData) {
      throw new AiUnavailableException('INVALID_JSON_RESPONSE');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[OllamaServiceStandard] CV PDF análisis directo completado en {$duration}ms");

    return $cvData;
  }

  /**
   * Realiza llamadas a Ollama con reintentos usando el cliente estándar
   */
  private function callOllamaWithRetries(string $prompt): ?string
  {
    $lastError = null;

    for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
      try {
        error_log("[OllamaServiceStandard] Intento {$attempt}/{$this->maxRetries}");

        $response = $this->callOllamaClient($prompt);

        if ($response) {
          return $response;
        }
      } catch (\Exception $e) {
        $lastError = $e->getMessage();
        error_log("[OllamaServiceStandard] Error en intento {$attempt}: {$lastError}");

        if ($attempt < $this->maxRetries) {
          sleep($attempt);
        }
      }
    }

    throw new AiUnavailableException("AI_ERROR: {$lastError}");
  }

  /**
   * Realiza la llamada a Ollama usando el cliente estándar
   */
  private function callOllamaClient(string $prompt): ?string
  {
    try {
      // Usar completion()->create() EXACTAMENTE como el servicio original (sin images)
      $response = $this->client->completion()->create(
        model: $this->model,
        prompt: $prompt,
        format: 'json'
      );

      // La respuesta está en la propiedad 'content'
      if (!empty($response->content)) {
        return trim($response->content);
      }

      throw new \Exception("Empty response content from Ollama client");
    } catch (\Exception $e) {
      error_log("[OllamaServiceStandard] Client error: " . $e->getMessage());
      throw new \Exception("Ollama client error: " . $e->getMessage());
    }
  }

  /**
   * Construye el prompt específico para análisis directo de PDF (EXACTO al original)
   */
  private function buildDirectPdfAnalysisPrompt(string $pdfBase64): string
  {
    return "Analiza este CV en formato PDF y extrae ÚNICAMENTE los datos que coincidan con los siguientes campos del formulario. Responde SOLO con JSON válido:

{
  \"nombre\": \"\",
  \"email\": \"\",
  \"telefono\": \"\",
  \"ubicacion_actual\": \"\",
  \"fecha_nacimiento\": \"\",
  \"portfolio\": \"\",
  \"linkedin\": \"\",
  \"otras_redes\": [],
  \"resumen_profesional\": \"\",
  \"soft_skills\": [],
  \"hard_skills\": [],
  \"idiomas\": [
    {\"idioma\": \"\", \"nivel\": \"\"}
  ],
  \"intereses\": [],
  \"referencias\": \"\",
  \"disponibilidad\": \"\",
  \"data_source\": \"ai_processing\",
  \"puestos_anteriores\": [
    {
      \"puesto\": \"\",
      \"empresa\": \"\",
      \"fecha_inicio\": \"\",
      \"fecha_fin\": \"\",
      \"descripcion\": \"\",
      \"responsabilidades\": [],
      \"ubicacion\": \"\",
      \"actual\": false
    }
  ],
  \"educacion\": [
    {
      \"titulo\": \"\",
      \"campo_estudio\": \"\",
      \"institucion\": \"\",
      \"fecha_inicio\": \"\",
      \"fecha_fin\": \"\",
      \"nivel_educativo\": \"\",
      \"descripcion\": \"\"
    }
  ],
  \"certificaciones\": [],
  \"certificaciones_detalle\": [
    {
      \"nombre_certificacion\": \"\",
      \"emisor\": \"\",
      \"fecha_emision\": \"\",
      \"fecha_expiracion\": \"\"
    }
  ],
  \"idiomas_detalle\": [
    {
      \"idioma\": \"\",
      \"nivel_competencia\": \"\"
    }
  ],
  \"proyectos\": [
    {
      \"nombre\": \"\",
      \"descripcion\": \"\",
      \"tecnologias\": [],
      \"fecha_inicio\": \"\",
      \"fecha_fin\": \"\",
      \"url\": \"\"
    }
  ],
  \"referencias_detalle\": [
    {
      \"nombre_referencia\": \"\",
      \"empresa_referencia\": \"\",
      \"email_referencia\": \"\",
      \"telefono_referencia\": \"\",
      \"notas\": \"\"
    }
  ],
  \"habilidades_adicionales\": [],
  \"routing\": {
    \"fuente\": \"ai\",
    \"razon\": \"Procesado automáticamente por Llama3.2\",
    \"fecha_asignacion\": \"" . date('Y-m-d H:i:s') . "\"
  }
}

INSTRUCCIONES CRÍTICAS:
1. Procesa el PDF directamente sin necesidad de extracción de texto previa
2. Extrae SOLO los campos listados arriba
3. Si no encuentras información para un campo, usa cadena vacía \"\" o array vacío []
4. Fechas en formato YYYY-MM-DD
5. Responde ÚNICAMENTE con JSON válido, sin texto adicional
6. Asegúrate de que todos los campos del formulario estén presentes

PDF DATA (base64):
" . $pdfBase64;
  }

  /**
   * LEGACY: Prompt para análisis de texto extraído
   */
  private function buildLegacyTextAnalysisPrompt(string $rawText): string
  {
    return "Analiza este texto de CV y extrae la información en formato JSON con la estructura exacta requerida por el frontend:

{
  \"nombre\": \"\",
  \"email\": \"\", 
  \"telefono\": \"\",
  \"ubicacion_actual\": \"\",
  \"resumen_profesional\": \"\",
  \"hard_skills\": [],
  \"soft_skills\": [],
  \"puestos_anteriores\": [],
  \"educacion\": [],
  \"data_source\": \"ai_processing\"
}

TEXTO DEL CV:
" . trim($rawText) . "

JSON:";
  }

  /**
   * Parsea la respuesta JSON de Ollama
   */
  private function parseJsonResponse(string $response): ?array
  {
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

    // Intentar toda la respuesta
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
      return $data;
    }

    return null;
  }

  /**
   * Verifica si Ollama está disponible usando el cliente estándar
   */
  public function isAvailable(): bool
  {
    try {
      // Usar el cliente para verificar disponibilidad con una llamada simple
      $response = $this->client->completion()->create(
        model: $this->model,
        prompt: 'test'
      );

      return !empty($response->content);
    } catch (\Exception $e) {
      error_log("[OllamaServiceStandard] Availability check failed: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Obtiene información del servicio
   */
  public function getServiceInfo(): array
  {
    return [
      'provider' => 'ollama-standard-client',
      'model' => $this->model,
      'base_url' => $this->baseUrl,
      'timeout_ms' => $this->timeoutMs,
      'max_retries' => $this->maxRetries,
      'available' => $this->isAvailable(),
      'client_version' => '1.0'
    ];
  }

  /**
   * Genera descripción de trabajo usando el cliente estándar
   */
  public function generateJobDescription(array $params): string
  {
    $prompt = "Genera una descripción de trabajo profesional basada en los siguientes parámetros:\n";

    foreach ($params as $key => $value) {
      $prompt .= "- $key: $value\n";
    }

    $prompt .= "\nGenera una descripción completa, profesional y atractiva:";

    try {
      $response = $this->callOllamaWithRetries($prompt);
      return $response ?? 'Error generando descripción';
    } catch (\Exception $e) {
      error_log("[OllamaServiceStandard] Job description generation error: " . $e->getMessage());
      return 'Error generando descripción';
    }
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
   * Obtiene modelos disponibles usando el cliente estándar
   */
  public function getAvailableModels(): array
  {
    try {
      // El cliente estándar puede no tener método directo para esto
      // Intentar usar el endpoint tags si está disponible
      return [];
    } catch (\Exception $e) {
      error_log("[OllamaServiceStandard] Get models error: " . $e->getMessage());
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
      'base_url' => $this->baseUrl,
      'available' => $this->isAvailable(),
      'client' => 'ollama-php-client'
    ];
  }
}
