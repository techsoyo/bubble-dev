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

$ocrEnabled = (($_ENV['CV_ENABLE_OCR'] ?? getenv('CV_ENABLE_OCR') ?? 'true') === 'true');
$bins = [
  'pdftotext' => $_ENV['PDFTOTEXT_BIN'] ?? getenv('PDFTOTEXT_BIN') ?? 'pdftotext',
  'pdftoppm'  => $_ENV['PDFTOPPM_BIN'] ?? getenv('PDFTOPPM_BIN') ?? 'pdftoppm',
  'tesseract' => $_ENV['TESSERACT_BIN'] ?? getenv('TESSERACT_BIN') ?? 'tesseract'
];

function resolveBin(string $bin): array
{
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    if ($isWin) {
        $cmd = 'where ' . escapeshellarg($bin) . ' 2> NUL';
    } else {
        $cmd = 'command -v ' . escapeshellarg($bin) . ' 2>/dev/null';
    }
    $out = [];
    @exec($cmd, $out, $code);
    if ($code === 0 && !empty($out[0])) {
        return ['found' => true, 'path' => trim($out[0])];
    }
    return ['found' => false, 'path' => ''];
}

$resolved = [
  'pdftotext' => resolveBin($bins['pdftotext']),
  'pdftoppm'  => resolveBin($bins['pdftoppm']),
  'tesseract' => resolveBin($bins['tesseract'])
];

jsonResponse(200, [
  'success' => true,
  'data' => [
    'ocr_enabled' => $ocrEnabled,
    'bins' => $resolved,
    'langs' => $_ENV['CV_OCR_LANGS'] ?? getenv('CV_OCR_LANGS') ?? 'eng'
  ]
]);


