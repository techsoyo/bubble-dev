<?php


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

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
// CONTROLADOR COMPLETO: Procesa CV en 2 etapas automÃƒÂ¡ticamente
header('Content-Type: application/json');

// 1. Recoger el nombre del archivo del POST
$filename = $_POST['filename'] ?? '';

if (!$filename) {
    echo json_encode(['error' => 'Falta el nombre del archivo']);
    exit;
}

$startTime = microtime(true);

// ================================
// ETAPA 1: EXTRACCIÃƒâ€œN CON LLAMA3
// ================================

$_POST['filename'] = $filename; // Asegurar que estÃƒÂ© disponible

ob_start();
include 'extract-cv-stage1.php';
$stage1Output = ob_get_clean();

$stage1Response = json_decode($stage1Output, true);

if (!$stage1Response || $stage1Response['status'] !== 'ok') {
    echo json_encode([
      'error' => 'Error en Etapa 1 (ExtracciÃƒÂ³n)',
      'stage1_error' => $stage1Response['error'] ?? 'Error desconocido',
      'details' => $stage1Response
    ]);
    exit;
}

$cleanFilename = $stage1Response['clean_file'];

// ================================
// ETAPA 2: ESTRUCTURACIÃƒâ€œN JSON CON RECRUITMENT-AI
// ================================

$_POST['clean_filename'] = $cleanFilename; // Preparar para etapa 2

ob_start();
include 'extract-cv-stage2.php';
$stage2Output = ob_get_clean();

$stage2Response = json_decode($stage2Output, true);

if (!$stage2Response || $stage2Response['status'] !== 'ok') {
    echo json_encode([
      'error' => 'Error en Etapa 2 (EstructuraciÃƒÂ³n JSON)',
      'stage1_success' => true,
      'stage2_error' => $stage2Response['error'] ?? 'Error desconocido',
      'details' => $stage2Response
    ]);
    exit;
}

$totalTime = microtime(true) - $startTime;

// ================================
// RESPUESTA FINAL COMPLETA
// ================================

echo json_encode([
  'status' => 'ok',
  'message' => 'CV procesado exitosamente en 2 etapas',
  'processing_time' => round($totalTime, 2) . 's',

  // Resultados de Etapa 1
  'stage1' => [
    'clean_file' => $stage1Response['clean_file'],
    'original_size' => $stage1Response['original_size'],
    'clean_size' => $stage1Response['clean_size'],
    'reduction_ratio' => $stage1Response['reduction_ratio']
  ],

  // Resultados de Etapa 2
  'stage2' => [
    'json_file' => $stage2Response['json_file'],
    'json_valid' => $stage2Response['json_valid'],
    'json_size' => $stage2Response['json_size'],
    'extracted_fields' => $stage2Response['extracted_fields']
  ],

  // Archivos generados
  'generated_files' => [
    'original' => $filename,
    'clean' => $stage1Response['clean_file'],
    'structured' => $stage2Response['json_file']
  ],

  'process_complete' => true
]);
exit;

