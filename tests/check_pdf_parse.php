<?php

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

/**
 * Test subida de CV (multipart/form-data)
 * Enviar: cv_file (archivo), user_email (texto)
 */

$endpoint = 'http://localhost:8000/api/pdf/parse';   // <-- AJUSTA si es .php
$pdfPath  = __DIR__ . '/Curriculum Vitae - daniel-alvarez-DevWeb.pdf';                // <-- Pon aquí tu PDF
$email    = 'autotest+cv@example.com';              // <-- Email de prueba
$token    = null;                                   // <-- Si tu endpoint exige Bearer

if (!file_exists($pdfPath)) {
  fwrite(STDERR, "FATAL: No existe el PDF: $pdfPath\n");
  exit(2);
}

$headers = [];
if ($token) $headers[] = 'Authorization: Bearer ' . $token;

$postfields = [
  'pdf'        => new CURLFile($pdfPath, 'application/pdf', basename($pdfPath)),
  'user_email' => $email,
];

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL            => $endpoint,
  CURLOPT_POST           => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POSTFIELDS     => $postfields,
  CURLOPT_HTTPHEADER     => $headers,
  CURLOPT_TIMEOUT        => 60
]);

$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
  echo "[FAIL] cURL error: $err\n";
  exit(1);
}

echo "HTTP $code\n$res\n";

if (!in_array($code, [200, 201])) {
  echo "[FAIL] Código HTTP inesperado\n";
  exit(1);
}

$decoded = json_decode($res, true);
if ($decoded === null) {
  echo "[FAIL] Respuesta no es JSON\n";
  exit(1);
}

$badWords = ['mock', 'fixture', 'lorem'];
foreach ($badWords as $w) {
  if (stripos($res, $w) !== false) {
    echo "[FAIL] Respuesta contiene '$w'\n";
    exit(1);
  }
}

echo "[OK] Parseo IA básico superado\n";
