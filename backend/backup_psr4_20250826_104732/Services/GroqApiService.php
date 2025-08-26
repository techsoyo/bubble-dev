<?php declare(strict_types=1);

namespace Services\GroqApiService.php\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Domain\CvSchema;
use Services\Exceptions\AiUnavailableException;

/**
 * Servicio de IA usando Groq API
 * 
 * Servicio de producciÃ³n que utiliza la API de Groq con modelos Llama3
 * como alternativa rÃ¡pida y gratuita para anÃ¡lisis de CV.
 * Groq ofrece velocidades ultra rÃ¡pidas gracias a sus chips LPU especializados.
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-20
 */
class GroqApiService
{
  private Client $httpClient;
  private string $apiKey;
  private string $baseUrl;
  private string $model;
  private int $timeoutSeconds;
  private int $maxRetries;

  public function __construct()
  {
    // ConfiguraciÃ³n desde variables de entorno
    $this->apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');
    $this->baseUrl = $_ENV['GROQ_BASE_URL'] ?? getenv('GROQ_BASE_URL') ?: 'https://api.groq.com';
    $this->model = $_ENV['GROQ_MODEL'] ?? getenv('GROQ_MODEL') ?: 'llama3-8b-8192';
    $this->timeoutSeconds = (int)(($_ENV['GROQ_TIMEOUT_SECONDS'] ?? getenv('GROQ_TIMEOUT_SECONDS')) ?: 120);
    $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);

    if (empty($this->apiKey)) {
      throw new \InvalidArgumentException('GROQ_API_KEY requerido. Obtenerla GRATIS en: https://console.groq.com/keys');
    }

    // Inicializar cliente HTTP con configuraciÃ³n optimizada
    $this->httpClient = new Client([
      'base_uri' => $this->baseUrl,
      'timeout' => $this->timeoutSeconds,
      'headers' => [
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json',
        'User-Agent' => 'BubbleOfTalents/1.0 GroqApiService'
      ],
      // ConfiguraciÃ³n SSL para desarrollo en Windows/Laragon
      'verify' => false, // Solo para desarrollo - deshabilita verificaciÃ³n SSL
      'curl' => [
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
      ]
    ]);
  }

  /**
   * Analiza PDF de CV usando Groq API con extracciÃ³n de texto
   *
   * @param string $pdfPath Ruta al archivo PDF del CV
   * @return array Datos estructurados del CV segÃºn CvFormData del frontend
   * @throws AiUnavailableException Si la API no estÃ¡ disponible o devuelve JSON invÃ¡lido
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

      error_log("[GroqApiService] Texto extraÃ­do del PDF: " . strlen($extractedText) . " caracteres");

      // Analizar el texto extraÃ­do
      $cvData = $this->analyzeCvFromText($extractedText);
    } catch (\Exception $e) {
      error_log("[GroqApiService] Error extrayendo texto del PDF: " . $e->getMessage());
      throw new AiUnavailableException('PDF_TEXT_EXTRACTION_FAILED');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[GroqApiService] CV PDF anÃ¡lisis completado en {$duration}ms");

    return $cvData;
  }

  /**
   * Analiza texto de CV usando Groq API
   *
   * @param string $rawText Texto del CV a analizar
   * @return array Datos estructurados del CV
   * @throws AiUnavailableException Si la API falla o devuelve respuesta invÃ¡lida
   */
  public function analyzeCvFromText(string $rawText): array
  {
    if (empty($rawText)) {
      throw new AiUnavailableException('EMPTY_TEXT');
    }

    $startTime = microtime(true);

    $prompt = $this->buildCvAnalysisPrompt($rawText);
    $response = $this->callGroqApiWithRetries($prompt);

    if (!$response) {
      throw new AiUnavailableException('AI_UNAVAILABLE');
    }

    $cvData = $this->parseJsonResponse($response);

    if (!$cvData) {
      throw new AiUnavailableException('INVALID_JSON_RESPONSE');
    }

    $duration = round((microtime(true) - $startTime) * 1000);
    error_log("[GroqApiService] CV anÃ¡lisis de texto completado en {$duration}ms");

    return $cvData;
  }

  /**
   * MÃ©todo de compatibilidad con OllamaService
   */
  public function analyzeCV($rawText): array
  {
    return $this->analyzeCvFromText($rawText);
  }

  /**
   * Construye prompt optimizado para anÃ¡lisis de CV con Groq/Llama3
   */
  private function buildCvAnalysisPrompt(string $cvText): string
  {
    return "Eres un experto analizador de currÃ­culos vitae. Analiza el siguiente CV y extrae la informaciÃ³n en formato JSON estrictamente vÃ¡lido.

**INSTRUCCIONES CRÃTICAS:**
1. Responde ÃšNICAMENTE con JSON vÃ¡lido, sin texto adicional, explicaciones o markdown
2. NO uses bloques de cÃ³digo como ```json
3. Extrae SOLO la informaciÃ³n que estÃ¡ presente en el CV
4. Si un campo no tiene informaciÃ³n, usa null o array vacÃ­o segÃºn corresponda
5. Usa el esquema exacto que se proporciona abajo

**ESQUEMA JSON REQUERIDO (TODOS LOS CAMPOS):**
{
  \"nombre\": \"\",
  \"email\": \"\",
  \"telefono\": \"\",
  \"ubicacion_actual\": \"\",
  \"fecha_nacimiento\": null,
  \"portfolio\": null,
  \"linkedin\": null,
  \"otras_redes\": [],
  \"resumen_profesional\": \"\",
  \"soft_skills\": [],
  \"hard_skills\": [],
  \"idiomas\": [
    {
      \"idioma\": \"\",
      \"nivel\": \"\"
    }
  ],
  \"intereses\": [],
  \"referencias\": null,
  \"disponibilidad\": null,
  \"puestos_anteriores\": [
    {
      \"puesto\": \"\",
      \"empresa\": \"\",
      \"fecha_inicio\": null,
      \"fecha_fin\": null,
      \"descripcion\": \"\",
      \"responsabilidades\": [],
      \"ubicacion\": null,
      \"actual\": false
    }
  ],
  \"educacion\": [
    {
      \"titulo\": \"\",
      \"campo_estudio\": \"\",
      \"institucion\": \"\",
      \"fecha_inicio\": null,
      \"fecha_fin\": null,
      \"nivel_educativo\": \"\",
      \"descripcion\": null
    }
  ],
  \"certificaciones\": [],
  \"certificaciones_detalle\": [
    {
      \"nombre_certificacion\": \"\",
      \"emisor\": \"\",
      \"fecha_emision\": null,
      \"fecha_expiracion\": null
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
      \"fecha_inicio\": null,
      \"fecha_fin\": null,
      \"url\": null
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
  \"data_source\": \"ai_processing\",
  \"routing\": {
    \"fuente\": \"ai\",
    \"razon\": \"Procesado automÃ¡ticamente por Groq/Llama3\",
    \"fecha_asignacion\": \"" . date('c') . "\"
  }
}

**REGLAS ESPECÃFICAS:**
- nombre: Solo el nombre completo de la persona
- email: Solo direcciones de email vÃ¡lidas  
- telefono: Solo nÃºmeros de telÃ©fono (con formato internacional si es posible)
- ubicacion_actual: Ciudad, paÃ­s donde vive actualmente
- fecha_nacimiento: En formato YYYY-MM-DD si aparece en el CV
- resumen_profesional: Un resumen conciso de 2-3 oraciones sobre el perfil profesional
- hard_skills: Array de habilidades tÃ©cnicas especÃ­ficas (tecnologÃ­as, herramientas, software)
- soft_skills: Array de habilidades interpersonales y competencias blandas
- puestos_anteriores: Array con trabajos previos - marca actual=true si es el trabajo actual
- educacion: Array con estudios completos (titulo, institucion, fechas, nivel)
- idiomas: Array bÃ¡sico con idiomas y niveles
- idiomas_detalle: Array detallado con informaciÃ³n mÃ¡s especÃ­fica de idiomas
- certificaciones: Array simple con nombres de certificaciones
- certificaciones_detalle: Array con informaciÃ³n completa de certificaciones
- proyectos: Array con proyectos profesionales o acadÃ©micos relevantes
- referencias_detalle: Array con informaciÃ³n de contacto de referencias
- habilidades_adicionales: Habilidades que no encajan en hard/soft skills
- Fechas siempre en formato YYYY-MM-DD cuando sea posible
- data_source siempre debe ser \"ai_processing\"

**TEXTO DEL CV A ANALIZAR:**
{$cvText}

Responde SOLO con el JSON vÃ¡lido:";
  }

  /**
   * Realiza llamada a Groq API con sistema de reintentos
   */
  private function callGroqApiWithRetries(string $prompt): ?string
  {
    $attempt = 0;
    $lastException = null;

    while ($attempt < $this->maxRetries) {
      $attempt++;

      try {
        error_log("[GroqApiService] Intento {$attempt}/{$this->maxRetries} - Llamando a Groq API");

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

        $response = $this->httpClient->post('/openai/v1/chat/completions', [
          'json' => $payload
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        error_log("[GroqApiService] Respuesta HTTP {$statusCode}, body length: " . strlen($body));

        if ($statusCode === 200) {
          $data = json_decode($body, true);

          if ($data && isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            error_log("[GroqApiService] Ã‰xito en intento {$attempt}");

            // Log de estadÃ­sticas de uso
            if (isset($data['usage'])) {
              $usage = $data['usage'];
              error_log("[GroqApiService] Tokens usados: " . json_encode($usage));
            }

            return $content;
          } else {
            error_log("[GroqApiService] Respuesta sin contenido vÃ¡lido: " . substr($body, 0, 500));
          }
        } else {
          error_log("[GroqApiService] Error HTTP {$statusCode}: " . substr($body, 0, 500));
        }
      } catch (GuzzleException $e) {
        $lastException = $e;
        error_log("[GroqApiService] Exception en intento {$attempt}: " . $e->getMessage());

        if ($attempt < $this->maxRetries) {
          $delay = pow(2, $attempt) * 1000; // Backoff exponencial
          error_log("[GroqApiService] Esperando {$delay}ms antes del siguiente intento");
          usleep($delay * 1000);
        }
      }
    }

    error_log("[GroqApiService] Todos los intentos fallaron");
    if ($lastException) {
      error_log("[GroqApiService] Ãšltima excepciÃ³n: " . $lastException->getMessage());
    }

    return null;
  }

  /**
   * Parsea y valida respuesta JSON de Groq (basado en la app de ejemplo)
   */
  private function parseJsonResponse(string $response): ?array
  {
    if (empty($response)) {
      error_log("[GroqApiService] Respuesta vacÃ­a");
      return null;
    }

    // Limpiar respuesta si contiene markdown o texto adicional
    $cleanedResponse = $this->cleanJsonResponse($response);

    $cvData = json_decode($cleanedResponse, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("[GroqApiService] Error JSON: " . json_last_error_msg());
      error_log("[GroqApiService] Respuesta problemÃ¡tica: " . substr($response, 0, 1000));
      return null;
    }

    // Validar estructura bÃ¡sica
    if (!is_array($cvData) || empty($cvData)) {
      error_log("[GroqApiService] JSON vÃ¡lido pero estructura invÃ¡lida");
      return null;
    }

    // Normalizar usando CvSchema
    $normalizedData = CvSchema::normalize($cvData);

    error_log("[GroqApiService] JSON parseado y normalizado exitosamente - campos: " . count($normalizedData));
    return $normalizedData;
  }

  /**
   * Limpia respuesta JSON de markdown y texto adicional (copiado de la app ejemplo)
   */
  private function cleanJsonResponse(string $response): string
  {
    // Remover bloques de cÃ³digo markdown
    $response = preg_replace('/```json\s*/', '', $response);
    $response = preg_replace('/```\s*$/', '', $response);
    $response = preg_replace('/^```/', '', $response);

    // Buscar JSON vÃ¡lido en la respuesta
    $start = strpos($response, '{');
    $end = strrpos($response, '}');

    if ($start !== false && $end !== false && $end > $start) {
      $response = substr($response, $start, $end - $start + 1);
    }

    return trim($response);
  }

  /**
   * Verifica si la API estÃ¡ disponible
   */
  public function isAvailable(): bool
  {
    try {
      $response = $this->httpClient->get('/openai/v1/models', [
        'timeout' => 10
      ]);

      return $response->getStatusCode() === 200;
    } catch (\Exception $e) {
      error_log("[GroqApiService] Check disponibilidad fallÃ³: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Obtiene informaciÃ³n de uso/estadÃ­sticas
   */
  public function getUsageInfo(): array
  {
    return [
      'service' => 'Groq API',
      'model' => $this->model,
      'base_url' => $this->baseUrl,
      'timeout' => $this->timeoutSeconds . 's',
      'max_retries' => $this->maxRetries,
      'features' => [
        'ultra_fast_inference' => true,
        'free_tier' => true,
        'openai_compatible' => true,
        'models' => ['llama3-8b-8192', 'llama3-70b-8192', 'mixtral-8x7b-32768', 'gemma-7b-it']
      ]
    ];
  }

  /**
   * Lista modelos disponibles en Groq
   */
  public function listAvailableModels(): array
  {
    try {
      $response = $this->httpClient->get('/openai/v1/models');
      $data = json_decode($response->getBody()->getContents(), true);

      return $data['data'] ?? [];
    } catch (\Exception $e) {
      error_log("[GroqApiService] Error obteniendo modelos: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Obtiene informaciÃ³n del servicio para compatibilidad
   */
  public function getServiceInfo(): array
  {
    return [
      'provider' => 'groq',
      'service' => 'Groq API',
      'model' => $this->model,
      'base_url' => $this->baseUrl,
      'timeout' => $this->timeoutSeconds,
      'max_retries' => $this->maxRetries,
      'available' => $this->isAvailable(),
      'features' => [
        'ultra_fast_inference' => true,
        'free_tier' => true,
        'openai_compatible' => true,
        'models' => ['llama3-8b-8192', 'llama3-70b-8192', 'mixtral-8x7b-32768', 'gemma-7b-it']
      ]
    ];
  }

  /**
   * MÃ©todo chat para compatibilidad con AIController
   */
  public function chat(array $messages): array
  {
    try {
      $payload = [
        'model' => $this->model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 1024,
        'stream' => false
      ];

      $response = $this->httpClient->post('/openai/v1/chat/completions', [
        'json' => $payload
      ]);

      $statusCode = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($statusCode === 200) {
        $data = json_decode($body, true);

        if ($data && isset($data['choices'][0]['message']['content'])) {
          return [
            'success' => true,
            'message' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? []
          ];
        }
      }

      return [
        'success' => false,
        'message' => 'Error en la respuesta de Groq',
        'error' => $body
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'message' => 'Error de conexiÃ³n con Groq',
        'error' => $e->getMessage()
      ];
    }
  }
}
