<?php

declare(strict_types=1);
/**
 * Endpoint de diagnóstico simple para verificar conectividad
 */

$ROOT = dirname(__DIR__, 1); // api → backend
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Bloquear en producción
if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}

// REMOVED: header('Content-Type: application/json'); // Use jsonResponse() instead

// Log para debugging
error_log('Diagnostics endpoint called - Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Diagnostics endpoint - Headers: ' . json_encode(getallheaders()));

// Respuesta simple de diagnóstico
echo json_encode([
  'success' => true,
  'message' => 'Endpoint de diagnóstico funcionando correctamente',
  'timestamp' => date('Y-m-d H:i:s'),
  'method' => $_SERVER['REQUEST_METHOD'],
  'server_info' => [
    'php_version' => phpversion(),
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
    'server_port' => $_SERVER['SERVER_PORT'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown'
  ]
]);
