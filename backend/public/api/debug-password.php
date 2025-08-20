<?php

declare(strict_types=1);

$ROOT = dirname(dirname(dirname(__DIR__))); // Corregido: api -> public -> backend -> raiz
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

// Debug info
$debug = [
  'method' => $_SERVER['REQUEST_METHOD'],
  'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
  'raw_input' => file_get_contents('php://input'),
  'timestamp' => date('Y-m-d H:i:s')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode($debug['raw_input'], true);
  $debug['parsed_json'] = $input;
  $debug['json_error'] = json_last_error_msg();

  if ($input && isset($input['candidate_id'])) {
    // Bootstrap ya cargado al inicio del archivo
    try {
      $db = getDBConnection();
      $stmt = $db->prepare("SELECT id, first_name, last_name FROM bt_candidates WHERE id = ?");
      $stmt->execute([$input['candidate_id']]);
      $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

      $debug['candidate_found'] = $candidate ? true : false;
      $debug['candidate_info'] = $candidate ?: 'not found';

      if ($candidate) {
        echo json_encode([
          'success' => true,
          'message' => 'Datos recibidos correctamente',
          'candidate' => $candidate,
          'debug' => $debug
        ]);
      } else {
        echo json_encode([
          'success' => false,
          'message' => 'Candidato no encontrado',
          'debug' => $debug
        ]);
      }
    } catch (Exception $e) {
      echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'debug' => $debug
      ]);
    }
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Datos incompletos',
      'debug' => $debug
    ]);
  }
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Método no permitido',
    'debug' => $debug
  ]);
}

