<?php

if ((getenv('APP_ENV') ?: 'production') === 'production') {
    http_response_code(404);
    exit;
}
// Proteger endpoint de pruebas en entornos de producciÃ³n.
require_once dirname(__DIR__, 2) . '/config/config.php';
if (function_exists('isProduction') && isProduction()) {
    http_response_code(403);
    echo json_encode(['error' => 'Endpoint disabled in production']);
    exit;
}
// Script de debug para ver exactamente quÃ© se envÃ­a a Ollama
$_POST['filename'] = 'Ejemplo1_CV_2025-07-27_12-13-23.txt';

echo "ðŸ” DEBUG: Â¿QUÃ‰ SE ENVÃA A OLLAMA?\n";
echo "==================================\n\n";

// Ejecutar los primeros pasos localmente sin enviar a Ollama
$filename = $_POST['filename'] ?? '';
$filePath = __DIR__ . '/../../uploads/textos/' . basename($filename);

if (!file_exists($filePath)) {
    echo "âŒ Archivo no encontrado: $filePath\n";
    exit;
}

$content = file_get_contents($filePath);

// Construir el prompt exactamente como en el archivo original
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

echo "ðŸ“Š INFORMACIÃ“N DEL PROMPT:\n";
echo "==========================\n";
echo 'ðŸ“ Longitud total: ' . strlen($prompt) . " caracteres\n";
echo "ðŸ“„ Archivo CV: $filename\n";
echo 'ðŸ’¾ TamaÃ±o del contenido del CV: ' . strlen($content) . " caracteres\n\n";

echo "ðŸš€ PAYLOAD QUE SE ENVÃA A OLLAMA:\n";
echo "=================================\n";

$payload = json_encode([
    'model' => 'recruitment-ai',
    'prompt' => $prompt,
    'stream' => false
], JSON_PRETTY_PRINT);

echo $payload . "\n\n";

echo "ðŸ” PRIMEROS 500 CARACTERES DEL PROMPT:\n";
echo "======================================\n";
echo substr($prompt, 0, 500) . "...\n\n";

echo "ðŸ” ÃšLTIMOS 300 CARACTERES DEL PROMPT:\n";
echo "====================================\n";
echo '...' . substr($prompt, -300) . "\n\n";

echo "âœ… DEBUG COMPLETADO - READY PARA ENVIAR A OLLAMA\n";
