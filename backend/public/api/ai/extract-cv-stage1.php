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
preflightHandle();
sendCorsHeaders();

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

// 4.1. PRE-LIMPIEZA BÃƒÂSICA para reducir tamaÃƒÂ±o antes de enviar a LLaMA3
$cvContent = preg_replace('/\s+/', ' ', $cvContent); // Normalizar espacios
$cvContent = preg_replace('/[^\w\s\.\,\;\:\-\(\)\[\]\/\@\+]/', '', $cvContent); // Remover caracteres especiales
$cvContent = substr($cvContent, 0, 3000); // Limitar a 3000 caracteres

// 5. Crear directorio de contenido limpio si no existe
$cleanDir = __DIR__ . '/../../uploads/clean/';
if (!is_dir($cleanDir)) {
    if (!mkdir($cleanDir, 0755, true)) {
        echo json_encode(['error' => 'No se pudo crear directorio clean']);
        exit;
    }
}

// 6. Construir el prompt para extracciÃƒÂ³n con LLaMA3 (mÃƒÂ¡s conciso)
$prompt = "Extrae solo informaciÃƒÂ³n ÃƒÂºtil de este CV. Incluye:\n" .
  "- Nombre, contacto, ubicaciÃƒÂ³n\n" .
  "- Estudios (tÃƒÂ­tulo, instituciÃƒÂ³n, aÃƒÂ±o)\n" .
  "- Trabajo (empresa, puesto, aÃƒÂ±os, funciones principales)\n" .
  "- TecnologÃƒÂ­as/herramientas\n" .
  "- Idiomas\n" .
  "- Certificaciones\n\n" .
  "Ignora decoraciones, marcas de agua, repeticiones.\n\n" .
  "CV:\n" . $cvContent;

// 7. Enviar al modelo Mistral de Ollama (mÃƒÂ¡s rÃƒÂ¡pido que LLaMA3)
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
  CURLOPT_TIMEOUT => 90, // 1.5 minutos para extracciÃƒÂ³n
  CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    echo json_encode([
      'error' => 'Error al conectar con Ollama (LLaMA3)',
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
      'error' => 'Respuesta invÃƒÂ¡lida de Ollama (LLaMA3)',
      'raw' => substr($response, 0, 200)
    ]);
    exit;
}

$cleanContent = trim($data['response']);

// 9. Generar nombre del archivo limpio
$pathInfo = pathinfo($filename);
$baseName = $pathInfo['filename'];
$cleanFileName = $baseName . '-clean.txt';
$cleanFilePath = $cleanDir . $cleanFileName;

// 10. Guardar el contenido limpio en archivo
if (file_put_contents($cleanFilePath, $cleanContent) === false) {
    echo json_encode(['error' => 'No se pudo guardar el archivo limpio']);
    exit;
}

// 11. Devolver respuesta exitosa de la ETAPA 1
echo json_encode([
  'status' => 'ok',
  'stage' => 1,
  'clean_file' => $cleanFileName,
  'original_size' => strlen($cvContent),
  'clean_size' => strlen($cleanContent),
  'reduction_ratio' => round((1 - strlen($cleanContent) / strlen($cvContent)) * 100, 1) . '%',
  'ready_for_stage2' => true
]);
exit;

