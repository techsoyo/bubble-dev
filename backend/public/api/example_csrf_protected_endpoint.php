<?php

declare(strict_types=1);


require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

use Security\CsrfMiddleware;
use Utils\JWTMiddleware;

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Solo permitir mÃ©todos POST/PUT/DELETE para operaciones que modifican estado
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'MÃ©todo no permitido']);
  exit;
}

try {
  // 1. Verificar autenticaciÃ³n JWT
  $payload = // 2. Verificar protecciÃ³n CSRF para operaciones de escritura
  // Tu lÃ³gica de endpoint aquÃ­
  // Ejemplo: Actualizar perfil de usuario

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar datos del POST
    $input = json_decode(file_get_contents('php://input'), true);

    // Validar datos requeridos
    if (!isset($input['nombre'])) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Nombre es requerido']);
      exit;
    }

    // SimulaciÃ³n de actualizaciÃ³n
    $resultado = [
      'id' => $payload['user_id'],
      'nombre' => htmlspecialchars($input['nombre']),
      'email' => $payload['email'],
      'actualizado_en' => date('Y-m-d H:i:s')
    ];

    http_response_code(200);
    echo json_encode([
      'success' => true,
      'data' => $resultado,
      'message' => 'Perfil actualizado correctamente'
    ]);
  }
} catch (Exception $e) {
  error_log("Error in example_csrf_protected_endpoint.php: " . $e->getMessage());

  if ($e->getMessage() === 'Token CSRF invÃ¡lido o expirado') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invÃ¡lido']);
  } else if ($e->getMessage() === 'Acceso no autorizado') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
  } else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
  }
}

