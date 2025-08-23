<?php

/**
 * Test del endpoint de guardado de candidatos
 */

require_once __DIR__ . '/config/bootstrap.php';

// Preparar datos de prueba
$testData = [
  'nombre' => 'Juan Pérez',
  'email' => 'juan.perez@example.com',
  'telefono' => '+1234567890',
  'ubicacion_actual' => 'Madrid, España',
  'fecha_nacimiento' => '1990-01-15',
  'resumen_profesional' => 'Desarrollador Full Stack con 5 años de experiencia',
  'soft_skills' => ['Comunicación', 'Trabajo en equipo'],
  'hard_skills' => ['JavaScript', 'PHP', 'MySQL'],
  'data_source' => 'ai_processing',
  'puestos_anteriores' => [
    [
      'puesto' => 'Desarrollador Senior',
      'empresa' => 'Tech Company',
      'fecha_inicio' => '2020-01-01',
      'fecha_fin' => '2023-12-31',
      'descripcion' => 'Desarrollo de aplicaciones web'
    ]
  ],
  'educacion' => [
    [
      'titulo' => 'Ingeniería Informática',
      'institucion' => 'Universidad Técnica',
      'fecha_inicio' => '2012-09-01',
      'fecha_fin' => '2016-06-30'
    ]
  ]
];

// Hacer petición al endpoint
$url = 'http://localhost/bubble_of_talents_1.0/backend/public/api/candidates/save_v2.php';
$jsonData = json_encode($testData);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Content-Length: ' . strlen($jsonData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== TEST ENDPOINT SAVE CANDIDATO ===\n";
echo "URL: $url\n";
echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

if ($httpCode === 201) {
  echo "✅ ÉXITO: Candidato guardado correctamente\n";
} else {
  echo "❌ ERROR: Falló el guardado del candidato\n";
}
