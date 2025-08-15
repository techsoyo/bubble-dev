<?php

declare(strict_types=1);

echo "=== PRUEBA DE LOGIN STAFF ===\n\n";

// Datos de prueba
$testData = [
  'action' => 'staff_login',
  'email' => 'ana.torres@bubblegum.agency',
  'password' => 'BubbleAdmin2025!'
];

// URL del endpoint
$url = 'http://localhost:8000/backend/auth/staff-login.php';

// Configurar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

echo "Enviando petición a: $url\n";
echo "Datos: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Ejecutar petición
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "Código HTTP: $httpCode\n";

if ($error) {
  echo "Error cURL: $error\n";
} else {
  echo "Respuesta cruda:\n";
  echo $response . "\n\n";

  echo "Respuesta formateada:\n";
  $decoded = json_decode($response, true);
  if ($decoded) {
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
  } else {
    echo "No se pudo decodificar JSON\n";
  }
}
