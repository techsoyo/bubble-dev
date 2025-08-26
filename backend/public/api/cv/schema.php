<?php declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
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

// Sube 3 niveles: cv -> api -> public -> backend/

use Domain\CvSchema;
use Utils\Cors;

// Asegurar autoload manual si fuera necesario
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
if (!class_exists('Domain\\CvSchema')) {
    $cvSchemaPath = __DIR__ . '/../../../src/Domain/CvSchema.php';
    if (file_exists($cvSchemaPath)) {
        require_once $cvSchemaPath;
    }
}

if (class_exists('Utils\\Cors')) {
    Cors::enforce(['GET', 'POST', 'OPTIONS']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!class_exists('Domain\\CvSchema')) {
    jsonResponse(500, ['success' => false, 'error' => ['code' => 'SCHEMA_UNAVAILABLE', 'message' => 'Esquema no disponible']]);
}

switch ($method) {
    case 'GET':
        jsonResponse(200, [
          'success' => true,
          'data' => CvSchema::TEMPLATE,
          'meta' => ['source' => 'template']
        ]);
        break;
    case 'POST':
        $raw = file_get_contents('php://input');
        $in = json_decode($raw, true);
        if (!is_array($in)) {
            jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_JSON', 'message' => 'JSON invÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lido']]);
        }
        $normalized = CvSchema::normalize($in);
        $errors = CvSchema::validate($normalized);
        if (!empty($errors)) {
            jsonResponse(422, [
              'success' => false,
              'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'Violaciones de validaciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n',
                'details' => $errors
              ],
              'data' => $normalized
            ]);
        }
        jsonResponse(200, [
          'success' => true,
          'data' => $normalized,
          'meta' => ['validated' => true]
        ]);
        break;
    default:
        jsonResponse(405, ['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'MÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©todo no permitido']]);
}

