<?php declare(strict_types=1);
// @deprecated - archivo de test, deshabilitar en producciÃƒÂ³n
if ((\['APP_ENV'] ?? 'production') === 'production') {
    http_response_code(404);
    exit('Not found');
}



if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}

// Test con prompt real del CV (pero con timeout adecuado)
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  PRUEBA CON PROMPT COMPLETO DEL CV\n";
echo "=====================================\n\n";

$filename = $_POST['filename'] ?? '';
$filePath = __DIR__ . '/../../uploads/textos/' . basename($filename);

if (!file_exists($filePath)) {
    echo "ÃƒÂ¢Ã‚ÂÃ…â€™ Archivo no encontrado: $filePath\n";
    exit;
}

$content = file_get_contents($filePath);

// Construir prompt completo (igual que en parse-cv-file.php)
$prompt = "Analiza el siguiente CV en texto plano y extrae la informaciÃƒÆ’Ã‚Â³n en formato JSON estructurado con los siguientes campos:aunque algunos estÃƒÆ’Ã‚Â©n vacÃƒÆ’Ã‚Â­os):\n\n" .
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
    "Asocia el perfil a una de las siguientes categorÃƒÆ’Ã‚Â­as y subcategorÃƒÆ’Ã‚Â­as segÃƒÆ’Ã‚Âºn la experiencia y habilidades detectadas:\n\n" .
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

echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Longitud del prompt: ' . strlen($prompt) . " caracteres\n";
echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â¤ Enviando a Ollama... (esto puede tardar 30-60 segundos)\n\n";

$start_time = microtime(true);

$curl = curl_init('http://localhost:11434/api/generate');

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'recruitment-ai',
        'prompt' => $prompt,
        'stream' => false
    ]),
    CURLOPT_TIMEOUT => 120 // 2 minutos
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$total_time = microtime(true) - $start_time;

if (curl_errno($curl)) {
    echo 'ÃƒÂ¢Ã‚ÂÃ…â€™ Error cURL: ' . curl_error($curl) . "\n";
    echo 'ÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  Tiempo transcurrido hasta error: ' . round($total_time, 2) . " segundos\n";
    curl_close($curl);
    exit;
}

curl_close($curl);

echo 'ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Respuesta recibida en ' . round($total_time, 2) . " segundos\n";
echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  HTTP Code: $httpCode\n\n";

$data = json_decode($response, true);
if (!isset($data['response'])) {
    echo "ÃƒÂ¢Ã‚ÂÃ…â€™ Respuesta invÃƒÆ’Ã‚Â¡lida de Ollama\n";
    echo 'Raw: ' . substr($response, 0, 200) . "...\n";
    exit;
}

echo "ÃƒÂ°Ã…Â¸Ã…Â½Ã¢â‚¬Â° Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã¢â‚¬Â°XITO! CV procesado correctamente\n";
echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â Longitud de respuesta: ' . strlen($data['response']) . " caracteres\n\n";

// Intentar parsear como JSON
$cvJson = json_decode($data['response'], true);
if ($cvJson) {
    echo "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Respuesta es JSON vÃƒÆ’Ã‚Â¡lido\n";
    echo 'ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ NOMBRE: ' . ($cvJson['nombre'] ?? 'No detectado') . "\n";
    echo 'ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ CATEGORÃƒÆ’Ã‚ÂA: ' . ($cvJson['categoria'] ?? 'No detectada') . "\n";
    echo 'ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ SUBCATEGORÃƒÆ’Ã‚ÂA: ' . ($cvJson['subcategoria'] ?? 'No detectada') . "\n";
} else {
    echo "ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â Respuesta contiene texto adicional, necesita limpieza\n";
    echo "ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â Inicio de respuesta:\n";
    echo substr($data['response'], 0, 300) . "...\n";
}

echo "\nÃƒÂ¢Ã‚ÂÃ‚Â±ÃƒÂ¯Ã‚Â¸Ã‚Â  TIEMPO TOTAL: " . round($total_time, 2) . " segundos\n";
echo 'ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â¡ NORMAL para un modelo 8B con prompt de ' . strlen($prompt) . " caracteres\n";

