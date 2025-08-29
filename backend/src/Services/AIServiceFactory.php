<?php

declare(strict_types=1);

namespace Services;

use Services\Interfaces\AIProviderInterface;
use Services\Exceptions\AiUnavailableException;

/**
 * AI Service Factory
 *
 * Fábrica para crear instancias de servicios de IA basados en configuración
 * Permite cambiar entre proveedores dinámicamente
 *
 * @package Backend\Services
 * @version 1.0.0
 * @since 2025-08-10
 */
class AIServiceFactory
{
  private static $providers = [];
  private static $currentProvider = null;

  /**
   * Registra un proveedor de IA
   *
   * @param string $name Nombre del proveedor
   * @param AIProviderInterface $provider Instancia del proveedor
   */
  public static function registerProvider(string $name, AIProviderInterface $provider): void
  {
    self::$providers[$name] = $provider;
  }

  /**
   * Obtiene un proveedor específico
   *
   * @param string $name Nombre del proveedor
   * @return AIProviderInterface|null
   */
  public static function getProvider(string $name): ?AIProviderInterface
  {
    return self::$providers[$name] ?? null;
  }

  /**
   * Establece el proveedor actual basado en configuración
   *
   * @param string|null $providerName Nombre del proveedor (opcional)
   * @return AIProviderInterface
   * @throws AiUnavailableException
   */
  public static function getCurrentProvider(?string $providerName = null): AIProviderInterface
  {
    $provider = $providerName ?? self::getConfiguredProvider();

    if (!isset(self::$providers[$provider])) {
      throw new AiUnavailableException("Proveedor de IA no registrado: {$provider}");
    }

    $providerInstance = self::$providers[$provider];

    if (!$providerInstance->isAvailable()) {
      throw new AiUnavailableException("Proveedor de IA no disponible: {$provider}");
    }

    self::$currentProvider = $providerInstance;
    return $providerInstance;
  }

  /**
   * Obtiene el proveedor configurado desde variables de entorno
   *
   * @return string Nombre del proveedor configurado
   */
  private static function getConfiguredProvider(): string
  {
    $provider = $_ENV['AI_PROVIDER'] ?? (!empty(getenv('AI_PROVIDER')) ? getenv('AI_PROVIDER') : null);
    return $provider ?: 'groq';
  }
  /**
   * Lista todos los proveedores registrados
   *
   * @return array Lista de nombres de proveedores
   */
  public static function listProviders(): array
  {
    return array_keys(self::$providers);
  }

  /**
   * Verifica si un proveedor está disponible
   *
   * @param string $name Nombre del proveedor
   * @return bool
   */
  public static function isProviderAvailable(string $name): bool
  {
    $provider = self::getProvider($name);
    return $provider && $provider->isAvailable();
  }

  /**
   * Obtiene información de todos los proveedores
   *
   * @return array Información de proveedores
   */
  public static function getProvidersInfo(): array
  {
    $info = [];
    foreach (self::$providers as $name => $provider) {
      $info[$name] = [
        'name' => $provider->getProviderName(),
        'available' => $provider->isAvailable(),
        'default_model' => $provider->getDefaultModel()
      ];
    }
    return $info;
  }
}
