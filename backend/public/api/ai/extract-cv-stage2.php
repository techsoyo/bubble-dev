<?php

declare(strict_types=1);

require_once __DIR__ . '/./bootstrap.php';

use Middleware\CsrfMiddleware;
use Middleware\JWTMiddleware;
use Services\JobMatchingService;
use Utils\ResponseHelper as Res;

// Autenticación obligatoria
JWTMiddleware::requireAuth();

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
preflightHandle();
sendCorsHeaders();

// 1. Recoger el nombre del archivo limpio del POST
$cleanFilename = $_POST['clean_filename'] ?? '';

if (!$cleanFilename) {
    echo json_encode(['error' => 'Falta el nombre del archivo limpio']);
    exit;
}

// 2. Construir ruta absoluta segura para leer el contenido limpio
$cleanFilePath = __DIR__ . '/../../uploads/clean/' . basename($cleanFilename);

// 3. Verificar que el archivo limpio existe
if (!file_exists($cleanFilePath)) {
    echo json_encode(['error' => 'Archivo limpio no encontrado']);
    exit;
}

// 4. Leer el contenido limpio
$cleanContent = file_get_contents($cleanFilePath);
if ($cleanContent === false) {
    echo json_encode(['error' => 'No se pudo leer el archivo limpio']);
    exit;
}

// 5. Crear directorio de JSONs si no existe
$jsonDir = __DIR__ . '/../../uploads/json/';
if (!is_dir($jsonDir)) {
    if (!mkdir($jsonDir, 0755, true)) {
        echo json_encode(['error' => 'No se pudo crear directorio json']);
        exit;
    }
}

// 6. Construir el prompt para estructuración JSON con recruitment-ai
$prompt = "Tarea: Analizar y estructurar en formato JSON el siguiente CV ya filtrado.\n\n" .
    "Instrucciones:\n" .
    "1. Extrae los siguientes campos, si estí¡n disponibles:\n" .
    "   - nombre, email, teléfono\n" .
    "   - formación académica: tí­tulo, institución, fechas\n" .
    "   - experiencia laboral: empresa, puesto, fechas, funciones\n" .
    "   - tecnologí­as/habilidades técnicas\n" .
    "   - idiomas (idioma y nivel)\n" .
    "   - certificaciones\n" .
    "   - logros o formaciones adicionales relevantes\n" .
    "2. La salida debe ser un JSON bien estructurado.\n" .
    "3. No inventes campos que no estén. Si algo no se encuentra, deja el array vací­o.\n" .
    "4. Cada entrada debe ir en su array correspondiente.\n\n" .
    "Formato de salida esperado:\n" .
    "{\n" .
    "  \"nombre\": \"\",\n" .
    "  \"email\": \"\",\n" .
    "  \"telefono\": \"\",\n" .
    "  \"formacion\": [\n" .
    "    {\n" .
    "      \"titulo\": \"\",\n" .
    "      \"institucion\": \"\",\n" .
    "      \"fecha_inicio\": \"\",\n" .
    "      \"fecha_fin\": \"\"\n" .
    "    }\n" .
    "  ],\n" .
    "  \"experiencia\": [\n" .
    "    {\n" .
    "      \"empresa\": \"\",\n" .
    "      \"puesto\": \"\",\n" .
    "      \"fecha_inicio\": \"\",\n" .
    "      \"fecha_fin\": \"\",\n" .
    "      \"funciones\": \"\"\n" .
    "    }\n" .
    "  ],\n" .
    "  \"tecnologias\": [],\n" .
    "  \"idiomas\": [\n" .
    "    {\n" .
    "      \"idioma\": \"\",\n" .
    "      \"nivel\": \"\"\n" .
    "    }\n" .
    "  ],\n" .
    "  \"certificaciones\": [],\n" .
    "  \"otros\": []\n" .
    "}\n\n" .
    "Contenido del CV limpio:\n" . $cleanContent;

// 7. Enviar al modelo recruitment-ai de Ollama
$ollamaHost = 'http://localhost:11434';
$model = 'recruitment-ai';

$curl = curl_init("$ollamaHost/api/generate");

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => $model,
        'prompt' => $prompt,
        'stream' => false,
        'format' => 'json'
    ]),
    CURLOPT_TIMEOUT => 240, // 4 minutos para JSON
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    echo json_encode([
        'error' => 'Error al conectar con Ollama (recruitment-ai)',
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
        'error' => 'Respuesta inví¡lida de Ollama (recruitment-ai)',
        'raw' => substr($response, 0, 200)
    ]);
    exit;
}

$jsonResponse = trim($data['response']);

// 9. Intentar extraer y validar el JSON
$jsonStart = strpos($jsonResponse, '{');
$jsonEnd = strrpos($jsonResponse, '}');

if ($jsonStart !== false && $jsonEnd !== false) {
    $jsonString = substr($jsonResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
    $parsedJson = json_decode($jsonString, true);

    if ($parsedJson) {
        // JSON ví¡lido extraí­do
        $finalJson = json_encode($parsedJson, JSON_PRETTY_PRINT);
    } else {
        // JSON inví¡lido, usar respuesta cruda pero marcar como no parseado
        $finalJson = $jsonResponse;
        $parsedJson = null;
    }
} else {
    // No se encontró JSON, usar respuesta cruda
    $finalJson = $jsonResponse;
    $parsedJson = null;
}

// 10. Generar nombre del archivo JSON
$pathInfo = pathinfo($cleanFilename);
$baseName = str_replace('-clean', '', $pathInfo['filename']);
$jsonFileName = $baseName . '-structured.json';
$jsonFilePath = $jsonDir . $jsonFileName;

// 11. Guardar el JSON en archivo
if (file_put_contents($jsonFilePath, $finalJson) === false) {
    echo json_encode(['error' => 'No se pudo guardar el archivo JSON']);
    exit;
}

// 12. Devolver respuesta exitosa de la ETAPA 2
echo json_encode([
    'status' => 'ok',
    'stage' => 2,
    'json_file' => $jsonFileName,
    'json_valid' => $parsedJson !== null,
    'clean_size' => strlen($cleanContent),
    'json_size' => strlen($finalJson),
    'extracted_fields' => $parsedJson ? array_keys($parsedJson) : [],
    'process_complete' => true
]);
exit;
