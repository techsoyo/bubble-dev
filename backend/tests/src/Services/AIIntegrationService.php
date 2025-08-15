<?php

declare(strict_types=1);

namespace Services;

use Utils\Logger;

/**
 * AI Integration Service
 *
 * Production-ready service for integrating with AI modules with comprehensive
 * error handling, retry mechanisms, timeout handling, and response validation.
 *
 * @package Services
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-05
 */
class AIIntegrationService
{
    /**
     * AI module URL
     *
     * @var string
     */
    private string $aiModuleUrl;

    /**
     * Service enabled status
     *
     * @var bool
     */
    private bool $enabled;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private int $timeout;

    /**
     * Maximum retry attempts
     *
     * @var int
     */
    private int $maxRetries;

    /**
     * Retry delay in seconds
     *
     * @var int
     */
    private int $retryDelay;

    /**
     * Circuit breaker failure threshold
     *
     * @var int
     */
    private int $failureThreshold;

    /**
     * Circuit breaker state
     *
     * @var string
     */
    private string $circuitState = 'closed'; // closed, open, half-open

    /**
     * Last failure time for circuit breaker
     *
     * @var int|null
     */
    private ?int $lastFailureTime = null;

    /**
     * Consecutive failure count
     *
     * @var int
     */
    private int $failureCount = 0;

    /**
     * Initialize AI Integration Service
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function __construct()
    {
        $this->aiModuleUrl = $this->getConfig('AI_MODULE_URL', 'http://localhost');
        $this->enabled = $this->getConfig('AI_SERVICE_ENABLED', true);
        $this->timeout = $this->getConfig('AI_REQUEST_TIMEOUT', 30);
        $this->maxRetries = $this->getConfig('AI_MAX_RETRIES', 3);
        $this->retryDelay = $this->getConfig('AI_RETRY_DELAY', 2);
        $this->failureThreshold = $this->getConfig('AI_FAILURE_THRESHOLD', 5);

        $this->validateConfiguration();

        Logger::info('AIIntegrationService initialized', [
            'url' => $this->aiModuleUrl,
            'enabled' => $this->enabled,
            'timeout' => $this->timeout,
            'max_retries' => $this->maxRetries
        ]);
    }

    /**
     * Validates service configuration
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    private function validateConfiguration(): void
    {
        if ($this->timeout <= 0 || $this->timeout > 300) {
            throw new \InvalidArgumentException('AI timeout must be between 1 and 300 seconds');
        }

        if ($this->maxRetries < 0 || $this->maxRetries > 10) {
            throw new \InvalidArgumentException('AI max retries must be between 0 and 10');
        }

        if (!filter_var($this->aiModuleUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('AI module URL is not valid');
        }
    }

    /**
     * Gets configuration value with type safety
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    private function getConfig(string $key, $default)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        // Type conversion based on default value type
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_int($default)) {
            return filter_var($value, FILTER_VALIDATE_INT) ?: $default;
        }

        return $value;
    }

    /**
     * Checks if the AI service is available with circuit breaker pattern
     *
     * @return bool True if service is available
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled) {
            Logger::info('AI service is disabled');
            return false;
        }

        // Check circuit breaker state
        if ($this->circuitState === 'open') {
            $timeSinceFailure = time() - ($this->lastFailureTime ?? 0);

            // Try to move to half-open state after timeout
            if ($timeSinceFailure > 60) { // 60 seconds timeout
                $this->circuitState = 'half-open';
                Logger::info('AI circuit breaker moved to half-open state');
            } else {
                Logger::warning('AI service unavailable - circuit breaker open');
                return false;
            }
        }

        try {
            $response = $this->performHealthCheck();

            if ($response) {
                $this->resetCircuitBreaker();
                return true;
            }

            $this->recordFailure();
            return false;
        } catch (\Exception $e) {
            Logger::error('AI service health check failed', [], $e);
            $this->recordFailure();
            return false;
        }
    }

    /**
     * Performs health check on AI service
     *
     * @return bool True if service is healthy
     */
    private function performHealthCheck(): bool
    {
        $healthEndpoint = rtrim($this->aiModuleUrl, '/') . '/health';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => min($this->timeout, 10), // Shorter timeout for health check
                'header' => [
                    'User-Agent: AIIntegrationService/2.0',
                    'Accept: application/json'
                ]
            ]
        ]);

        $response = @file_get_contents($healthEndpoint, false, $context);

        return $response !== false;
    }

    /**
     * Records a failure for circuit breaker
     */
    private function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->circuitState = 'open';
            Logger::warning('AI circuit breaker opened due to failures', [
                'failure_count' => $this->failureCount,
                'threshold' => $this->failureThreshold
            ]);
        }
    }

    /**
     * Resets circuit breaker on successful operation
     */
    private function resetCircuitBreaker(): void
    {
        $this->failureCount = 0;
        $this->lastFailureTime = null;
        $this->circuitState = 'closed';
    }

    /**
     * Analyzes CV using AI service with comprehensive error handling
     *
     * @param string $textFilePath Path to CV text file
     * @param array<string, mixed> $options Analysis options
     * @return array<string, mixed> Analysis results
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function analyzeCV(string $textFilePath, array $options = []): array
    {
        // Validate input parameters
        if (empty($textFilePath)) {
            throw new \InvalidArgumentException('Text file path cannot be empty');
        }

        if (!file_exists($textFilePath)) {
            throw new \InvalidArgumentException("Text file does not exist: $textFilePath");
        }

        if (!is_readable($textFilePath)) {
            throw new \InvalidArgumentException("Text file is not readable: $textFilePath");
        }

        // Check service availability
        if (!$this->isAvailable()) {
            Logger::warning('AI service not available for CV analysis');
            return $this->getFallbackCVAnalysis();
        }

        $startTime = microtime(true);

        Logger::info('Starting CV analysis', [
            'file_path' => $textFilePath,
            'file_size' => filesize($textFilePath),
            'options' => $options
        ]);

        // Prepare request data
        $requestData = [
            'text_file_path' => $textFilePath,
            'options' => array_merge([
                'detailed_analysis' => false,
                'extract_skills' => true,
                'categorize' => true
            ], $options)
        ];

        // Validate request data
        if (!$this->validateAnalysisRequest($requestData)) {
            throw new \InvalidArgumentException('Invalid analysis request data');
        }

        try {
            $response = $this->makeRetryableRequest('/api/ai/analyze-cv.php', $requestData);

            // Validate response
            $validatedResponse = $this->validateAIResponse($response, 'cv_analysis');

            $duration = microtime(true) - $startTime;

            Logger::info('CV analysis completed successfully', [
                'duration' => round($duration, 3),
                'response_size' => strlen(json_encode($validatedResponse))
            ]);

            return $validatedResponse;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            Logger::error('CV analysis failed', [
                'file_path' => $textFilePath,
                'duration' => round($duration, 3),
                'error' => $e->getMessage()
            ]);

            // Return fallback response on error
            return array_merge($this->getFallbackCVAnalysis(), [
                'error' => 'AI analysis failed: ' . $e->getMessage(),
                'fallback_used' => true
            ]);
        }
    }

    /**
     * Extracts skills from CV text using AI service
     *
     * @param string $cvText CV text content
     * @param array<string, mixed> $options Extraction options
     * @return array<string, mixed> Extracted skills and metadata
     *
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function extractSkills(string $cvText, array $options = []): array
    {
        // Validate input
        if (empty($cvText)) {
            throw new \InvalidArgumentException('CV text cannot be empty');
        }

        if (strlen($cvText) > 1048576) { // 1MB limit
            throw new \InvalidArgumentException('CV text is too large (max 1MB)');
        }

        // Check service availability
        if (!$this->isAvailable()) {
            Logger::warning('AI service not available for skill extraction');
            return $this->getFallbackSkillExtraction($cvText);
        }

        $startTime = microtime(true);

        Logger::info('Starting skill extraction', [
            'text_length' => strlen($cvText),
            'options' => $options
        ]);

        // Prepare request data
        $requestData = [
            'cv_text' => $cvText,
            'options' => array_merge([
                'include_confidence' => true,
                'categorize_skills' => true,
                'min_confidence' => 0.7
            ], $options)
        ];

        try {
            $response = $this->makeRetryableRequest('/api/ai/extract-skills.php', $requestData);

            // Validate response
            $validatedResponse = $this->validateAIResponse($response, 'skill_extraction');

            $duration = microtime(true) - $startTime;

            Logger::info('Skill extraction completed successfully', [
                'duration' => round($duration, 3),
                'skills_found' => count($validatedResponse['skills'] ?? [])
            ]);

            return $validatedResponse;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            Logger::error('Skill extraction failed', [
                'duration' => round($duration, 3),
                'error' => $e->getMessage()
            ]);

            // Return fallback response
            return array_merge($this->getFallbackSkillExtraction($cvText), [
                'error' => 'AI skill extraction failed: ' . $e->getMessage(),
                'fallback_used' => true
            ]);
        }
    }

    /**
     * Makes retryable HTTP request to AI service
     *
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $data Request data
     * @return array<string, mixed> Response data
     *
     * @throws \Exception If all retry attempts fail
     */
    private function makeRetryableRequest(string $endpoint, array $data): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries + 1; $attempt++) {
            try {
                if ($attempt > 1) {
                    Logger::info("AI request retry attempt $attempt", [
                        'endpoint' => $endpoint,
                        'delay' => $this->retryDelay
                    ]);

                    sleep($this->retryDelay * ($attempt - 1)); // Exponential backoff
                }

                return $this->performHttpRequest($endpoint, $data);
            } catch (\Exception $e) {
                $lastException = $e;

                Logger::warning("AI request attempt $attempt failed", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage()
                ]);

                // Don't retry on certain types of errors
                if ($this->isNonRetryableError($e)) {
                    break;
                }
            }
        }

        // All attempts failed
        $this->recordFailure();
        throw new \Exception(
            "AI request failed after {$this->maxRetries} retries: " .
                ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Performs HTTP request to AI service
     *
     * @param string $endpoint API endpoint
     * @param array<string, mixed> $data Request data
     * @return array<string, mixed> Response data
     *
     * @throws \Exception If request fails
     */
    private function performHttpRequest(string $endpoint, array $data): array
    {
        $url = rtrim($this->aiModuleUrl, '/') . '/' . ltrim($endpoint, '/');
        $jsonData = json_encode($data);

        if ($jsonData === false) {
            throw new \Exception('Failed to encode request data as JSON');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonData),
                    'User-Agent: AIIntegrationService/2.0',
                    'Accept: application/json',
                    'X-Request-ID: ' . bin2hex(random_bytes(8))
                ],
                'content' => $jsonData,
                'timeout' => $this->timeout,
                'ignore_errors' => true // We'll handle HTTP errors manually
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new \Exception('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
        }

        // Check HTTP status code
        $statusCode = $this->getHttpStatusCode($http_response_header ?? []);

        if ($statusCode >= 400) {
            throw new \Exception("HTTP error $statusCode: $response");
        }

        $decodedResponse = json_decode($response, true);

        if ($decodedResponse === null) {
            throw new \Exception('Invalid JSON response from AI service');
        }

        return $decodedResponse;
    }

    /**
     * Extracts HTTP status code from response headers
     *
     * @param array<string> $headers HTTP response headers
     * @return int HTTP status code
     */
    private function getHttpStatusCode(array $headers): int
    {
        if (empty($headers)) {
            return 0;
        }

        $statusLine = $headers[0] ?? '';

        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Checks if an error should not be retried
     *
     * @param \Exception $exception Exception to check
     * @return bool True if error is non-retryable
     */
    private function isNonRetryableError(\Exception $exception): bool
    {
        $message = $exception->getMessage();

        // Don't retry on client errors (4xx)
        if (preg_match('/HTTP error 4\d{2}/', $message)) {
            return true;
        }

        // Don't retry on validation errors
        if (strpos($message, 'Invalid') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Validates AI response structure and content
     *
     * @param array<string, mixed> $response Response to validate
     * @param string $type Response type (cv_analysis, skill_extraction)
     * @return array<string, mixed> Validated response
     *
     * @throws \Exception If response is invalid
     */
    private function validateAIResponse(array $response, string $type): array
    {
        // Check for error in response
        if (isset($response['error'])) {
            throw new \Exception('AI service returned error: ' . $response['error']);
        }

        switch ($type) {
            case 'cv_analysis':
                return $this->validateCVAnalysisResponse($response);

            case 'skill_extraction':
                return $this->validateSkillExtractionResponse($response);

            default:
                throw new \InvalidArgumentException("Unknown response type: $type");
        }
    }

    /**
     * Validates CV analysis response
     *
     * @param array<string, mixed> $response Response to validate
     * @return array<string, mixed> Validated response
     *
     * @throws \Exception If response is invalid
     */
    private function validateCVAnalysisResponse(array $response): array
    {
        $required = ['status'];

        foreach ($required as $field) {
            if (!isset($response[$field])) {
                throw new \Exception("Missing required field in CV analysis response: $field");
            }
        }

        // Sanitize data fields
        if (isset($response['data'])) {
            $response['data'] = $this->sanitizeAIResponseData($response['data']);
        }

        return $response;
    }

    /**
     * Validates skill extraction response
     *
     * @param array<string, mixed> $response Response to validate
     * @return array<string, mixed> Validated response
     *
     * @throws \Exception If response is invalid
     */
    private function validateSkillExtractionResponse(array $response): array
    {
        if (!isset($response['skills'])) {
            throw new \Exception('Missing skills field in response');
        }

        if (!is_array($response['skills'])) {
            throw new \Exception('Skills field must be an array');
        }

        // Validate and sanitize skills
        $sanitizedSkills = [];

        foreach ($response['skills'] as $skill) {
            if (is_string($skill)) {
                $cleanSkill = htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8');
                if (!empty($cleanSkill) && strlen($cleanSkill) <= 100) {
                    $sanitizedSkills[] = $cleanSkill;
                }
            }
        }

        $response['skills'] = array_unique($sanitizedSkills);

        return $response;
    }

    /**
     * Sanitizes AI response data for security
     *
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitizeAIResponseData($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeAIResponseData'], $data);
        }

        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }

        return $data;
    }

    /**
     * Validates analysis request data
     *
     * @param array<string, mixed> $data Request data to validate
     * @return bool True if valid
     */
    private function validateAnalysisRequest(array $data): bool
    {
        if (!isset($data['text_file_path']) || !is_string($data['text_file_path'])) {
            return false;
        }

        if (isset($data['options']) && !is_array($data['options'])) {
            return false;
        }

        return true;
    }

    /**
     * Provides fallback CV analysis when AI service is unavailable
     *
     * @return array<string, mixed> Fallback analysis results
     */
    private function getFallbackCVAnalysis(): array
    {
        return [
            'status' => 'success',
            'data' => [
                'skills' => [],
                'experience' => 'Unknown',
                'category' => 'General',
                'confidence' => 0.5
            ],
            'ai_enabled' => false,
            'fallback_used' => true,
            'message' => 'AI service unavailable - using fallback analysis'
        ];
    }

    /**
     * Provides fallback skill extraction when AI service is unavailable
     *
     * @param string $cvText CV text for basic analysis
     * @return array<string, mixed> Fallback extraction results
     */
    private function getFallbackSkillExtraction(string $cvText): array
    {
        // Basic keyword matching for common skills
        $commonSkills = [
            'PHP',
            'JavaScript',
            'Python',
            'Java',
            'C++',
            'SQL',
            'HTML',
            'CSS',
            'React',
            'Vue',
            'Angular',
            'Node.js',
            'Laravel',
            'Symfony',
            'MySQL',
            'PostgreSQL',
            'MongoDB',
            'Redis',
            'Docker',
            'Git'
        ];

        $foundSkills = [];
        $cvTextUpper = strtoupper($cvText);

        foreach ($commonSkills as $skill) {
            if (strpos($cvTextUpper, strtoupper($skill)) !== false) {
                $foundSkills[] = $skill;
            }
        }

        return [
            'skills' => $foundSkills,
            'confidence' => 0.6,
            'ai_enabled' => false,
            'fallback_used' => true,
            'message' => 'AI service unavailable - using basic keyword matching'
        ];
    }
}
