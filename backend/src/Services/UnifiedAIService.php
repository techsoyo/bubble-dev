<?php

declare(strict_types=1);

namespace Services;

use Services\Interfaces\AIProviderInterface;
use Services\Exceptions\AiUnavailableException;

/**
 * Unified AI Service
 *
 * Servicio unificado de IA que permite cambiar entre proveedores dinámicamente
 * Proporciona una interfaz consistente para todas las operaciones de IA
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-10
 */
class UnifiedAIService
{
  private AIProviderInterface $currentProvider;
  private array $providerStats = [];

  public function __construct(?string $providerName = null)
  {
    try {
      $this->currentProvider = AIServiceFactory::getCurrentProvider($providerName);
      $this->initializeProviderStats();
    } catch (AiUnavailableException $e) {
      error_log("[UnifiedAIService] Error inicializando proveedor: " . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Inicializa estadísticas de proveedores
   */
  private function initializeProviderStats(): void
  {
    $providers = AIServiceFactory::listProviders();
    foreach ($providers as $provider) {
      $this->providerStats[$provider] = [
        'requests' => 0,
        'successes' => 0,
        'failures' => 0,
        'last_used' => null,
        'average_response_time' => 0
      ];
    }
  }

  /**
   * Ejecuta una consulta de chat completion
   *
   * @param string $prompt El prompt a enviar
   * @param array $options Opciones adicionales
   * @return string|null Respuesta del modelo
   */
  public function chatCompletion(string $prompt, array $options = []): ?string
  {
    $startTime = microtime(true);
    $providerName = $this->currentProvider->getProviderName();

    try {
      $result = $this->currentProvider->chatCompletion($prompt, $options);

      $this->updateProviderStats($providerName, true, $startTime);
      return $result;
    } catch (\Exception $e) {
      $this->updateProviderStats($providerName, false, $startTime);
      error_log("[UnifiedAIService] Error en chat completion: " . $e->getMessage());

      // Intentar con otro proveedor disponible
      return $this->tryFallbackProvider($prompt, $options);
    }
  }

  /**
   * Actualiza estadísticas del proveedor
   */
  private function updateProviderStats(string $providerName, bool $success, float $startTime): void
  {
    if (!isset($this->providerStats[$providerName])) {
      $this->providerStats[$providerName] = [
        'requests' => 0,
        'successes' => 0,
        'failures' => 0,
        'last_used' => null,
        'average_response_time' => 0
      ];
    }

    $stats = &$this->providerStats[$providerName];
    $stats['requests']++;
    $stats['last_used'] = date('Y-m-d H:i:s');

    if ($success) {
      $stats['successes']++;
    } else {
      $stats['failures']++;
    }

    // Calcular tiempo de respuesta promedio
    $responseTime = (microtime(true) - $startTime) * 1000;
    $stats['average_response_time'] = (($stats['average_response_time'] * ($stats['requests'] - 1)) + $responseTime) / $stats['requests'];
  }

  /**
   * Intenta usar un proveedor alternativo en caso de fallo
   */
  private function tryFallbackProvider(string $prompt, array $options = []): ?string
  {
    $providers = AIServiceFactory::listProviders();

    foreach ($providers as $providerName) {
      if ($providerName === $this->currentProvider->getProviderName()) {
        continue;
      }

      try {
        $fallbackProvider = AIServiceFactory::getProvider($providerName);
        if ($fallbackProvider && $fallbackProvider->isAvailable()) {
          error_log("[UnifiedAIService] Intentando proveedor alternativo: {$providerName}");
          $result = $fallbackProvider->chatCompletion($prompt, $options);

          if ($result) {
            $this->updateProviderStats($providerName, true, microtime(true));
            return $result;
          }
        }
      } catch (\Exception $e) {
        error_log("[UnifiedAIService] Proveedor alternativo {$providerName} también falló: " . $e->getMessage());
        $this->updateProviderStats($providerName, false, microtime(true));
      }
    }

    return null;
  }

  /**
   * Cambia al proveedor especificado
   *
   * @param string $providerName Nombre del proveedor
   * @throws AiUnavailableException
   */
  public function switchProvider(string $providerName): void
  {
    $this->currentProvider = AIServiceFactory::getCurrentProvider($providerName);
  }

  /**
   * Obtiene el proveedor actual
   *
   * @return AIProviderInterface
   */
  public function getCurrentProvider(): AIProviderInterface
  {
    return $this->currentProvider;
  }

  /**
   * Obtiene estadísticas de uso de proveedores
   *
   * @return array Estadísticas de proveedores
   */
  public function getProviderStats(): array
  {
    return $this->providerStats;
  }

  /**
   * Verifica si el servicio está disponible
   *
   * @return bool
   */
  public function isAvailable(): bool
  {
    return $this->currentProvider->isAvailable();
  }

  /**
   * Genera contenido usando IA (job descriptions, etc.)
   *
   * @param string $type Tipo de contenido
   * @param array $data Datos para generar contenido
   * @return string|null Contenido generado
   */
  public function generateContent(string $type, array $data): ?string
  {
    $prompts = [
      'job_description' => $this->buildJobDescriptionPrompt($data),
      'interview_questions' => $this->buildInterviewQuestionsPrompt($data),
      'email_template' => $this->buildEmailTemplatePrompt($data),
      'cover_letter' => $this->buildCoverLetterPrompt($data)
    ];

    if (!isset($prompts[$type])) {
      throw new \InvalidArgumentException("Tipo de contenido no soportado: {$type}");
    }

    return $this->chatCompletion($prompts[$type], [
      'temperature' => 0.7,
      'max_tokens' => 2000
    ]);
  }

  /**
   * Construye prompt para descripción de trabajo
   */
  private function buildJobDescriptionPrompt(array $data): string
  {
    return "Genera una descripción de trabajo profesional y atractiva basada en la siguiente información:\n\n" .
      "Título: {$data['title']}\n" .
      "Empresa: {$data['company']}\n" .
      "Ubicación: {$data['location']}\n" .
      "Tipo: {$data['type']}\n" .
      "Salario: {$data['salary']}\n" .
      "Requisitos: {$data['requirements']}\n" .
      "Responsabilidades: {$data['responsibilities']}\n\n" .
      "La descripción debe ser profesional, atractiva y completa.";
  }

  /**
   * Construye prompt para preguntas de entrevista
   */
  private function buildInterviewQuestionsPrompt(array $data): string
  {
    return "Genera 10 preguntas de entrevista relevantes para el siguiente puesto:\n\n" .
      "Título: {$data['title']}\n" .
      "Descripción: {$data['description']}\n" .
      "Habilidades requeridas: {$data['skills']}\n\n" .
      "Las preguntas deben evaluar tanto habilidades técnicas como comportamentales.";
  }

  /**
   * Construye prompt para plantilla de email
   */
  private function buildEmailTemplatePrompt(array $data): string
  {
    return "Genera una plantilla de email profesional para: {$data['purpose']}\n\n" .
      "Contexto: {$data['context']}\n" .
      "Destinatario: {$data['recipient']}\n" .
      "Información clave: {$data['key_info']}\n\n" .
      "El email debe ser cortés, profesional y efectivo.";
  }

  /**
   * Construye prompt para carta de presentación
   */
  private function buildCoverLetterPrompt(array $data): string
  {
    return "Genera una carta de presentación personalizada:\n\n" .
      "Puesto: {$data['position']}\n" .
      "Empresa: {$data['company']}\n" .
      "Experiencia del candidato: {$data['experience']}\n" .
      "Habilidades: {$data['skills']}\n\n" .
      "La carta debe destacar por qué el candidato es ideal para el puesto.";
  }
}
