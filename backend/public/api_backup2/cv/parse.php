<?php

declare(strict_types=1);

// ====================================================================
// ENDPOINT: /api/cv/parse
// FLUJO: PDF upload â†’ ValidaciÃ³n â†’ Storage â†’ Ollama â†’ JSON â†’ Frontend
// ====================================================================

// ðŸ” PASO 1: INICIALIZACIÃ“N - Logs de inicio y carga de bootstrap
error_log("[CV_DEBUG] Iniciando parse.php");
file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] Iniciando parse.php\n", FILE_APPEND);

// Sube 3 niveles: cv â†’ api â†’ public â†’ backend/
require_once $BOOT;

// ðŸŒ PASO 2: VERIFICACIÃ“N DE ENTORNO - Log temporal para diagnosticar configuraciÃ³n
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
 * ====================================================================
 * ENDPOINT: POST /api/cv/parse
 * ====================================================================
 * 
 * FLUJO COMPLETO:
 * 1. Recibe PDF multipart/form-data
 * 2. Valida (tamaÃ±o, MIME, extensiÃ³n)  
 * 3. Almacena en /storage/private/cv/UUID.pdf
 * 4. Procesa con Ollama/Llama3.2
 * 5. Normaliza con CvSchema 
 * 6. Retorna JSON para formulario frontend
 * 7. Cleanup automÃ¡tico del archivo
 * 
 * Objetivo: Parsear un CV PDF y devolver datos normalizados o error controlado.
 *
 * Requisitos clave:
 * - multipart/form-data con campo 'file'
 * - Solo PDF (MIME application/pdf, extensiÃ³n .pdf)
 * - TamaÃ±o < 5MB
 * - Extrae texto -> procesa con IA -> normaliza con CvSchema
 * - Respuesta Ã©xito: 200 { success:true, data:<json>, meta:{ mode:"ai" } }
 * - Errores:
 *   400 parÃ¡metros/mIME/tamaÃ±o invÃ¡lido
 *   422 fallo de parseo (sin texto o IA no produce JSON vÃ¡lido)
 *   500 excepciones inesperadas
 */

// ðŸ›¡ï¸ PASO 3: CONFIGURACIÃ“N DE SEGURIDAD Y CORS
$isCli = (php_sapi_name() === 'cli');

use Utils\Cors;
use Utils\Log;
use Utils\RateLimiter;
use Utils\RequestId;

// â±ï¸ MÃ‰TRICAS: Inicio de tracking de tiempo total y subtimings
$__cv_parse_start = microtime(true);
$__cv_parse_sub = ['upload_ms' => 0, 'extract_ms' => 0, 'openai_ms' => 0];

// ðŸ“Š LOG: Estado inicial del procesamiento
error_log("[CV_DEBUG] Iniciando procesamiento de CV: " . date('Y-m-d H:i:s'));

// ðŸ”’ VALIDACIÃ“N HTTP: Solo POST, aplicar CORS y rate limiting
if (!$isCli) {
    if (class_exists('Utils\\Cors')) {
        Cors::enforce(['POST', 'OPTIONS']);
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    if ($method !== 'POST') {
        jsonResponse(405, ['success' => false, 'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'MÃ©todo no permitido']]);
    }
    if (class_exists('Utils\\RateLimiter')) {
        RateLimiter::enforceForRoute('/api/cv/parse');
    }
}

// ðŸ“¦ PASO 4: CARGA DE DEPENDENCIAS - Autoload y clases necesarias
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
// Inicializar RequestId tras autoload
if (class_exists('Utils\\RequestId')) {
    RequestId::init();
}
// Fallback manual si el autoload PSR-4 aÃºn no mapea Domain
if (!class_exists('Domain\\CvSchema')) {
    $cvSchemaPath = __DIR__ . '/../../../src/Domain/CvSchema.php';
    if (file_exists($cvSchemaPath)) {
        require_once $cvSchemaPath;
    }
}

use Domain\CvSchema; // si no existe, atraparemos mÃ¡s abajo
use Services\Exceptions\AiUnavailableException;

// ðŸ”Œ CARGA MANUAL DE SERVICIOS - En caso de que autoload falle
if (!class_exists('Utils\\Log')) {
    $logPath = __DIR__ . '/../../../src/Utils/Log.php';
    if (file_exists($logPath)) {
        require_once $logPath;
    }
}

// // ðŸ¤– Servicio principal: Ollama para IA
// $ollamaPath = __DIR__ . '/../../../src/Services/OllamaService.php';
// if (file_exists($ollamaPath)) {
//     require_once $ollamaPath;
// }

// ðŸ“„ Servicio PDF: Para validaciÃ³n y extracciÃ³n
$pdfTextPath = __DIR__ . '/../../../src/Services/PdfTextService.php';
if (file_exists($pdfTextPath)) {
    require_once $pdfTextPath;
}

// âš ï¸ Excepciones especÃ­ficas
$aiExceptionPath = __DIR__ . '/../../../src/Services/Exceptions/AiUnavailableException.php';
if (file_exists($aiExceptionPath)) {
    require_once $aiExceptionPath;
}

use Services\InfectedFileException;
// use Services\OllamaService;
use Services\PdfSecurityException;
use Services\PdfTextEmptyException;
use Services\PdfTextService;

// ðŸ› ï¸ FUNCIONES AUXILIARES - Para logging y respuestas estandarizadas
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
    // Log ÃšNICO de error al responder
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

// ðŸš€ PASO 5: INICIO DEL PROCESAMIENTO PRINCIPAL
try {
    file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] MÃ©todo HTTP: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);

    // ðŸ”’ VERIFICACIÃ“N DE POLÃTICA - Si estÃ¡ forzado modo manual, salir inmediatamente
    $manualOnly = getenv('CV_ALLOW_MANUAL_ONLY') === 'true' || ($_ENV['CV_ALLOW_MANUAL_ONLY'] ?? '') === 'true';
    if ($manualOnly) {
        respond(422, 'PARSE_FAILED', 'Parseo IA deshabilitado por polÃ­tica (CV_ALLOW_MANUAL_ONLY)');
    }

    // ðŸ“ PASO 6: VALIDACIÃ“N DEL ARCHIVO SUBIDO
    file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] Verificando archivo... _FILES existe: " . (isset($_FILES) ? 'SÃ' : 'NO') . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] Campo 'file' existe: " . (isset($_FILES['file']) ? 'SÃ' : 'NO') . "\n", FILE_APPEND);

    // ðŸš« VALIDAR: Campo 'file' debe existir en $_FILES
    if (!isset($_FILES['file'])) {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'MISSING_FILE', 'message' => 'Archivo requerido (campo file)']]);
    }
    $file = $_FILES['file'];  // â† VARIABLE PRINCIPAL: Datos del archivo subido

    // ðŸ“Š LOG: InformaciÃ³n detallada del archivo recibido
    error_log("[CV_DEBUG] Archivo recibido: " . json_encode([
        'name' => $file['name'] ?? 'N/A',
        'size' => $file['size'] ?? 0,
        'type' => $file['type'] ?? 'N/A',
        'error' => $file['error'] ?? 'N/A'
    ]));

    // ðŸš« VALIDAR: Errores de subida de PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'UPLOAD_ERROR', 'message' => 'Error en la subida del archivo']]);
    }

    // ðŸ“ VALIDAR: TamaÃ±o mÃ¡ximo configurable desde .env
    $maxBytes = (int)(getenv('CV_MAX_UPLOAD_BYTES') ?: 5242880); // 5MB por defecto
    if ($file['size'] > $maxBytes) {
        jsonResponse(413, ['success' => false, 'error' => ['code' => 'MAX_SIZE_EXCEEDED', 'message' => 'TamaÃ±o mÃ¡ximo ' . number_format($maxBytes / 1048576, 1) . 'MB']]);
    }

    // ðŸ·ï¸ VALIDAR: ExtensiÃ³n debe ser .pdf
    $originalName = $file['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_EXTENSION', 'message' => 'Formato no permitido (solo PDF)']]);
    }

    // ðŸ” VALIDAR: MIME type debe ser application/pdf (verificaciÃ³n real del contenido)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf') {
        jsonResponse(400, ['success' => false, 'error' => ['code' => 'INVALID_MIME', 'message' => 'MIME no permitido (application/pdf requerido)']]);
    }

    // â±ï¸ INICIO: Tracking de tiempo de almacenamiento
    $t0 = microtime(true);

    // ðŸ” PASO 7: ALMACENAMIENTO SEGURO DEL PDF
    // Generar UUID Ãºnico para evitar colisiones de nombres
    $uuid = bin2hex(random_bytes(16));
    $storageDir = BASE_PATH . '/storage/private/cv';
    $safePath = $storageDir . '/' . $uuid . '.pdf';  // â† VARIABLE CLAVE: Path definitivo del PDF

    // ðŸ“ Crear directorio de almacenamiento si no existe
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0750, true);
    }

    // ðŸšš MOVER: De temporal de PHP a almacenamiento permanente seguro
    if (!move_uploaded_file($file['tmp_name'], $safePath)) {
        jsonResponse(422, ['success' => false, 'error' => ['code' => 'STORAGE_FAILED', 'message' => 'No se pudo almacenar el archivo']]);
    }

    // ðŸ§¹ REGISTRO DE LIMPIEZA: El PDF se eliminarÃ¡ automÃ¡ticamente al final del script
    register_shutdown_function(static function () use ($safePath) {
        if (is_file($safePath)) {
            @unlink($safePath);
        }
    });

    // ðŸ“Š LOG: PreparaciÃ³n para crear servicios de procesamiento
    // error_log("[CV_DEBUG] Intentando crear OllamaService y PdfTextService...");
    // file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] Intentando crear servicios\n", FILE_APPEND);

    // ðŸ—ï¸ PASO 8: INSTANCIACIÃ“N DE SERVICIOS
    try {
        // ðŸ¤– Servicio principal: Ollama para procesamiento IA
        // file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] Creando OllamaService (MIGRADO)...\n", FILE_APPEND);
        // $ollama = new OllamaService();
        // error_log("[CV_DEBUG] OllamaService (MIGRADO) creado exitosamente");
        // file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] OllamaService (MIGRADO) OK\n", FILE_APPEND);

        // ðŸ“„ Servicio PDF: Para validaciÃ³n y procesamiento
        $pdfTextService = new PdfTextService();
        error_log("[CV_DEBUG] PdfTextService creado exitosamente");
        file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] PdfTextService OK\n", FILE_APPEND);
    } catch (\Throwable $e) {
        // âŒ ERROR: Fallo en la creaciÃ³n de servicios (posible problema de configuraciÃ³n)
        error_log("[CV_DEBUG] Error creando servicios: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
        file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
        // No exponemos detalles internos al cliente
        jsonResponse(422, ['success' => false, 'error' => ['code' => 'SERVICE_UNAVAILABLE', 'message' => 'Servicio de IA no disponible']]);
    }
    // â±ï¸ MÃ‰TRICA: Tiempo total de upload y almacenamiento
    $tUploadEnd = microtime(true);
    $__cv_parse_sub['upload_ms'] = (int)round(($tUploadEnd - $t0) * 1000);

    // ðŸš€ PASO 9: PROCESAMIENTO PRINCIPAL CON IA
    file_put_contents(__DIR__ . '/../../../logs/cv_debug.log', "[" . date('Y-m-d H:i:s') . "] Procesando PDF con Ollama (incluye extracciÃ³n de texto)\n", FILE_APPEND);

    // ðŸ¤– PROCESAMIENTO: Ollama analiza el PDF y extrae datos estructurados
    $tAi0 = microtime(true);
    error_log("[CV_DEBUG] Iniciando anÃ¡lisis con Ollama desde PDF...");

    try {
        // ðŸŽ¯ PUNTO CLAVE: AquÃ­ se pasa el PDF ($safePath) a Ollama para procesamiento
        $aiData = $ollama->analyzeCvFromPdf($safePath);  // â† PROCESAMIENTO PRINCIPAL
        $isFallback = false;
        error_log("[CV_DEBUG] Ollama anÃ¡lisis exitoso desde PDF!");
    } catch (AiUnavailableException $e) {
        // ðŸ”„ FALLBACK: Si Ollama falla, retornar template vacÃ­o para modo manual
        error_log("[CV_DEBUG] Error en Ollama: " . $e->getMessage());

        $normalized = CvSchema::normalize(CvSchema::TEMPLATE);
        $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
        $__cv_parse_sub['ai_fallback_ms'] = (int)round((microtime(true) - $tAi0) * 1000);

        logCvParse('OLLAMA_FAILED', [
            'duration_ms' => $durMs,
            'subtimings_ms' => $__cv_parse_sub,
            'error' => $e->getMessage()
        ]);

        // âœ… RESPUESTA EXITOSA: Modo manual como fallback
        jsonResponse(200, [
            'success' => true,
            'data' => $normalized,
            'meta' => [
                'mode' => 'manual',
                'reason' => 'OLLAMA_PROCESSING_FAILED',
                'error' => $e->getMessage(),
                'duration_ms' => $durMs,
                'subtimings_ms' => $__cv_parse_sub
            ]
        ]);
    }

    // â±ï¸ MÃ‰TRICA: Tiempo total de procesamiento IA
    $tAiEnd = microtime(true);
    $__cv_parse_sub['ai_ms'] = (int)round(($tAiEnd - $tAi0) * 1000);

    error_log("[CV_DEBUG] Ollama procesamiento exitoso - Tiempo: " . $__cv_parse_sub['ai_ms'] . "ms");

    // ðŸ”„ PASO 10: NORMALIZACIÃ“N DE DATOS EXTRAÃDOS
    // Convertir datos de IA al formato esperado por el frontend
    $normalized = CvSchema::normalize($aiData);
    $isFallback = false;

    // ðŸ“Š LOG: Ã‰xito completo del procesamiento
    $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
    logCvParse('SUCCESS', ['duration_ms' => $durMs, 'subtimings_ms' => $__cv_parse_sub]);

    // âœ… RESPUESTA FINAL: Datos estructurados listos para el formulario del frontend
    jsonResponse(200, [
        'success' => true,
        'data' => $normalized,        // â† DATOS ESTRUCTURADOS PARA EL FRONTEND
        'meta' => [
            'mode' => 'ai',           // Indica que fue procesado por IA
            'provider' => 'ollama',   // Servicio usado
            'duration_ms' => $durMs,
            'subtimings_ms' => $__cv_parse_sub
        ]
    ]);

    // âš ï¸ CÃ“DIGO LEGACY - AnÃ¡lisis desde texto extraÃ­do (puede ser eliminado)
    // Este bloque es cÃ³digo heredado del flujo anterior con extracciÃ³n de texto
    $tAi0 = microtime(true);
    error_log("[CV_DEBUG] Iniciando anÃ¡lisis con Ollama...");

    try {
        $aiData = $ollama->analyzeCvFromText($extractedText);
        $isFallback = false;
        error_log("[CV_DEBUG] Ollama anÃ¡lisis exitoso!");
    } catch (AiUnavailableException $e) {
        error_log("[CV_DEBUG] Ollama fallÃ³: " . $e->getMessage());
        // La IA fallÃ³ - usar fallback manual exitoso
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
        // Error inesperado - tambiÃ©n fallback manual exitoso
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

    // Log del resultado final
    error_log("[CV_DEBUG] Datos finales del CV: " . json_encode($aiData, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

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
    // âŒ PASO 11: MANEJO DE ERRORES CRÃTICOS
    // Cualquier excepciÃ³n no manejada llega aquÃ­
    $durMs = (int)round((microtime(true) - $__cv_parse_start) * 1000);
    logCvParse('FATAL', ['code' => 'PARSE_FAILED', 'duration_ms' => $durMs, 'subtimings_ms' => $__cv_parse_sub]);
    jsonResponse(422, ['success' => false, 'error' => ['code' => 'PARSE_FAILED', 'message' => 'Fallo inesperado procesando CV']]);
}

// ðŸ FIN DEL FLUJO
// El archivo PDF se elimina automÃ¡ticamente gracias a register_shutdown_function()
// El frontend recibe JSON con datos estructurados listos para el formulario
