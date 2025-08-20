<?php

declare(strict_types=1);

// ====================================================================
// ENDPOINT MIGRADO: /api/cv/parse
// NUEVA ARQUITECTURA: Librerías estándar + DI + Repository Pattern
// ====================================================================

require_once __DIR__ . '/../../../config/bootstrap_new.php';

use Controllers\CvProcessingController;
use Symfony\Component\HttpFoundation\Request;
use Psr\Container\ContainerInterface;

/**
 * Endpoint migrado con arquitectura moderna
 * - Uso de librerías PHP estándar (Guzzle, Doctrine, etc.)
 * - Dependency Injection con Symfony DI
 * - Repository Pattern para base de datos
 * - Factory Pattern para proveedores de IA
 * - Middleware para CORS, Auth, Rate Limiting
 * - Logging estructurado con Monolog
 */

try {
  // Obtener container de DI configurado
  /** @var ContainerInterface $container */
  $container = require __DIR__ . '/../../../config/container.php';

  // Crear request de Symfony desde globals de PHP
  $request = Request::createFromGlobals();

  // Aplicar middleware stack
  $middlewareStack = $container->get('middleware.stack');
  $response = $middlewareStack->process($request, function ($request) use ($container) {

    // Obtener controller desde container (con todas las dependencias inyectadas)
    /** @var CvProcessingController $controller */
    $controller = $container->get(CvProcessingController::class);

    // Procesar request
    return $controller->upload($request);
  });

  // Enviar respuesta
  $response->send();
} catch (\Throwable $e) {

  // Manejo centralizado de errores con logging
  $logger = $container?->get('logger') ?? new \Monolog\Logger('error');

  $logger->error('Fatal error in CV parse endpoint', [
    'error' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'trace' => $e->getTraceAsString(),
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
  ]);

  // Respuesta de error estandarizada
  http_response_code(500);
  header('Content-Type: application/json');

  echo json_encode([
    'success' => false,
    'error' => [
      'code' => 'INTERNAL_SERVER_ERROR',
      'message' => 'Error interno del servidor',
      'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid()
    ]
  ], JSON_UNESCAPED_UNICODE);
}
