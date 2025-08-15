<?php

declare(strict_types=1);

$ROOT = dirname(__DIR__);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Bootstrap no encontrado']);
  exit;
}
require_once $BOOT;

// Content Type header (CORS ya configurado en bootstrap.php)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Solo permitir método GET para endpoints públicos
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Método no permitido - solo GET']);
  exit;
}

// Manejar GET request
echo json_encode([
  'success' => true,
  'message' => 'Endpoint de cambio de contraseña está funcionando',
  'method' => $_SERVER['REQUEST_METHOD'],
  'timestamp' => date('Y-m-d H:i:s')
]);
exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);

  echo json_encode([
    'success' => true,
    'message' => 'POST recibido correctamente',
    'received_data' => $input ?: 'No data',
    'timestamp' => date('Y-m-d H:i:s')
  ]);
  exit;
}

echo json_encode([
  'success' => false,
  'message' => 'Método no permitido',
  'method' => $_SERVER['REQUEST_METHOD']
]);
