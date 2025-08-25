<?php

// PASO 1: Leer archivo y construir prompt

// 1. Recoger el nombre del archivo del POST
$filename = $_POST['filename'] ?? '';

if (!$filename) {
    echo json_encode(['error' => 'Falta el nombre del archivo']);
    exit;
}

// 2. Construir ruta absoluta segura
$filePath = __DIR__ . '/../../uploads/textos/' . basename($filename);

// 3. Leer el archivo .txt
if (!file_exists($filePath)) {
    echo json_encode(['error' => 'Archivo no encontrado']);
    exit;
}

$content = file_get_contents($filePath);

// // 4. Obtener el prompt base de OllamaService
// require_once __DIR__ . '/../../src/Services/OllamaService.php';

// use Backend\Services\OllamaService;

// Construir el prompt exactamente como en analyzeCV, pero solo hasta el armado del prompt
$prompt = "Analiza el siguiente CV en texto plano y extrae la informaciÃ³n en formato JSON estructurado con los siguientes campos:aunque algunos estÃ©n vacÃ­os):\n\n" .
    "{\n" .
    "  \"nombre\": \"\",\n" .
    "  \"email\": \"\",\n" .
    "  \"telefono\": \"\",\n" .
    "  \"ubicacion_actual\": \"\",\n" .
    "  \"fecha_nacimiento\": \"\",\n" .
    "  \"portfolio\": \"\",\n" .
    "  \"linkedin\": \"\",\n" .
    "  \"otras_redes\": [],\n" .
    "  \"resumen_profesional\": \"\",\n" .
    "  \"soft_skills\": [],\n" .
    "  \"intereses\": [],\n" .
    "  \"referencias\": [],\n" .
    "  \"disponibilidad\": \"\",\n" .
    "  \"puestos_anteriores\": [\n" .
    "    {\n" .
    "      \"puesto\": \"\",\n" .
    "      \"empresa\": \"\",\n" .
    "      \"fecha_inicio\": \"\",\n" .
    "      \"fecha_fin\": \"\",\n" .
    "      \"responsabilidades\": [],\n" .
    "      \"logros\": []\n" .
    "    }\n" .
    "  ],\n" .
    "  \"tecnologias_herramientas\": [],\n" .
    "  \"idiomas\": [\n" .
    "    {\n" .
    "      \"idioma\": \"\",\n" .
    "      \"nivel\": \"\"\n" .
    "    }\n" .
    "  ],\n" .
    "  \"educacion\": [\n" .
    "    {\n" .
    "      \"grado\": \"\",\n" .
    "      \"institucion\": \"\",\n" .
    "      \"fecha_inicio\": \"\",\n" .
    "      \"fecha_fin\": \"\"\n" .
    "    }\n" .
    "  ],\n" .
    "  \"certificaciones\": [],\n" .
    "  \"categoria\": \"\",\n" .
    "  \"subcategoria\": \"\",\n" .
    "  \"otros\": \"\"\n" .
    "}\n\n" .
    "Asocia el perfil a una de las siguientes categorÃ­as y subcategorÃ­as segÃºn la experiencia y habilidades detectadas:\n\n" .
    "[\n" .
    "  { categoria: 'Management', subcategorias: ['Account Manager', 'Account Director', 'Medical Strategist/Planner', 'Scientific Account Executive'] },\n" .
    "  { categoria: 'Creativity (Art & Design)', subcategorias: ['Copywriter (health)', 'Art Director', 'Graphic Designer', 'Content Creator/Content Strategist'] },\n" .
    "  { categoria: 'Digital & Technology', subcategorias: ['UX/UI Designer', 'Front-end/Web Developer', 'Mobile Developer (iOS/Android)', 'Digital Project Manager'] },\n" .
    "  { categoria: 'Audiovisual & Production', subcategorias: ['Video Producer', 'Motion Graphics Specialist', 'Postproduction Editor'] },\n" .
    "  { categoria: 'Events & Experiences', subcategorias: ['Event Manager', 'Production Coordinator', 'Experiential Marketing Specialist'] },\n" .
    "  { categoria: 'Communication & PR', subcategorias: ['PR/Media Relations Specialist', 'Community Manager', 'Content Manager'] },\n" .
    "  { categoria: 'Paid Media & Performance', subcategorias: ['Google Ads/Meta/TikTok Specialist', 'Email/CRM Marketing', 'Digital Analytics'] },\n" .
    "  { categoria: 'Internships/Junior', subcategorias: ['Design Intern', 'Copy Intern', 'Production Intern', 'Strategy Intern', 'Digital Intern'] }\n" .
    "]\n\n" .
    "Texto del CV:\n---\n" . $content . "\n---";

// PASO 2: Enviar el prompt a Ollama y recibir respuesta
// âš ï¸ Este cÃ³digo solo debe ejecutarse cuando el prompt del PASO 1 haya sido verificado.

$ollamaHost = 'http://localhost:11434';
$model = 'recruitment-ai'; // o el modelo que estÃ©s usando

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
    CURLOPT_TIMEOUT => 300, // 5 minutos timeout para prompts largos
    CURLOPT_CONNECTTIMEOUT => 10 // 10 segundos para conectar
]);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    echo json_encode(['error' => 'Error al conectar con Ollama', 'details' => curl_error($curl), 'timeout' => true]);
    curl_close($curl);
    exit;
}

curl_close($curl);

// Verificar que Ollama respondiÃ³
$data = json_decode($response, true);
if (!isset($data['response'])) {
    echo json_encode(['error' => 'Respuesta invÃ¡lida de Ollama', 'raw' => $response]);
    exit;
}

// Pasar al siguiente paso con $data['response']
echo json_encode(['status' => 'ok', 'ollama_response' => $data['response']]);
exit;
