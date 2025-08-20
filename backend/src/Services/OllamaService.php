<?php

namespace Services;

use Domain\CvSchema;
use Services\Exceptions\AiUnavailableException;
use Services\PdfTextService;

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
   * Analiza PDF de CV directamente con Llama3.2 sin extracción previa de texto
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

    // NUEVO: Validar PDF y obtener path sin extracción de texto
    $pdfTextService = new PdfTextService();
    $validatedPdfPath = $pdfTextService->validateAndReturnPath($pdfPath);

    // NUEVO: Convertir PDF a base64 para envío directo a Llama
    $pdfBase64 = base64_encode(file_get_contents($validatedPdfPath));

    if (!$pdfBase64) {
      throw new AiUnavailableException('PDF_ENCODING_FAILED');
    }

    // Procesar PDF directamente con Ollama
    $cvData = $this->analyzePdfDirectly($pdfBase64);

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[OllamaService] CV PDF análisis directo completado en {$duration}ms");

    return $cvData;
  }

  /**
   * LEGACY: Analiza texto de CV y devuelve JSON estructurado (método mantenido para compatibilidad)
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

    // Construir prompt legacy para análisis de texto
    $prompt = $this->buildLegacyTextAnalysisPrompt($rawText);

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
    error_log("[OllamaService] CV análisis de texto completado en {$duration}ms");

    return $cvData;
  }

  /**
   * LEGACY: Prompt para análisis de texto extraído (mantenido para compatibilidad)
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
   * Método para compatibilidad con llamadas existentes
   */
  public function analyzeCV($rawText): array
  {
    return $this->analyzeCvFromText($rawText);
  }

  /**
   * NUEVO: Analiza PDF directamente enviando datos binarios a Llama3.2
   *
   * @param string $pdfBase64 PDF codificado en base64
   * @return array Datos estructurados del CV según CvFormData
   * @throws AiUnavailableException Si Ollama no puede procesar el PDF
   */
  private function analyzePdfDirectly(string $pdfBase64): array
  {
    if (empty($pdfBase64)) {
      throw new AiUnavailableException('EMPTY_PDF_DATA');
    }

    $startTime = microtime(true);

    // Construir prompt específico para procesamiento directo de PDF
    $prompt = $this->buildDirectPdfAnalysisPrompt($pdfBase64);

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
    error_log("[OllamaService] CV PDF análisis directo completado en {$duration}ms");

    return $cvData;
  }

  /**
   * Construye el prompt específico para análisis directo de PDF según campos CvFormData
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
