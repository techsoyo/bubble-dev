<?php

declare(strict_types=1);



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

// ====================================================================
// ENDPOINT: POST /api/cv/parse.php
// Flujo: subir PDF -> validar -> guardar -> extraer texto (smalot/pdfparser)
//       -> llamar a Groq (OpenAI-compatible) -> normalizar -> responder
// ====================================================================

// POST/PUT/PATCH/DELETE

// En prod, no aceptar Authorization
if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

use Utils\Cors;
use Utils\RateLimiter;
use Utils\RequestId;
use Utils\Log;
use Domain\CvSchema;

// Autoload (por si no lo cargÃ³ bootstrap)
$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// ===== MÃ©tricas
$__cv_parse_start = microtime(true);
$__cv_parse_sub   = ['upload_ms' => 0, 'extract_ms' => 0, 'ai_ms' => 0];

// ===== Helpers
function logCvParse(string $tag, array $data = []): void
{
    if (class_exists('Utils\\Log')) {
        $level = $tag === 'SUCCESS' ? 'info' : ($tag === 'FATAL' ? 'error' : 'warn');
        Log::json($level, ['event' => 'cv_parse', 'tag' => $tag] + $data);
    } else {
        error_log('[CV_PARSE_' . $tag . '] ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
function respondError(int $status, string $code, string $message, array $details = []): void
{
    $durMs = (int) round((microtime(true) - $GLOBALS['__cv_parse_start']) * 1000);
    logCvParse('ERROR', [
        'code'          => $code,
        'duration_ms'   => $durMs,
        'subtimings_ms' => $GLOBALS['__cv_parse_sub'],
        'details_keys'  => array_keys($details),
    ]);
    jsonResponse($status, [
        'success' => false,
        'error' => [
            'code'    => $code,
            'message' => $message,
            'details' => empty($details) ? (object)[] : $details,
        ],
    ]);
}

// ===== CORS / MÃ©todo / Rate limiting
if (PHP_SAPI !== 'cli') {
    if (class_exists(Cors::class)) {
        Cors::enforce(['POST', 'OPTIONS']);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    if ($method !== 'POST') {
        respondError(405, 'METHOD_NOT_ALLOWED', 'MÃ©todo no permitido');
    }
    if (class_exists(RateLimiter::class)) {
        RateLimiter::enforceForRoute('/api/cv/parse');
    }
}
if (class_exists(RequestId::class)) {
    RequestId::init();
}

// ===== PolÃ­tica â€œsolo manualâ€
$manualOnly = getenv('CV_ALLOW_MANUAL_ONLY') === 'true' || (($_ENV['CV_ALLOW_MANUAL_ONLY'] ?? '') === 'true');
if ($manualOnly) {
    $normalized = class_exists(CvSchema::class) ? CvSchema::normalize(CvSchema::TEMPLATE) : [];
    $durMs = (int) round((microtime(true) - $__cv_parse_start) * 1000);
    logCvParse('MANUAL_ONLY', ['duration_ms' => $durMs, 'subtimings_ms' => $__cv_parse_sub]);
    jsonResponse(200, [
        'success' => true,
        'data'    => $normalized,
        'meta'    => [
            'mode'           => 'manual',
            'reason'         => 'CV_ALLOW_MANUAL_ONLY',
            'duration_ms'    => $durMs,
            'subtimings_ms'  => $__cv_parse_sub,
        ],
    ]);
}

// ===== ValidaciÃ³n subida
if (!isset($_FILES['file'])) {
    respondError(400, 'MISSING_FILE', "Archivo requerido (campo 'file')");
}
$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respondError(400, 'UPLOAD_ERROR', 'Error en la subida del archivo');
}
$maxBytes = (int) (getenv('CV_MAX_UPLOAD_BYTES') ?: 5242880); // 5MB
if (($file['size'] ?? 0) > $maxBytes) {
    respondError(413, 'MAX_SIZE_EXCEEDED', 'TamaÃ±o mÃ¡ximo ' . number_format($maxBytes / 1048576, 1) . 'MB');
}
$originalName = (string) ($file['name'] ?? '');
$extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($extension !== 'pdf') {
    respondError(400, 'INVALID_EXTENSION', 'Formato no permitido (solo PDF)');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if ($mime !== 'application/pdf') {
    respondError(400, 'INVALID_MIME', 'MIME no permitido (application/pdf requerido)');
}

// ===== Guardar PDF
$t0 = microtime(true);
$uuid       = bin2hex(random_bytes(16));
$storageDir = BASE_PATH . '/storage/private/cv';
$safePath   = $storageDir . '/' . $uuid . '.pdf';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0750, true);
}
if (!move_uploaded_file($file['tmp_name'], $safePath)) {
    respondError(422, 'STORAGE_FAILED', 'No se pudo almacenar el archivo');
}
register_shutdown_function(static function () use ($safePath) {
    if (is_file($safePath)) {
        @unlink($safePath);
    }
});
$__cv_parse_sub['upload_ms'] = (int) round((microtime(true) - $t0) * 1000);

// ===== Extraer TEXTO del PDF (smalot/pdfparser)
$tExt = microtime(true);
try {
    if (!class_exists(\Smalot\PdfParser\Parser::class)) {
        throw new RuntimeException('Falta smalot/pdfparser. Ejecuta: composer require smalot/pdfparser');
    }
    // si tu PDF puede ser grande, puedes aumentar memoria temporal:
    // @ini_set('memory_limit','512M');

    $parser = new \Smalot\PdfParser\Parser();
    $pdfDoc = $parser->parseFile($safePath);
    $extractedText = trim((string) $pdfDoc->getText());

    // limpieza bÃ¡sica
    $extractedText = preg_replace('/[^\PC\s]/u', ' ', $extractedText) ?? $extractedText;
    $extractedText = preg_replace('/[ \t]{2,}/', ' ', $extractedText) ?? $extractedText;
} catch (\Throwable $e) {
    respondError(422, 'PDF_TEXT_EXTRACT_FAILED', 'No se pudo extraer texto del PDF', ['message' => $e->getMessage()]);
}
$__cv_parse_sub['extract_ms'] = (int) round((microtime(true) - $tExt) * 1000);

if (mb_strlen($extractedText) < 50) {
    // Muy poco texto -> modo manual
    $normalized = class_exists(CvSchema::class) ? CvSchema::normalize(CvSchema::TEMPLATE) : [];
    $durMs = (int) round((microtime(true) - $__cv_parse_start) * 1000);
    logCvParse('PDF_TEXT_TOO_SHORT', ['duration_ms' => $durMs, 'subtimings_ms' => $__cv_parse_sub, 'extracted_chars' => mb_strlen($extractedText)]);
    jsonResponse(200, [
        'success' => true,
        'data'    => $normalized,
        'meta'    => [
            'mode'           => 'manual',
            'reason'         => 'PDF_TEXT_TOO_SHORT',
            'duration_ms'    => $durMs,
            'subtimings_ms'  => $__cv_parse_sub,
        ],
    ]);
}

// ===== LLM (Groq; API OpenAI-compatible)
$apiKey   = getenv('GROQ_API_KEY') ?: '';
$base     = getenv('GROQ_BASE_URL') ?: 'https://api.groq.com';
$model    = getenv('GROQ_MODEL') ?: 'llama3-8b-8192';
$timeout  = (int) (getenv('GROQ_TIMEOUT_SECONDS') ?: 60);
$retries  = max(1, (int) (getenv('AI_MAX_RETRIES') ?: 1));

if (!$apiKey) {
    respondError(500, 'LLM_CONFIG_MISSING', 'GROQ_API_KEY no configurada');
}

// Normaliza la URL para apuntar a /openai/v1/chat/completions
$baseNorm = rtrim($base, '/');
if (!preg_match('~/openai/v1$~', $baseNorm)) {
    $baseNorm .= '/openai/v1';
}
$url = $baseNorm . '/chat/completions';

$system = <<<PROMPT
Eres un extractor de CV. Devuelve SOLO JSON vÃ¡lido con la estructura:
{
  "name": string,
  "email": string,
  "phone": string,
  "skills": string[],
  "experience": [{ "company": string, "role": string, "start": string, "end": string }],
  "education":  [{ "institution": string, "degree": string, "year": string }]
}
Nada fuera del JSON.
PROMPT;

$payload = json_encode([
    'model'       => $model,
    'temperature' => 0.2,
    'messages'    => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $extractedText],
    ],
    // 'response_format' => ['type' => 'json_object'], // ActÃ­valo si tu endpoint lo soporta
], JSON_UNESCAPED_UNICODE);

$tAi = microtime(true);

// â€”â€”â€” Reintentos con timeout â€”â€”â€”
$raw = false;
$lastErr = null;
$lastHttp = 0;
for ($i = 1; $i <= $retries; $i++) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $raw      = curl_exec($ch);
    $lastErr  = curl_error($ch);
    $lastHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Salimos si respondiÃ³ bien (cÃ³digo < 500 y sin error de cURL)
    if ($raw !== false && $lastHttp > 0 && $lastHttp < 500) {
        break;
    }
    // breve espera entre intentos
    usleep(200000);
}
$__cv_parse_sub['ai_ms'] = (int) round((microtime(true) - $tAi) * 1000);

// Fallback a modo manual si Groq no responde
if ($raw === false || !$lastHttp) {
    $normalized = class_exists(\Domain\CvSchema::class)
        ? \Domain\CvSchema::normalize(\Domain\CvSchema::TEMPLATE)
        : [];
    $durMs = (int) round((microtime(true) - $__cv_parse_start) * 1000);
    logCvParse('LLM_UNAVAILABLE', [
        'duration_ms' => $durMs,
        'subtimings_ms' => $__cv_parse_sub,
        'curl_error' => $lastErr,
        'http_code' => $lastHttp,
    ]);
    jsonResponse(200, [
        'success' => true,
        'data'    => $normalized,
        'meta'    => [
            'mode'           => 'manual',
            'reason'         => 'LLM_UNAVAILABLE',
            'duration_ms'    => $durMs,
            'subtimings_ms'  => $__cv_parse_sub,
            'provider'       => 'groq',
        ],
    ]);
}

// â€”â€”â€” Parseo de respuesta Groq â€”â€”â€”
$resp = json_decode($raw, true);
if (!is_array($resp) || $lastHttp >= 400) {
    respondError(422, 'UPSTREAM_ERROR', 'Error del proveedor LLM', ['status' => $lastHttp, 'body' => $resp]);
}

$content = $resp['choices'][0]['message']['content'] ?? '';
$parsed  = json_decode($content, true);
if ($parsed === null) {
    // Intenta extraer el primer bloque JSON si vino texto alrededor
    if (preg_match('/\{(?:[^{}]*|(?R))*\}/s', $content, $m)) {
        $parsed = json_decode($m[0], true);
    }
}
if (!is_array($parsed)) {
    respondError(422, 'INVALID_LLM_JSON', 'El LLM no devolviÃ³ JSON vÃ¡lido', ['raw' => $content]);
}

