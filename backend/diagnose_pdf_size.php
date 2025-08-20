<?php

/**
 * Diagnóstico del tamaño del PDF base64
 */

require_once __DIR__ . '/autoload.php';

$pdfPath = __DIR__ . '/../Curriculum Vitae - javier-rodriguez-mkt.pdf';

if (!file_exists($pdfPath)) {
  echo "PDF no encontrado\n";
  exit(1);
}

$pdfContent = file_get_contents($pdfPath);
$pdfBase64 = base64_encode($pdfContent);

echo "=== DIAGNÓSTICO PDF BASE64 ===\n";
echo "Archivo original: " . number_format(strlen($pdfContent)) . " bytes\n";
echo "Base64: " . number_format(strlen($pdfBase64)) . " caracteres\n";
echo "Proporción: " . round((strlen($pdfBase64) / strlen($pdfContent)), 2) . "x\n";

// Mostrar primeros y últimos caracteres del base64
echo "\nPrimeros 100 caracteres base64:\n";
echo substr($pdfBase64, 0, 100) . "...\n";

echo "\nÚltimos 100 caracteres base64:\n";
echo "..." . substr($pdfBase64, -100) . "\n";

// Verificar si el base64 es válido
$decoded = base64_decode($pdfBase64);
if ($decoded !== false && $decoded === $pdfContent) {
  echo "\n✓ Base64 es válido y decodifica correctamente\n";
} else {
  echo "\n✗ Error en la codificación base64\n";
}

// Información adicional
echo "\nTamaño estimado del prompt completo: ~" . number_format(strlen($pdfBase64) + 2000) . " caracteres\n";
