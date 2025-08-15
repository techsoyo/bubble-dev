<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}

/**
 * Script de prueba para verificar el endpoint de procesamiento de CV
 */

// Función para probar el endpoint
function testCVEndpoint()
{
  // Probar diferentes URLs posibles de Laragon
  $possibleUrls = [
    'http://localhost:8000/bubble_of_talents_1.0/backend/api/ai/parse-cv-openai.php',
    'http://bubble-of-talents-1-0.test/backend/api/ai/parse-cv-openai.php',
    'http://localhost/bubble_of_talents_1.0/backend/api/ai/parse-cv-openai.php',
    'http://127.0.0.1:8000/bubble_of_talents_1.0/backend/api/ai/parse-cv-openai.php'
  ];

  foreach ($possibleUrls as $url) {
    echo "Probando URL: $url\n";
    if (testSingleURL($url)) {
      break;
    }
    echo "---\n";
  }
}

function testSingleURL($url)
{
  // Crear un archivo de prueba temporal
  $testContent = "Curriculum Vitae\nNombre: Test Usuario\nEmail: test@local\nTeléfono: +34 123 456 789";
  $tempFile = tempnam(sys_get_temp_dir(), 'test_cv_') . '.txt';
  file_put_contents($tempFile, $testContent);

  // Preparar la petición
  $postData = [
    'user_email' => 'user@local',
    'use_openai' => 'true',
    'cv_file' => new CURLFile($tempFile, 'text/plain', 'test_cv.txt')
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  // Limpiar archivo temporal
  unlink($tempFile);

  echo "HTTP Code: $httpCode\n";

  if ($error) {
    echo "cURL Error: $error\n";
    return false;
  }

  if ($httpCode === 0) {
    echo "No se pudo conectar al servidor\n";
    return false;
  }

  echo "Response Length: " . strlen($response) . " bytes\n";

  // Verificar si es JSON válido
  $decoded = json_decode($response, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    echo "✅ Response is valid JSON\n";
    echo "Response Data:\n";
    print_r($decoded);
    return true;
  } else {
    echo "❌ Response is NOT valid JSON\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Raw Response:\n";
    echo substr($response, 0, 1000) . "\n";
    if (strlen($response) > 1000) {
      echo "... (truncated)\n";
    }
    return false;
  }
}

// Ejecutar la prueba si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
  testCVEndpoint();
}
