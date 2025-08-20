<?php

/**
 * Test simple y directo del cliente Ollama
 */

require_once __DIR__ . '/autoload.php';

use Hanwoolderink\Ollama\Ollama;
use GuzzleHttp\Client;

echo "=== TEST DIRECTO CLIENTE OLLAMA ===\n\n";

try {
  // Crear cliente con timeout más corto para test
  $guzzleClient = new Client([
    'base_uri' => 'http://localhost:11434',
    'timeout' => 30 // 30 segundos para test
  ]);

  $ollama = new Ollama($guzzleClient);
  echo "✓ Cliente Ollama creado\n";

  // Test simple con texto corto
  echo "\nProbando con prompt simple...\n";
  $response = $ollama->completion()->create(
    model: 'llama3.2:latest',
    prompt: 'Responde solo: "Hola mundo"'
  );

  echo "✓ Respuesta recibida\n";
  echo "Contenido: " . $response->content . "\n";
  echo "Modelo usado: " . $response->model . "\n";
  echo "Duración total: " . $response->total_duration . " nanosegundos\n";

  echo "\n=== CLIENTE FUNCIONANDO CORRECTAMENTE ===\n";
} catch (\Exception $e) {
  echo "✗ ERROR: " . $e->getMessage() . "\n";
  echo "Tipo: " . get_class($e) . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
