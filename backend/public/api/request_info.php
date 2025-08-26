<?php declare(strict_types=1);
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

// Recoger toda la informaciÃƒÆ’Ã‚Â³n de la solicitud
$headers = getallheaders();
$headersList = [];
foreach ($headers as $name => $value) {
    $headersList[$name] = $value;
}

$result = [
  'timestamp' => date('Y-m-d H:i:s'),
  'success' => true,
  'message' => 'InformaciÃƒÆ’Ã‚Â³n de la solicitud recibida',
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



