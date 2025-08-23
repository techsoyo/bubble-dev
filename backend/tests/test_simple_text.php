<?php

/**
 * Test SIMPLE con texto de ejemplo para verificar el cliente
 */

require_once __DIR__ . '/autoload.php';

use Services\OllamaServiceStandard;
use Services\Exceptions\AiUnavailableException;

echo "=== TEST SIMPLE - ANÁLISIS DE TEXTO ===\n\n";

try {
  // Texto de ejemplo de CV
  $testCvText = "
    CURRICULUM VITAE
    
    Javier Rodríguez
    Marketing Digital Specialist
    Email: javier.rodriguez@email.com
    Teléfono: +34 612 345 678
    LinkedIn: linkedin.com/in/javier-rodriguez
    
    PERFIL PROFESIONAL
    Especialista en marketing digital con más de 5 años de experiencia en estrategias de contenido, 
    redes sociales y campañas de publicidad online. Experto en Google Ads, Facebook Ads y analítica web.
    
    EXPERIENCIA PROFESIONAL
    Marketing Manager - Digital Agency Pro (2020-2023)
    - Gestión de campañas de Google Ads con presupuesto de 50,000€/mes
    - Desarrollo de estrategias de contenido para redes sociales
    - Análisis de métricas y reporting a clientes
    
    Marketing Specialist - StartupTech (2018-2020)
    - Creación de contenido para blog y redes sociales
    - Gestión de campañas de email marketing
    - Coordinación con equipo de diseño
    
    EDUCACIÓN
    Máster en Marketing Digital - Universidad Complutense Madrid (2018)
    Licenciatura en Publicidad y RRPP - Universidad Autónoma Madrid (2016)
    
    HABILIDADES
    Técnicas: Google Analytics, Google Ads, Facebook Business, Hootsuite, Mailchimp, WordPress
    Personales: Creatividad, trabajo en equipo, orientación a resultados, adaptabilidad
    
    IDIOMAS
    Español: Nativo
    Inglés: Avanzado (C1)
    Francés: Intermedio (B2)
    ";

  echo "✓ Texto de CV de prueba preparado: " . number_format(strlen($testCvText)) . " caracteres\n\n";

  // Inicializar servicio
  $service = new OllamaServiceStandard();
  echo "✓ OllamaServiceStandard inicializado\n";

  // Verificar disponibilidad
  echo "\n1. Verificando disponibilidad de Ollama...\n";
  $isAvailable = $service->isAvailable();

  if (!$isAvailable) {
    throw new \Exception("Ollama no está disponible");
  }

  echo "✓ Ollama está disponible\n";

  // Análizar texto
  echo "\n2. *** ANÁLISIS DE TEXTO ***\n";
  echo "Procesando CV con Ollama...\n";

  $startTime = microtime(true);

  try {
    $cvData = $service->analyzeCvFromText($testCvText);

    $duration = round((microtime(true) - $startTime) * 1000);

    echo "✓ ANÁLISIS COMPLETADO en {$duration}ms\n\n";

    // Mostrar resultados
    echo "=== DATOS EXTRAÍDOS ===\n";
    echo "Nombre: " . ($cvData['nombre'] ?? 'N/A') . "\n";
    echo "Email: " . ($cvData['email'] ?? 'N/A') . "\n";
    echo "Teléfono: " . ($cvData['telefono'] ?? 'N/A') . "\n";
    echo "Ubicación: " . ($cvData['ubicacion_actual'] ?? 'N/A') . "\n";

    if (!empty($cvData['resumen_profesional'])) {
      echo "\nResumen: " . substr($cvData['resumen_profesional'], 0, 200) . "...\n";
    }

    if (!empty($cvData['hard_skills'])) {
      echo "\nHabilidades técnicas: " . implode(', ', array_slice($cvData['hard_skills'], 0, 5)) . "\n";
    }

    echo "\n✓ Campos extraídos: " . count($cvData) . "\n";
    echo "✓ Fuente: " . ($cvData['data_source'] ?? 'N/A') . "\n";

    echo "\n=== TEST SIMPLE EXITOSO ===\n";
  } catch (AiUnavailableException $e) {
    echo "✗ Error de IA: " . $e->getMessage() . "\n";
    throw $e;
  }
} catch (\Exception $e) {
  echo "\n✗ ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}
