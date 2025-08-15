<?php

declare(strict_types=1);

// Sube 3 niveles: cv → api → public → backend/
$ROOT = dirname(__DIR__, 3);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
    http_response_code(500);
    exit('Bootstrap no encontrado');
}
require_once $BOOT;

// Log "env_check" temporal (diagnóstico)
if (class_exists('Utils\\Log')) {
    \Utils\Log::json('debug', [
        'req_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
        'step'   => 'env_check',
        'env'    => [
            'BASE_URL'    => getenv('OPENAI_BASE_URL') ?: null,
            'MODEL'       => getenv('OPENAI_MODEL') ?: null,
            'API_KEY_SET' => (bool) getenv('OPENAI_API_KEY'),
        ],
    ]);
}
/**
 * Endpoint: POST /api/cv/parse
 * Objetivo: Parsear un CV PDF y devolver datos normalizados o error controlado.
 *
 * Requisitos clave:
 * - multipart/form-data con campo 'file'
 * - Solo PDF (MIME application/pdf, extensión .pdf)
 * - Tamaño < 5MB
 * - Extrae texto -> procesa con IA -> normaliza con CvSchema
 * - Respuesta éxito: 200 { success:true, data:<json>, meta:{ mode:"ai" } }
 * - Errores:
 *   400 parámetros/mIME/tamaño inválido
 *   422 fallo de parseo (sin texto o IA no produce JSON válido)
 *   500 excepciones inesperadas
 */

// CORS & Método
$isCli = (php_sapi_name() === 'cli');

use Utils\Cors;
use Utils\Log;
use Utils\RateLimiter;
use Utils\RequestId;

$__cv_parse_start = microtime(true);
$__cv_parse_sub = ['upload_ms' => 0, 'extract_ms' => 0, 'openai_ms' => 0];
if (!$isCli) {
    if (class_exists('Utils\\Cors')) {
        Cors::enforce(['POST', 'OPTIONS']);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    if ($method !== 'POST') {
        jsonResponse(405, ['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Método no permitido']]);
    }
    if (class_exists('Utils\\RateLimiter')) {
        RateLimiter::enforceForRoute('/api/cv/parse');
    }
}

// Autoload & dependencias
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
// Inicializar RequestId tras autoload
if (class_exists('Utils\\RequestId')) {
    RequestId::init();
}
// Fallback manual si el autoload PSR-4 aún no mapea Domain
if (!class_exists('Domain\\CvSchema')) {
    $cvSchemaPath = __DIR__ . '/../../../src/Domain/CvSchema.php';
    if (file_exists($cvSchemaPath)) {
        require_once $cvSchemaPath;
    }
}

use Domain\CvSchema; // si no existe, atraparemos más abajo
use Services\Exceptions\AiUnavailableException;

if (!class_exists('Utils\\Log')) {
    $logPath = __DIR__ . '/../../../src/Utils/Log.php';
    if (file_exists($logPath)) {
        require_once $logPath;
    }
}

// Carga del servicio Mistral
$mistralPath = __DIR__ . '/../../../src/Services/MistralService.php';
if (file_exists($mistralPath)) {
    require_once $mistralPath;
}

// Carga del servicio PdfText
$pdfTextPath = __DIR__ . '/../../../src/Services/PdfTextService.php';
if (file_exists($pdfTextPath)) {
    require_once $pdfTextPath;
}

// Carga de excepciones
$aiExceptionPath = __DIR__ . '/../../../src/Services/Exceptions/AiUnavailableException.php';
if (file_exists($aiExceptionPath)) {
    require_once $aiExceptionPath;
}

use Services\InfectedFileException;
use Services\MistralService;
use Services\PdfSecurityException;
use Services\PdfTextEmptyException;
use Services\PdfTextService;

// Helpers de respuesta estandarizada
function logCvParse(string $tag, array $data = []): void
{
    if (class_exists('Utils\\Log')) {
        $level = $tag === 'OK' ? 'info' : ($tag === 'FATAL' ? 'error' : 'warn');
        // Mapear a outcome/error_code
        $base = ['event' => 'cv_parse', 'tag' => $tag];
        if (isset($data['code'])) {
            $base['error_code'] = $data['code'];
        }
        if (!isset($data['outcome'])) {
            $base['outcome'] = $tag === 'OK' ? 'ok' : 'error';
        }
        Log::json($level, $base + $data);
    } else {
        error_log('[CV_PARSE_' . $tag . '] ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function respond(int $status, string $code, string $message, array $details = [])
{
    $durMs = (int)round((microtime(true) - $GLOBALS['__cv_parse_start']) * 1000);
    // Log ÚNICO de error al responder
    logCvParse('ERROR', [
        'code' => $code,
        'duration_ms' => $durMs,
        'subtimings_ms' => $GLOBALS['__cv_parse_sub'],
        'details_keys' => array_keys($details)
    ]);
    jsonResponse($status, [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'details' => empty($details) ? (object)[] : $details
        ]
    ]);
}
function parseFailed(array $details = [])
{
    respond(422, 'PARSE_FAILED', 'No se pudo procesar el CV', $details);
}

try {
    // Si política fuerza modo manual, responder directamente (front evitará incluso llamar si variable también set en frontend)
    $manualOnly = getenv('CV_ALLOW_MANUAL_ONLY') === 'true' || ($_ENV['CV_ALLOW_MANUAL_ONLY'] ?? '') === 'true';
    if ($manualOnly) {
        respond(422, 'PARSE_FAILED', 'Parseo IA deshabilitado por política (CV_ALLOW_MANUAL_ONLY)');
    }
    // Validar campo file
    if (!isset($_FILES['file'])) {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'MISSING_FILE', 'message' => 'Archivo requerido (campo file)']]);
    }
    $file = $_FILES['file'];

    // Errores de subida
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'Error en la subida del archivo']]);
    }

    // Tamaño configurable desde .env
    $maxBytes = (int)(getenv('CV_MAX_UPLOAD_BYTES') ?: 5242880); // 5MB por defecto
    if ($file['size'] > $maxBytes) {
        jsonResponse(413, ['success' => false, 'error' => ['code' => 'MAX_SIZE_EXCEEDED', 'message' => 'Tamaño máximo ' . number_format($maxBytes / 1048576, 1) . 'MB']]);
    }

    // Comprobar extensión .pdf
    $originalName = $file['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_EXTENSION', 'message' => 'Formato no permitido (solo PDF)']]);
    }

    // Detectar MIME real con finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf') {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_MIME', 'message' => 'MIME no permitido (application/pdf requerido)']]);
    }

    $t0 = microtime(true);

    // Generar UUID seguro para el archivo
    $uuid = bin2hex(random_bytes(16));
    $storageDir = BASE_PATH . '/storage/private/cv';
    $safePath = $storageDir . '/' . $uuid . '.pdf';

    // Crear directorio si no existe
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0750, true);
    }

    // Mover archivo a storage seguro
    if (!move_uploaded_file($file['tmp_name'], $safePath)) {
        jsonResponse(422, ['success' => false, 'error' => ['code' => 'STORAGE_FAILED', 'message' => 'No se pudo almacenar el archivo']]);
    }

    // Registrar cleanup automático
    register_shutdown_function(static function () use ($safePath) {
        if (is_file($safePath)) {
            @unlink($safePath);
        }
    });

    // Instanciar servicios
    try {
        $mistral = new MistralService();
        $pdfTextService = new PdfTextService();
    } catch (\Throwable $e) {
        // No exponemos detalles de API key
        jsonResponse(422, ['success' => false, 'error' => ['code' => 'SERVICE_UNAVAILABLE', 'message' => 'Servicio de IA no disponible']]);
    }
    $tUploadEnd = microtime(true);
    $__cv_parse_sub['upload_ms'] = (int)round(($tUploadEnd - $t0) * 1000);

    // Extraer texto usando PdfTextService robusto
    $tExtract0 = microtime(true);
    try {
        $extractedText = $pdfTextService->extract($safePath);
    } catch (InfectedFileException $e) {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'INFECTED_FILE', 'message' => 'Archivo infectado detectado']]);
    } catch (PdfSecurityException $e) {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_PDF', 'message' => 'Archivo PDF inválido o corrupto']]);
    } catch (PdfTextEmptyException $e) {
        jsonResponse(422, ['success' => false, 'error' => ['code' => 'EMPTY_TEXT', 'message' => 'No se pudo extraer texto del PDF']]);
    } catch (\Throwable $e) {
        jsonResponse(422, ['success' => false, 'error' => ['code' => 'EXTRACTION_FAILED', 'message' => 'Error durante extracción de texto']]);
    }
    $__cv_parse_sub['extract_ms'] = (int)round((microtime(true) - $tExtract0) * 1000);

    // Si no hay texto suficiente => fallback manual
    if (!$extractedText || mb_strlen(trim($extractedText)) < 30) {
        $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
        logCvParse('NO_TEXT', ['duration_ms' => $durMs, 'subtimings_ms' => $__cv_parse_sub]);

        // Fallback manual con template vacío
        $normalized = CvSchema::normalize(CvSchema::TEMPLATE);
        jsonResponse(200, [
            'success' => true,
            'data' => $normalized,
            'meta' => [
                'mode' => 'manual',
                'reason' => 'NO_TEXT_EXTRACTED',
                'duration_ms' => $durMs,
                'subtimings_ms' => $__cv_parse_sub
            ]
        ]);
    }

    // Analizar con IA (con reintentos automáticos incorporados)
    $tAi0 = microtime(true);
    try {
        $aiData = $mistral->analyzeCvFromText($extractedText);
        $isFallback = false;
    } catch (AiUnavailableException $e) {
        // La IA falló - usar fallback manual exitoso
        $__cv_parse_sub['openai_ms'] = (int)round((microtime(true) - $tAi0) * 1000);

        $errorCode = $e->getMessage();
        logCvParse('AI_FAILED_FALLBACK', [
            'error_code' => $errorCode,
            'extracted_chars' => mb_strlen($extractedText),
            'duration_ms' => $__cv_parse_sub['openai_ms']
        ]);

        // Fallback manual pero exitoso - retorna 200
        $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
        $normalized = CvSchema::normalize(CvSchema::TEMPLATE);

        jsonResponse(200, [
            'success' => true,
            'data' => $normalized,
            'meta' => [
                'mode' => 'manual',
                'reason' => 'IA_UNAVAILABLE',
                'duration_ms' => $durMs,
                'subtimings_ms' => $__cv_parse_sub
            ]
        ]);
    } catch (\Throwable $e) {
        // Error inesperado - también fallback manual exitoso
        $__cv_parse_sub['openai_ms'] = (int)round((microtime(true) - $tAi0) * 1000);

        logCvParse('AI_ERROR_FALLBACK', [
            'error_class' => get_class($e),
            'error_message' => $e->getMessage(),
            'duration_ms' => $__cv_parse_sub['openai_ms']
        ]);

        $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
        $normalized = CvSchema::normalize(CvSchema::TEMPLATE);

        jsonResponse(200, [
            'success' => true,
            'data' => $normalized,
            'meta' => [
                'mode' => 'manual',
                'reason' => 'IA_ERROR',
                'duration_ms' => $durMs,
                'subtimings_ms' => $__cv_parse_sub
            ]
        ]);
    }

    $__cv_parse_sub['openai_ms'] = (int)round((microtime(true) - $tAi0) * 1000);

    // Normalizar contra nuestro contrato
    if (!class_exists('Domain\\CvSchema')) {
        jsonResponse(422, ['success' => false, 'error' => ['code' => 'SCHEMA_UNAVAILABLE', 'message' => 'Esquema no disponible']]);
    }

    $normalized = CvSchema::normalize($aiData);

    // Eliminamos archivo temporal
    if (file_exists($safePath)) {
        @unlink($safePath);
    }

    $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
    logCvParse('OK', [
        'duration_ms' => $durMs,
        'subtimings_ms' => $__cv_parse_sub,
        'extracted_chars' => mb_strlen($extractedText),
        'fields_present' => count($normalized),
        'mode' => 'ai'
    ]);

    jsonResponse(200, [
        'success' => true,
        'data' => $normalized,
        'meta' => [
            'mode' => 'ai',
            'duration_ms' => $durMs,
            'subtimings_ms' => $__cv_parse_sub
        ]
    ]);
    exit;
} catch (\Throwable $e) {
    $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
    logCvParse('FATAL', ['code' => 'PARSE_FAILED', 'duration_ms' => $durMs, 'subtimings_ms' => $__cv_parse_sub]);
    jsonResponse(422, ['success' => false, 'error' => ['code' => 'PARSE_FAILED', 'message' => 'Fallo inesperado procesando CV']]);
}
