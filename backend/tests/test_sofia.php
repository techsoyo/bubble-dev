<?php

/**
 * Test con CV de Sofia García
 */

require_once __DIR__ . '/config/bootstrap.php';

use Services\GroqApiService;

echo "=== TEST CON CV SOFIA GARCÍA ===\n";

$cvPath = __DIR__ . '/../Curriculum Vitae - sofia-garcia-mkt.pdf';

// 1. ANÁLISIS CON GROQ
$groqService = new GroqApiService();
$startTime = microtime(true);

try {
  $cvData = $groqService->analyzeCvFromPdf($cvPath);
  $duration = round(microtime(true) - $startTime, 2);

  echo "✅ CV analizado en {$duration}s\n";
  echo "Nombre: " . ($cvData['nombre'] ?? 'N/A') . "\n";
  echo "Email: " . ($cvData['email'] ?? 'N/A') . "\n";
  echo "Campos extraídos: " . count($cvData) . "\n";

  // 2. GUARDAR EN BD
  $payload = json_encode($cvData);

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'http://localhost/bubble_of_talents_1.0/backend/public/api/candidates/save_v2.php',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  echo "\nGuardado: HTTP $httpCode\n";
  if ($httpCode === 200) {
    echo "✅ ÉXITO: Candidato guardado\n";
  } else {
    echo "❌ ERROR: $response\n";
  }
} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
}
