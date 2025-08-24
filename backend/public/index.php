<?php

/**
 * BUBBLE OF TALENTS - PUNTO DE ENTRADA PRINCIPAL
 * ===============================================
 *
 * FLUJO CORRECTO:
 * Apache/Laragon → .htaccess → public/index.php → AppRouter → Controllers
 *
 * Este archivo es el único punto de entrada para TODAS las requests
 * de la API cuando se usa con Apache/Laragon
 */

declare(strict_types=1);

use Router\AppRouter;

// ===== CONFIGURACIÓN DE RUTAS ABSOLUTAS =====
define('BACKEND_ROOT', dirname(__DIR__));
define('PROJECT_ROOT', dirname(BACKEND_ROOT));

// ===== INCLUDES CON RUTAS ABSOLUTAS =====
// 1. Cargar autoloader de Composer
if (!file_exists(BACKEND_ROOT . '/vendor/autoload.php')) {
  http_response_code(500);
  echo json_encode(['error' => 'Composer autoload not found. Run: composer install']);
  exit;
}
require_once BACKEND_ROOT . '/vendor/autoload.php';

// 2. Cargar bootstrap con configuraciones
if (!file_exists(BACKEND_ROOT . '/config/bootstrap.php')) {
  http_response_code(500);
  echo json_encode(['error' => 'Bootstrap config not found']);
  exit;
}
require_once BACKEND_ROOT . '/config/bootstrap.php';

// ===== HEADERS CORS PARA DESARROLLO =====
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');

// ===== MANEJO DE PREFLIGHT OPTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// ===== DESPACHAR ROUTER =====
try {
  // Cargar archivo de rutas centralizado
  $router = require_once BACKEND_ROOT . '/src/Router/routes.php';
  $router->dispatch();
} catch (Exception $e) {
  error_log('Router Error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'error' => 'Internal server error',
    'message' => $e->getMessage(),
    'debug' => [
      'file' => basename($e->getFile()),
      'line' => $e->getLine(),
      'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]
  ]);
}
