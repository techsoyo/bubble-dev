<?php

declare(strict_types=1);

namespace Services\Interfaces;

/**
 * AI Provider Interface
 *
 * Define el contrato para todos los proveedores de IA
 *
 * @package Backend\Services\Interfaces
 * @version 1.0.0
 * @since 2025-08-10
 */
interface AIProviderInterface
{
  /**
   * Ejecuta una consulta de chat completion
   *
   * @param string $prompt El prompt a enviar
   * @param array $options Opciones adicionales (modelo, temperatura, etc.)
   * @return string|null Respuesta del modelo o null si falla
   */
  public function chatCompletion(string $prompt, array $options = []): ?string;

  /**
   * Verifica si el servicio está disponible
   *
   * @return bool True si el servicio está operativo
   */
  public function isAvailable(): bool;

  /**
   * Obtiene el nombre del proveedor
   *
   * @return string Nombre del proveedor
   */
  public function getProviderName(): string;

  /**
   * Obtiene el modelo por defecto
   *
   * @return string Nombre del modelo
   */
  public function getDefaultModel(): string;
}
