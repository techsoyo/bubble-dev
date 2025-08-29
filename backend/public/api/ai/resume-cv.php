<?php

declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃƒÂ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    // double-submit cookie
}

// En producciÃƒÂ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
// TAREA 1: Resumir CV usando modelo Mistral
header('Content-Type: application/json');

// 1. Recoger el nombre del archivo del POST
$filename = $_POST['filename'] ?? '';

if (!$filename) {
    echo json_encode(['error' => 'Falta el nombre del archivo']);
    exit;
}

// 2. Construir ruta absoluta segura para leer el CV original
$originalFilePath = __DIR__ . '/../../uploads/textos/' . basename($filename);

// 3. Verificar que el archivo original existe
if (!file_exists($originalFilePath)) {
    echo json_encode(['error' => 'Archivo CV no encontrado']);
    exit;
}

// 4. Leer el contenido del CV
$cvContent = file_get_contents($originalFilePath);
if ($cvContent === false) {
    echo json_encode(['error' => 'No se pudo leer el archivo CV']);
    exit;
}

// 5. Crear directorio de resúmenes si no existe
$resumenesDir = __DIR__ . '/../../uploads/resumenes/';
if (!is_dir($resumenesDir)) {
    if (!mkdir($resumenesDir, 0755, true)) {
        echo json_encode(['error' => 'No se pudo crear directorio de resúmenes']);
        exit;
    }
}

// 6. Construir el prompt para el modelo Mistral
$prompt = "Eres un asistente que resume información curricular para procesos de selección. Dado el siguiente CV en texto plano, genera un resumen claro y útil para un reclutador. Solo incluye:\n" .
    "- Nombre completo\n" .
    "- Formación académica principal\n" .
    "- Idiomas con nivel\n" .
    "- Tecnologí­as o herramientas que domina\n" .
    "- Experiencia profesional destacada (mí¡x. 5 lí­neas)\n" .
    "- Soft skills mencionadas\n" .
    "- Datos de contacto si existen\n" .
    "- Ubicación geogrí¡fica (ciudad y paí­s si se menciona)\n\n" .
    "CV:\n" . $cvContent;

// 7. Enviar al modelo Mistral de Ollama
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
    CURLOPT_TIMEOUT => 120, // 2 minutos timeout
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    echo json_encode([
        'error' => 'Error al conectar con Ollama (Mistral)',
        'details' => curl_error($curl)
    ]);
    curl_close($curl);
    exit;
}

curl_close($curl);

// 8. Verificar respuesta de Ollama
$data = json_decode($response, true);
if (!isset($data['response'])) {
    echo json_encode([
        'error' => 'Respuesta inví¡lida de Ollama',
        'raw' => substr($response, 0, 200)
    ]);
    exit;
}

$resumen = trim($data['response']);

// 9. Generar nombre del archivo resumen
$pathInfo = pathinfo($filename);
$baseName = $pathInfo['filename'];
$resumenFileName = $baseName . '-resumen.txt';
$resumenFilePath = $resumenesDir . $resumenFileName;

// 10. Guardar el resumen en archivo
if (file_put_contents($resumenFilePath, $resumen) === false) {
    echo json_encode(['error' => 'No se pudo guardar el archivo de resumen']);
    exit;
}

// 11. Devolver respuesta exitosa
echo json_encode([
    'status' => 'ok',
    'resumen_file' => $resumenFileName,
    'original_size' => strlen($cvContent),
    'resumen_size' => strlen($resumen),
    'compression_ratio' => round((1 - strlen($resumen) / strlen($cvContent)) * 100, 1) . '%'
]);
exit;
