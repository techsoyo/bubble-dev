<?php

declare(strict_types=1);

namespace Services;

use Services\AI\AIProviderFactory;
use Services\Cache\CvCacheService;
use Services\Exceptions\CvProcessingException;
use Repositories\{CvRepository, CvMetricsRepository};
use DTOs\CvUploadRequest;
use Entities\{CvCandidate, CvProcessingMetric};
use Domain\CvSchema;
use Psr\Log\LoggerInterface;

/**
 * Servicio Principal de Procesamiento de CV
 * Nueva implementación con arquitectura moderna
 */
class CvProcessingService
{
  public function __construct(
    private AIProviderFactory $aiProviderFactory,
    private PdfTextService $pdfService,
    private FileStorageService $storageService,
    private CvRepository $cvRepository,
    private CvMetricsRepository $metricsRepository,
    private LoggerInterface $logger,
    private CvCacheService $cacheService
  ) {}

  /**
   * Procesar upload de CV con nueva arquitectura
   */
  public function processUpload(CvUploadRequest $request, string $requestId): array
  {
    $startTime = microtime(true);
    $fileHash = null;
    $metrics = new CvProcessingMetric($requestId);

    try {
      // 1. Almacenar archivo de forma segura
      $uploadStart = microtime(true);
      $filePath = $this->storageService->storeUploadedFile(
        $request->file,
        'cv',
        $requestId
      );
      $metrics->setUploadDuration((int)round((microtime(true) - $uploadStart) * 1000));
      $metrics->setFileSize(filesize($filePath));

      // 2. Verificar caché por hash del archivo
      $fileHash = $this->cacheService->getFileHash($filePath);
      $cachedResult = $this->cacheService->getCachedResult($fileHash);

      if ($cachedResult !== null) {
        $this->logger->info('CV result retrieved from cache', [
          'request_id' => $requestId,
          'file_hash' => $fileHash
        ]);

        $metrics->setSuccess(true)
          ->setProcessingMode('cached')
          ->setTotalDuration((int)round((microtime(true) - $startTime) * 1000));

        $this->metricsRepository->save($metrics);

        return [
          'data' => $cachedResult['data'],
          'meta' => $cachedResult['meta'] + ['cached' => true]
        ];
      }

      // 3. Procesar con IA
      $aiStart = microtime(true);
      $result = $this->processWithAI($filePath, $request, $requestId);
      $aiDuration = (int)round((microtime(true) - $aiStart) * 1000);

      // 4. Normalizar datos
      $normalized = CvSchema::normalize($result['ai_data']);

      // 5. Guardar en base de datos
      $candidate = $this->createCandidateFromData($normalized, $result['meta']);
      $this->cvRepository->save($candidate);

      // 6. Cachear resultado
      $cacheData = [
        'data' => $normalized,
        'meta' => $result['meta']
      ];
      $this->cacheService->cacheResult($fileHash, $cacheData);

      // 7. Guardar métricas
      $metrics->setSuccess(true)
        ->setAiProvider($result['meta']['provider'])
        ->setProcessingMode($request->processing_mode)
        ->setAiProcessingDuration($aiDuration)
        ->setTotalDuration((int)round((microtime(true) - $startTime) * 1000))
        ->setCandidateId($candidate->getId());

      $this->metricsRepository->save($metrics);

      // 8. Cleanup temporal
      $this->storageService->deleteFile($filePath);

      $this->logger->info('CV processed successfully', [
        'request_id' => $requestId,
        'candidate_id' => $candidate->getId(),
        'provider' => $result['meta']['provider'],
        'duration_ms' => $metrics->getTotalDuration()
      ]);

      return [
        'data' => $normalized,
        'meta' => $result['meta'] + [
          'candidate_id' => $candidate->getId(),
          'cached' => false
        ]
      ];
    } catch (\Throwable $e) {
      // Manejo centralizado de errores
      $this->handleProcessingError($e, $requestId, $metrics, $startTime);
      throw new CvProcessingException('Failed to process CV: ' . $e->getMessage(), 0, $e);
    }
  }

  private function processWithAI(string $filePath, CvUploadRequest $request, string $requestId): array
  {
    // Intentar con proveedor específico o buscar uno disponible
    if ($request->ai_provider !== 'auto') {
      try {
        $aiService = $this->aiProviderFactory->store($request->ai_provider);
        if (!$aiService->isAvailable()) {
          throw new \Exception("Provider {$request->ai_provider} is not available");
        }
      } catch (\Exception $e) {
        $this->logger->warning('Specific AI provider failed, falling back to auto-selection', [
          'request_id' => $requestId,
          'requested_provider' => $request->ai_provider,
          'error' => $e->getMessage()
        ]);
        $aiService = $this->aiProviderFactory->getAvailableProvider();
      }
    } else {
      $aiService = $this->aiProviderFactory->getAvailableProvider();
    }

    $providerName = $this->getProviderName($aiService);

    // Procesar según el tipo de servicio
    if (method_exists($aiService, 'analyzeCvFromPdf')) {
      // Procesamiento directo de PDF (Ollama)
      $aiData = $aiService->analyzeCvFromPdf($filePath);
    } else {
      // Extraer texto primero y luego procesar (OpenAI, etc.)
      $extractedText = $this->pdfService->extractText($filePath);
      $aiData = $aiService->analyzeCv($extractedText);
    }

    return [
      'ai_data' => $aiData,
      'meta' => [
        'mode' => 'ai',
        'provider' => $providerName,
        'confidence' => $this->calculateConfidence($aiData)
      ]
    ];
  }

  private function createCandidateFromData(array $normalizedData, array $meta): CvCandidate
  {
    $candidate = new CvCandidate();

    // Datos básicos
    $candidate->setNombre($normalizedData['nombre'] ?? '')
      ->setEmail($normalizedData['email'] ?? '')
      ->setTelefono($normalizedData['telefono'] ?? null)
      ->setUbicacionActual($normalizedData['ubicacion_actual'] ?? null)
      ->setResumenProfesional($normalizedData['resumen_profesional'] ?? null)
      ->setHardSkills($normalizedData['hard_skills'] ?? [])
      ->setSoftSkills($normalizedData['soft_skills'] ?? [])
      ->setDataSource('ai_processing')
      ->setAiProvider($meta['provider'])
      ->setAiConfidenceScore($meta['confidence'] ?? null);

    // Experiencias laborales
    foreach ($normalizedData['puestos_anteriores'] ?? [] as $experienceData) {
      $experience = new CvExperience();
      $experience->setPuesto($experienceData['puesto'] ?? '')
        ->setEmpresa($experienceData['empresa'] ?? '')
        ->setFechaInicio($this->parseDate($experienceData['fecha_inicio'] ?? null))
        ->setFechaFin($this->parseDate($experienceData['fecha_fin'] ?? null))
        ->setDescripcion($experienceData['descripcion'] ?? null);

      $candidate->addExperience($experience);
    }

    // Educación
    foreach ($normalizedData['educacion'] ?? [] as $educationData) {
      $education = new CvEducation();
      $education->setTitulo($educationData['titulo'] ?? '')
        ->setInstitucion($educationData['institucion'] ?? '')
        ->setFechaInicio($this->parseDate($educationData['fecha_inicio'] ?? null))
        ->setFechaFin($this->parseDate($educationData['fecha_fin'] ?? null))
        ->setDescripcion($educationData['descripcion'] ?? null);

      $candidate->addEducation($education);
    }

    return $candidate;
  }

  private function calculateConfidence(array $aiData): float
  {
    $score = 0.0;
    $maxScore = 10.0;

    // Verificar campos requeridos
    $requiredFields = ['nombre', 'email'];
    foreach ($requiredFields as $field) {
      if (!empty($aiData[$field])) {
        $score += 2.0;
      }
    }

    // Verificar campos opcionales importantes
    $importantFields = ['telefono', 'ubicacion_actual', 'resumen_profesional'];
    foreach ($importantFields as $field) {
      if (!empty($aiData[$field])) {
        $score += 1.0;
      }
    }

    // Verificar arrays de datos
    $arrayFields = ['hard_skills', 'soft_skills', 'puestos_anteriores', 'educacion'];
    foreach ($arrayFields as $field) {
      if (!empty($aiData[$field]) && is_array($aiData[$field])) {
        $score += 1.0;
      }
    }

    return min(1.0, $score / $maxScore);
  }

  private function getProviderName($aiService): string
  {
    $class = get_class($aiService);
    if (str_contains($class, 'Ollama')) return 'ollama';
    if (str_contains($class, 'OpenAI')) return 'openai';
    if (str_contains($class, 'Anthropic')) return 'anthropic';
    return 'unknown';
  }

  private function parseDate(?string $dateString): ?\DateTime
  {
    if (empty($dateString)) return null;

    try {
      return new \DateTime($dateString);
    } catch (\Exception $e) {
      return null;
    }
  }

  private function handleProcessingError(
    \Throwable $e,
    string $requestId,
    CvProcessingMetric $metrics,
    float $startTime
  ): void {
    $errorCode = $this->getErrorCode($e);

    $metrics->setSuccess(false)
      ->setErrorCode($errorCode)
      ->setErrorMessage($e->getMessage())
      ->setTotalDuration((int)round((microtime(true) - $startTime) * 1000));

    $this->metricsRepository->save($metrics);

    $this->logger->error('CV processing failed', [
      'request_id' => $requestId,
      'error_code' => $errorCode,
      'error_message' => $e->getMessage(),
      'error_class' => get_class($e),
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ]);
  }

  private function getErrorCode(\Throwable $e): string
  {
    if (str_contains($e->getMessage(), 'AI')) return 'AI_SERVICE_ERROR';
    if (str_contains($e->getMessage(), 'PDF')) return 'PDF_PROCESSING_ERROR';
    if (str_contains($e->getMessage(), 'storage')) return 'STORAGE_ERROR';
    if (str_contains($e->getMessage(), 'database')) return 'DATABASE_ERROR';
    return 'GENERAL_ERROR';
  }
}
