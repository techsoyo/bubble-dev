<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}
require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno desde .env
if (file_exists(__DIR__ . '/.env')) {
  $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos($line, '#') === 0) continue; // Skip comments
    if (strpos($line, '=') !== false) {
      [$key, $value] = explode('=', $line, 2);
      $_ENV[trim($key)] = trim($value);
      putenv(trim($key) . '=' . trim($value));
    }
  }
}

echo "=== PRUEBA DEL ENDPOINT PARSE-CV-OPENAI ===\n\n";

// Crear un archivo CV de ejemplo
$cvText = "Juan Pérez Desarrollador\n" .
  "Email: juan.perez@email.com\n" .
  "Teléfono: +34 600 123 456\n" .
  "LinkedIn: linkedin.com/in/juanperez\n\n" .
  "EXPERIENCIA PROFESIONAL\n" .
  "Desarrollador Full Stack - Tech Company (2020-2024)\n" .
  "- Desarrollo de aplicaciones web con React y Node.js\n" .
  "- Implementación de APIs REST\n" .
  "- Trabajo con bases de datos MySQL y MongoDB\n\n" .
  "Desarrollador Junior - StartUp Inc (2018-2020)\n" .
  "- Desarrollo frontend con Vue.js\n" .
  "- Mantenimiento de código PHP\n\n" .
  "EDUCACIÓN\n" .
  "Ingeniería Informática - Universidad Complutense Madrid (2014-2018)\n\n" .
  "HABILIDADES\n" .
  "JavaScript, TypeScript, PHP, Python, React, Vue.js, Node.js, MySQL, MongoDB\n\n" .
  "IDIOMAS\n" .
  "Español (nativo), Inglés (B2), Francés (A2)";

$tempFile = __DIR__ . '/uploads/cvs/test_cv.txt';
file_put_contents($tempFile, $cvText);

echo "1. Archivo de prueba creado: {$tempFile}\n";
echo "2. Haciendo petición POST al endpoint...\n\n";

// Enviar una petición multipart/form-data de prueba
$url = 'http://localhost/bubble_of_talents_1.0/backend/api/ai/parse-cv-openai.php';

$cfile = new CURLFile($tempFile, 'text/plain', 'test_cv.txt');

$postData = array(
  'cv_file' => $cfile
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
  echo "❌ Error cURL: {$error}\n";
} else {
  echo "✅ Respuesta recibida (HTTP {$httpCode}):\n";
  echo $response . "\n";
}

// Limpiar archivo temporal
unlink($tempFile);

echo "\n=== FIN DE LA PRUEBA ===\n";
