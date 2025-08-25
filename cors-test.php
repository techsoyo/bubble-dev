<?php

/**
 * Test CORS Headers - Endpoint simple para verificar CORS
 */

declare(strict_types=1);

// Incluir bootstrap que configura CORS
require_once __DIR__ . '/backend/config/bootstrap.php';

// Forzar output del estado de headers antes de cualquier otro output
ob_start();

// Respuesta JSON
header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
  'success' => true,
  'message' => 'Test CORS exitoso',
  'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
  'cors_config' => [
    'allowed_methods' => getenv('CORS_ALLOWED_METHODS'),
    'allowed_origins' => getenv('CORS_ALLOWED_ORIGINS'),
    'allowed_headers' => getenv('CORS_ALLOWED_HEADERS')
  ],
  'headers' => getallheaders() ?: [],
  'timestamp' => date('c')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

ob_end_flush();
