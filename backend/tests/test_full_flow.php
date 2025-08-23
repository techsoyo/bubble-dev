<?php

/**
 * Test completo del flujo: Análisis de CV con Groq + Guardado de candidato
 */

require_once __DIR__ . '/config/bootstrap.php';

// 1. ANÁLISIS DEL CV CON GROQ
echo "=== TEST FLUJO COMPLETO: CV ANALYSIS + SAVE ===\n";
echo "1. Analizando CV con GroqApiService...\n";

$cvPath = __DIR__ . '/../Ejemplo1_CV.pdf';
if (!file_exists($cvPath)) {
  echo "❌ ERROR: Archivo CV no encontrado en $cvPath\n";
  exit(1);
}

// Leer y extraer texto del PDF para el análisis
echo "Extrayendo texto del PDF...\n";

use Smalot\PdfParser\Parser;

$parser = new Parser();
$pdf = $parser->parseFile($cvPath);
$cvText = $pdf->getText();

if (empty($cvText)) {
  echo "❌ ERROR: No se pudo extraer texto del PDF\n";
  exit(1);
}

echo "✅ Texto extraído del PDF: " . strlen($cvText) . " caracteres\n";

// Preparar datos para análisis
$analyzeData = [
  'cv_text' => $cvText
]; // Hacer petición al endpoint de análisis
$analyzeUrl = 'http://localhost/bubble_of_talents_1.0/backend/public/api/analyze_cv.php';
$jsonData = json_encode($analyzeData);

$ch = curl_init($analyzeUrl);
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

echo "URL Análisis: $analyzeUrl\n";
echo "HTTP Code: $httpCode\n";

if ($httpCode !== 200) {
  echo "❌ ERROR: Falló el análisis del CV\n";
  echo "Response: " . json_encode(json_decode($response, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
  exit(1);
}

$analysisResult = json_decode($response, true);
if (!isset($analysisResult['data']['structured_data'])) {
  echo "❌ ERROR: No se obtuvieron datos estructurados\n";
  echo "Response: " . json_encode($analysisResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
  exit(1);
}

$structuredData = $analysisResult['data']['structured_data'];
echo "✅ ÉXITO: CV analizado correctamente\n";
echo "Datos extraídos: " . count($structuredData) . " campos\n";
echo "Nombre: " . ($structuredData['nombre'] ?? 'N/A') . "\n";
echo "Email: " . ($structuredData['email'] ?? 'N/A') . "\n";
echo "\n";

// 2. GUARDADO DEL CANDIDATO
echo "2. Guardando candidato en base de datos...\n";

// Agregar data_source para marcar como procesado por IA
$structuredData['data_source'] = 'ai_processing';

// Hacer petición al endpoint de guardado
$saveUrl = 'http://localhost/bubble_of_talents_1.0/backend/public/api/candidates/save_v2.php';
$saveJsonData = json_encode($structuredData);

$ch = curl_init($saveUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $saveJsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Content-Length: ' . strlen($saveJsonData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$saveResponse = curl_exec($ch);
$saveHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL Guardado: $saveUrl\n";
echo "HTTP Code: $saveHttpCode\n";

if ($saveHttpCode !== 201) {
  echo "❌ ERROR: Falló el guardado del candidato\n";
  echo "Response: " . json_encode(json_decode($saveResponse, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
  exit(1);
}

$saveResult = json_decode($saveResponse, true);
echo "✅ ÉXITO: Candidato guardado correctamente\n";
echo "Candidate ID: " . ($saveResult['data']['candidate_id'] ?? 'N/A') . "\n";
echo "\n";

// 3. RESUMEN FINAL
echo "=== RESUMEN DEL FLUJO COMPLETO ===\n";
echo "✅ Análisis de CV con GroqApiService: ÉXITO\n";
echo "✅ Guardado de candidato en BD: ÉXITO\n";
echo "📊 Tiempo de procesamiento análisis: " . ($analysisResult['data']['processing_info']['processing_time_ms'] ?? 'N/A') . "ms\n";
echo "📋 Campos extraídos: " . count($structuredData) . "\n";
echo "🆔 ID del candidato: " . ($saveResult['data']['candidate_id'] ?? 'N/A') . "\n";
echo "🤖 Fuente de datos: " . ($saveResult['data']['data_source'] ?? 'N/A') . "\n";
echo "\n🎉 FLUJO COMPLETO EXITOSO - LISTO PARA INTEGRACIÓN FRONTEND!\n";
