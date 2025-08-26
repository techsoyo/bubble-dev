<?php


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

// cookie HttpOnly obligatoria

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
declare(strict_types=1);

use Utils\Cors;

if (class_exists('Utils\\Cors')) {
    Cors::enforce(['GET', 'OPTIONS']);
}

$provider = $_ENV['AI_PROVIDER'] ?? getenv('AI_PROVIDER') ?? 'openai';
$model = $_ENV['OPENAI_MODEL'] ?? getenv('OPENAI_MODEL') ?? '';
$baseUrl = $_ENV['OPENAI_BASE_URL'] ?? getenv('OPENAI_BASE_URL') ?? '';
$apiKeySet = !empty($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY'));

$azure = [
  'base_url' => $_ENV['AZURE_OPENAI_BASE_URL'] ?? getenv('AZURE_OPENAI_BASE_URL') ?? '',
  'deployment' => $_ENV['AZURE_OPENAI_DEPLOYMENT'] ?? getenv('AZURE_OPENAI_DEPLOYMENT') ?? '',
  'api_version' => $_ENV['AZURE_OPENAI_API_VERSION'] ?? getenv('AZURE_OPENAI_API_VERSION') ?? ''
];

$status = [
  'provider' => $provider,
  'configured' => false,
  'details' => [
    'model' => $model,
    'base_url' => $provider === 'azure' ? $azure['base_url'] : $baseUrl,
    'has_api_key' => $apiKeySet,
  ]
];

switch ($provider) {
    case 'azure':
        $status['configured'] = $azure['base_url'] !== '' && $azure['deployment'] !== '' && $apiKeySet;
        $status['details']['deployment'] = $azure['deployment'];
        $status['details']['api_version'] = $azure['api_version'];
        break;
    case 'local':
        $status['configured'] = $baseUrl !== '' && $apiKeySet; // algunos servidores locales aceptan una clave cualquiera
        break;
    case 'openai':
    default:
        $status['configured'] = $apiKeySet;
        break;
}

jsonResponse(200, [
  'success' => true,
  'data' => $status
]);


