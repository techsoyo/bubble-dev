<?php

declare(strict_types=1);

/**
 * AI Providers Bootstrap
 *
 * Inicializa y registra todos los proveedores de IA disponibles
 * Este archivo debe ser incluido al inicio de la aplicación
 *
 * @package Backend\Config
 * @version 1.0.0
 * @since 2025-08-10
 */

require_once __DIR__ . '/../Services/AIServiceFactory.php';
require_once __DIR__ . '/../Services/Providers/GroqProvider.php';
require_once __DIR__ . '/../Services/Providers/OpenAIProvider.php';

use Services\AIServiceFactory;
use Services\Providers\GroqProvider;
use Services\Providers\OpenAIProvider;

// Registrar proveedores disponibles
try {
  // Registrar proveedor Groq
  $groqProvider = new GroqProvider();
  if ($groqProvider->isAvailable()) {
    AIServiceFactory::registerProvider('groq', $groqProvider);
    error_log("[AI Bootstrap] Proveedor Groq registrado exitosamente");
  } else {
    error_log("[AI Bootstrap] Proveedor Groq no disponible - verificar GROQ_API_KEY");
  }

  // Registrar proveedor OpenAI
  $openAIProvider = new OpenAIProvider();
  if ($openAIProvider->isAvailable()) {
    AIServiceFactory::registerProvider('openai', $openAIProvider);
    error_log("[AI Bootstrap] Proveedor OpenAI registrado exitosamente");
  } else {
    error_log("[AI Bootstrap] Proveedor OpenAI no disponible - verificar OPENAI_API_KEY");
  }

  // Mostrar información de proveedores registrados
  $providersInfo = AIServiceFactory::getProvidersInfo();
  error_log("[AI Bootstrap] Proveedores registrados: " . json_encode($providersInfo));
} catch (\Exception $e) {
  error_log("[AI Bootstrap] Error inicializando proveedores de IA: " . $e->getMessage());
}
