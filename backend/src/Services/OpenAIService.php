<?php

declare(strict_types=1);

namespace Services;

use Domain\CvSchema;
use Services\Exceptions\AiUnavailableException;

/**
 * Servicio de IA Multi-Proveedor para Anáslisis de CV
 *
 * Soporta múltiples proveedores de IA:
 * - OpenAI (GPT models)
 * - Azure OpenAI
 * - Servicios locales compatibles con OpenAI
 *
 * @package Backend\Services
 * @version 3.0.0
 * @since 2025-01-11
 */
class OpenAIService
{
    private $provider;
    private $apiKey;
    private $apiUrl;
    private $model;
    private $timeoutMs;
    private $maxTokens;
    private $temperature;
    private $jsonMode;
    private $maxRetries;
    private $deployment; // Para Azure

    public function __construct()
    {
        // Cargar configuración desde .env
        $this->provider = ($_ENV['AI_PROVIDER'] ?? getenv('AI_PROVIDER')) ?: 'openai';
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
        $this->model = ($_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL')) ?: 'gpt-4o-mini';
        $this->timeoutMs = (int)(($_ENV['OPENAI_TIMEOUT_MS'] ?? getenv('OPENAI_TIMEOUT_MS')) ?: 20000);
        $this->maxTokens = (int)(($_ENV['OPENAI_MAX_TOKENS'] ?? getenv('OPENAI_MAX_TOKENS')) ?: 4000);
        $this->temperature = (float)(($_ENV['OPENAI_TEMPERATURE'] ?? getenv('OPENAI_TEMPERATURE')) ?: 0.3);
        $this->jsonMode = (($_ENV['OPENAI_JSON_MODE'] ?? getenv('OPENAI_JSON_MODE')) === 'true');
        $this->maxRetries = (int)(($_ENV['AI_MAX_RETRIES'] ?? getenv('AI_MAX_RETRIES')) ?: 3);

        // Configurar URLs y parí¡metros especí­ficos del proveedor
        $this->configureProvider();

        if (!$this->apiKey) {
            throw new \Exception('API key no configurada para el proveedor: ' . $this->provider);
        }
    }

    /**
     * Configura URLs y parí¡metros especí­ficos del proveedor
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
                // Para Ollama usamos el endpoint generate
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
     * Analiza texto de CV y devuelve JSON estructurado
     *
     * @param string $rawText Texto extraí­do del CV
     * @return array Datos estructurados del CV
     * @throws AiUnavailableException Si la IA no estí¡ disponible o devuelve JSON inví¡lido
     */
    public function analyzeCvFromText(string $rawText): array
    {
        if (empty($rawText)) {
            throw new AiUnavailableException('EMPTY_TEXT');
        }

        $startTime = microtime(true);
        $requestId = uniqid('ai_', true);

        try {
            $prompt = $this->buildCvAnalysisPrompt($rawText);
            $response = $this->callAiWithRetries($prompt, $requestId);

            // DEBUG: Guardar respuesta para debugging
            $debugFile = 'c:/laragon/www/bubble_of_talents_1.0/backend/uploads/debug_openai_' . time() . '.txt';
            file_put_contents($debugFile, "PROMPT:\n" . $prompt . "\n\nRESPONSE:\n" . $response);

            if (!$response) {
                throw new AiUnavailableException('NO_RESPONSE');
            }

            // Intentar decodificar JSON directamente
            $cvData = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Fallback: extraer JSON del contenido
                $cvData = $this->extractJsonFromResponse($response);

                if (!$cvData) {
                    $this->logRequest($requestId, $startTime, 0, 'JSON_DECODE_ERROR', strlen($rawText), strlen($response));
                    throw new AiUnavailableException('BAD_JSON');
                }
            }

            // Normalizar usando el esquema
            $normalizedData = CvSchema::normalize($cvData);

            $this->logRequest($requestId, $startTime, 200, $this->model, strlen($rawText), strlen($response));

            return $normalizedData;
        } catch (AiUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logRequest($requestId, $startTime, 500, 'EXCEPTION', strlen($rawText), 0, $e->getMessage());
            throw new AiUnavailableException('AI_ERROR: ' . $e->getMessage());
        }
    }

    /**
     * Construye el prompt sistemí¡tico para Anáslisis de CV
     */
    private function buildCvAnalysisPrompt(string $rawText): string
    {
        // Truncar texto si es muy largo (mí¡ximo 8000 caracteres)
        $truncatedText = mb_strlen($rawText) > 8000 ? mb_substr($rawText, 0, 8000) . "\n\n[TEXTO TRUNCADO]" : $rawText;

        $templateJson = json_encode(CvSchema::PROMPT_MINIMAL, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $systemPrompt = 'Eres un experto analizador de CV. Tu tarea es extraer TODA la información disponible del texto proporcionado y estructurarla en JSON. ' .
            'Busca activamente nombres, contactos, experiencia laboral, educación, habilidades, etc. ' .
            'Si encuentras información, úsala. Solo deja campos vací­os si realmente no aparece información. ' .
            'Fechas en formato YYYY-MM o YYYY-MM-DD. Devuelve SOLO JSON ví¡lido, sin comentarios adicionales.';

        $userPrompt = "Analiza el siguiente CV y extrae TODA la información disponible. Se especí­fico y completo:\n\n" .
            "CAMPOS A EXTRAER:\n" .
            "- nombre: Busca el nombre completo de la persona\n" .
            "- email: Busca direcciones de correo electrónico\n" .
            "- telefono: Busca números de teléfono (incluye códigos de paí­s)\n" .
            "- ubicacion_actual: Busca ciudad, paí­s, dirección actual\n" .
            "- fecha_nacimiento: Busca fecha de nacimiento (YYYY-MM-DD)\n" .
            "- portfolio, linkedin: Busca URLs de portfolio, LinkedIn, etc.\n" .
            "- otras_redes: Busca URLs de redes sociales adicionales\n" .
            "- resumen_profesional: Busca resumen, perfil profesional, objetivo\n" .
            "- soft_skills: Busca habilidades blandas (liderazgo, comunicación, etc.)\n" .
            "- hard_skills: Busca habilidades técnicas (software, lenguajes, herramientas)\n" .
            "- idiomas: Busca idiomas y niveles [{\"idioma\":\"Espaí±ol\",\"nivel\":\"Nativo\"}]\n" .
            "- intereses: Busca pasiones, hobbies, intereses personales\n" .
            "- disponibilidad: Busca información sobre disponibilidad laboral\n" .
            "- puestos_anteriores: Busca TODA la experiencia laboral con fechas, empresas, responsabilidades\n" .
            "- educacion: Busca TODA la educación: tí­tulos, universidades, fechas\n" .
            "- certificaciones: Busca certificaciones, cursos, capacitaciones\n" .
            "- proyectos: Busca proyectos mencionados\n\n" .
            "EJEMPLO DE EXPERIENCIA:\n" .
            '{\"puesto\":\"Diseí±ador Grí¡fico Senior\",\"empresa\":\"BBVA\",\"fecha_inicio\":\"2019-01\",\"fecha_fin\":\"2023-01\",\"descripcion\":\"Desarrollé estrategias visuales...\",\"responsabilidades\":[\"Coordiné equipo de 5 diseí±adores\",\"Implementé técnicas de diseí±o adaptativo\"]}' . "\n\n" .
            "TEXTO DEL CV:\n" . $truncatedText . "\n\n" .
            'Extrae TODA la información disponible y responde SOLO con JSON ví¡lido:';

        return $userPrompt;
    }
    /**
     * Realiza llamada a IA con reintentos exponenciales
     */
    private function callAiWithRetries(string $prompt, string $requestId): ?string
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            try {
                $response = $this->callAiProvider($prompt);

                if ($response !== null) {
                    return $response;
                }
            } catch (\Exception $e) {
                $lastError = $e;

                // Solo reintentar en errores especí­ficos
                if ($this->shouldRetry($e)) {
                    $attempt++;
                    if ($attempt < $this->maxRetries) {
                        // Backoff exponencial: 1s, 2s, 4s
                        $delay = pow(2, $attempt - 1);
                        sleep($delay);
                        continue;
                    }
                }
                break;
            }

            $attempt++;
        }

        if ($lastError) {
            throw $lastError;
        }

        return null;
    }

    /**
     * Realiza la llamada especí­fica al proveedor de IA
     */
    private function callAiProvider(string $prompt): ?string
    {
        $data = $this->buildRequestData($prompt);
        $headers = $this->buildHeaders();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => intval($this->timeoutMs / 1000),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: {$error}");
        }

        if ($httpCode === 429) {
            throw new \Exception('Rate limit exceeded', 429);
        }

        if ($httpCode >= 500) {
            throw new \Exception("Server error: HTTP {$httpCode}", $httpCode);
        }

        if ($httpCode !== 200) {
            throw new \Exception("API Error: HTTP {$httpCode} - {$response}", $httpCode);
        }

        $responseData = json_decode($response, true);

        if (!isset($responseData['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response structure: ' . $response);
        }

        return trim($responseData['choices'][0]['message']['content']);
    }

    /**
     * Construye los datos de la petición según el proveedor
     */
    private function buildRequestData(string $prompt): array
    {
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un parser de CV. Devuelve solo JSON, exactamente con estas claves (sin extras). Campos desconocidos ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ vací­o. Fechas YYYY-MM o YYYY-MM-DD. Lí­mite strings 2000 chars y arrays 200 items. Nunca pidas datos fuera del texto. Nunca metas comentarios fuera del JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature
        ];

        // Aí±adir modo JSON si estí¡ soportado
        if ($this->jsonMode && $this->supportsJsonMode()) {
            $data['response_format'] = ['type' => 'json_object'];
        }

        return $data;
    }
    /**
     * Construye headers según el proveedor
     */
    private function buildHeaders(): array
    {
        $headers = ['Content-Type: application/json'];

        switch ($this->provider) {
            case 'azure':
                $headers[] = 'api-key: ' . $this->apiKey;
                break;
            case 'openai':
            case 'local':
            default:
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
                break;
        }

        return $headers;
    }

    /**
     * Verifica si el proveedor soporta modo JSON
     */
    private function supportsJsonMode(): bool
    {
        // Solo GPT-4 y modelos superiores soportan JSON mode
        return strpos($this->model, 'gpt-4') !== false ||
            strpos($this->model, 'gpt-3.5-turbo') !== false;
    }

    /**
     * Determina si un error es retryable
     */
    private function shouldRetry(\Exception $e): bool
    {
        $code = $e->getCode();
        return $code === 429 || $code >= 500 || $code === CURLE_OPERATION_TIMEOUTED;
    }

    /**
     * Extrae JSON de respuesta usando regex como fallback
     */
    private function extractJsonFromResponse(string $response): ?array
    {
        // Buscar bloques JSON en la respuesta
        $patterns = [
            '/```json\s*(\{.*?\})\s*```/s',
            '/```\s*(\{.*?\})\s*```/s',
            '/(\{.*?\})/s'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $jsonData = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $jsonData;
                }
            }
        }

        return null;
    }

    /**
     * Registra métricas de la petición
     */
    private function logRequest(string $requestId, float $startTime, int $httpCode, string $model, int $inputSize, int $outputSize, string $error = ''): void
    {
        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $requestId,
            'provider' => $this->provider,
            'model' => $model,
            'http_code' => $httpCode,
            'latency_ms' => $latencyMs,
            'input_size' => $inputSize,
            'output_size' => $outputSize,
            'error' => $error
        ];
    }

    /**
     * Método legacy para compatibilidad hacia atrí¡s
     * @deprecated Use analyzeCvFromText instead
     */
    public function analyzeCVWithOpenAI($cvText)
    {
        try {
            return $this->analyzeCvFromText($cvText);
        } catch (AiUnavailableException $e) {
            // Retornar estructura bí¡sica en caso de error para compatibilidad
            return $this->createFallbackStructure($cvText);
        }
    }

    /**
     * Crea estructura bí¡sica como fallback
     */
    private function createFallbackStructure(string $cvText): array
    {
        $email = '';
        $nombre = '';
        $telefono = '';

        // Extraer información bí¡sica usando regex
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $cvText, $matches)) {
            $email = $matches[0];
        }

        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{3,4}/', $cvText, $matches)) {
            $telefono = $matches[0];
        }

        $lines = explode("\n", $cvText);
        foreach ($lines as $line) {
            $line = trim($line);
            if (
                strlen($line) > 5 && strlen($line) < 50 &&
                preg_match('/^[A-Za-zÃƒÆ’Ã¢â€šÂ¬-í¿\s]+$/', $line) &&
                str_word_count($line) >= 2
            ) {
                $nombre = $line;
                break;
            }
        }

        return [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'ubicacion_actual' => '',
            'fecha_nacimiento' => '',
            'portfolio' => '',
            'linkedin' => '',
            'otras_redes' => [],
            'resumen_profesional' => '',
            'soft_skills' => [],
            'hard_skills' => [],
            'idiomas' => [],
            'intereses' => [],
            'referencias' => [],
            'disponibilidad' => '',
            'puestos_anteriores' => [],
            'educacion' => [],
            'certificaciones' => [],
            'proyectos' => []
        ];
    }
}
