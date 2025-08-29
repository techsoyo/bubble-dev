<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;
use Utils\ResponseHelper as Res;

// Auth
JWTMiddleware::requireAuth();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

// En producción solo cookie
if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    Res::error('Unauthorized (cookie required)', null, 401);
}

// CORS helpers si existen
if (function_exists('preflightHandle')) {
    preflightHandle();
}
if (function_exists('sendCorsHeaders')) {
    sendCorsHeaders();
}

// Intentar obtener JSON de entrada, si no existe usar $_POST (form-data)
$input = null;
if (function_exists('\Services\ResponseHelper') && method_exists('\Services\ResponseHelper', 'getJsonInput')) {
    $input = Res::getJsonInput();
}
if (!is_array($input)) {
    // Fallback a POST (formularios multipart/form-data)
    $input = $_POST ?? [];
}

$filename = trim((string)($input['filename'] ?? ''));
if ($filename === '') {
    Res::fail('Falta el nombre del archivo', 400);
}

// 2. Construir ruta absoluta segura para leer el CV original
$originalFilePath = __DIR__ . '/../../uploads/textos/' . basename($filename);
if (!file_exists($originalFilePath)) {
    Res::fail('Archivo CV no encontrado', 404);
}

// 4. Leer el contenido del CV
$cvContent = file_get_contents($originalFilePath);
if ($cvContent === false) {
    Res::error('No se pudo leer el archivo CV', null, 500);
}

// 4.1. PRE-LIMPIEZA BÁSICA para reducir tamaño antes de enviar a modelo
$cvContent = preg_replace('/\s+/', ' ', $cvContent);
$cvContent = preg_replace('/[^\w\s\.\,\;\:\-\(\)\[\]\/\@\+]/u', '', $cvContent);
$cvContent = substr($cvContent, 0, 3000);

// 5. Crear directorio de contenido limpio si no existe
$cleanDir = __DIR__ . '/../../uploads/clean/';
if (!is_dir($cleanDir) && !mkdir($cleanDir, 0755, true)) {
    Res::error('No se pudo crear directorio clean', null, 500);
}

// 6. Construir prompt
$prompt = "Extrae solo informacion util de este CV. Incluye:\n" .
    "- Nombre, contacto, ubicacion\n" .
    "- Estudios (titulo, institucion, anio)\n" .
    "- Trabajo (empresa, puesto, anios, funciones principales)\n" .
    "- Tecnologias/herramientas\n" .
    "- Idiomas\n" .
    "- Certificaciones\n\n" .
    "Ignora decoraciones, marcas de agua, repeticiones.\n\n" .
    "CV:\n" . $cvContent;

// 7. Enviar al modelo (Ollama)
$ollamaHost = 'http://localhost:11434';
$model = 'mistral';

$curl = curl_init("$ollamaHost/api/generate");
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => $model,
        'prompt' => $prompt,
        'stream' => false
    ]),
    CURLOPT_TIMEOUT => 90,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response = curl_exec($curl);
if (curl_errno($curl)) {
    $err = curl_error($curl);
    curl_close($curl);
    Res::error('Error al conectar con Ollama (LLaMA3)', ['details' => $err], 502);
}
curl_close($curl);

// 8. Verificar respuesta
$data = json_decode($response, true);
if (!is_array($data) || !isset($data['response'])) {
    Res::error('Respuesta invalida de Ollama (LLaMA3)', ['raw' => substr((string)$response, 0, 200)], 502);
}

$cleanContent = trim((string)$data['response']);

// 9. Guardar
$pathInfo = pathinfo($filename);
$baseName = $pathInfo['filename'] ?? uniqid('cv_', true);
$cleanFileName = $baseName . '-clean.txt';
$cleanFilePath = $cleanDir . $cleanFileName;

if (file_put_contents($cleanFilePath, $cleanContent) === false) {
    Res::error('No se pudo guardar el archivo limpio', null, 500);
}

// 11. Responder
Res::success('ok', [
    'status' => 'ok',
    'stage' => 1,
    'clean_file' => $cleanFileName,
    'original_size' => strlen($cvContent),
    'clean_size' => strlen($cleanContent),
    'reduction_ratio' => round((1 - strlen($cleanContent) / max(1, strlen($cvContent))) * 100, 1) . '%',
    'ready_for_stage2' => true
]);
