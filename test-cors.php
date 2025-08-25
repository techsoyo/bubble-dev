<?php

/**
 * Test CORS Configuration
 * Archivo para probar que las variables de entorno CORS funcionan correctamente
 */

declare(strict_types=1);

// Incluir bootstrap que ahora carga .env y configura CORS
require_once __DIR__ . '/backend/config/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// Test 1: Verificar que las variables de entorno se cargaron
echo "=== TEST CORS CONFIGURATION ===\n\n";

echo "1. Variables de entorno cargadas:\n";
echo "   CORS_ALLOWED_METHODS: " . (getenv('CORS_ALLOWED_METHODS') ?: 'NO DEFINIDO') . "\n";
echo "   CORS_ALLOWED_ORIGINS: " . (getenv('CORS_ALLOWED_ORIGINS') ?: 'NO DEFINIDO') . "\n";
echo "   CORS_ALLOWED_HEADERS: " . (getenv('CORS_ALLOWED_HEADERS') ?: 'NO DEFINIDO') . "\n\n";

// Test 2: Verificar headers CORS enviados
echo "2. Headers CORS enviados:\n";
$headers = headers_list();
foreach ($headers as $header) {
  if (stripos($header, 'Access-Control') === 0) {
    echo "   $header\n";
  }
}

// Test 3: Información del request actual
echo "\n3. Request actual:\n";
echo "   Método: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "   Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'No definido') . "\n";

// Test 4: Respuesta JSON de prueba
$response = [
  'success' => true,
  'message' => 'CORS configurado correctamente',
  'cors_config' => [
    'allowed_methods' => getenv('CORS_ALLOWED_METHODS'),
    'allowed_origins' => getenv('CORS_ALLOWED_ORIGINS'),
    'allowed_headers' => getenv('CORS_ALLOWED_HEADERS')
  ],
  'headers_sent' => array_filter($headers, function ($header) {
    return stripos($header, 'Access-Control') === 0;
  }),
  'timestamp' => date('c')
];

echo "\n4. Respuesta JSON:\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
