<?php

declare(strict_types=1);
$ROOT = dirname(__DIR__);             // api -> backend/
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Recoger toda la información de la solicitud
$headers = getallheaders();
$headersList = [];
foreach ($headers as $name => $value) {
    $headersList[$name] = $value;
}

$result = [
  'timestamp' => date('Y-m-d H:i:s'),
  'success' => true,
  'message' => 'Información de la solicitud recibida',
  'request_info' => [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
    'http_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Not provided',
    'headers' => $headersList,
    'get_params' => $_GET,
    'server' => [
      'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
      'php_version' => phpversion()
    ]
  ]
];

echo json_encode($result, JSON_PRETTY_PRINT);
