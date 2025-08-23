<?php

/**
 * check_routes.php
 * Script CLI para:
 * 1. Extraer rutas API usadas en frontend/ usando APIMiner.
 * 2. Comprobar que cada ruta responde correctamente en el backend.
 * 3. Imprimir resumen de resultados.
 *
 * Requiere: APIMiner (https://github.com/ezhov-evgeny/apiminer) instalado globalmente o en vendor/bin.
 */

// Configuración
$BASE_URL = getenv('API_BASE_URL') ?: 'http://localhost/backend/public/api';
$FRONTEND_PATH = __DIR__ . '/frontend';
$APIMINER_BIN = __DIR__ . '/vendor/bin/apiminer'; // O 'apiminer' si está global
$TIMEOUT = getenv('API_TIMEOUT') ?: 5;

// 1. Extraer rutas con APIMiner
function extract_routes($frontend_path, $apiminer_bin)
{
  $cmd = escapeshellcmd("$apiminer_bin --path $frontend_path --json");
  $output = shell_exec($cmd);
  if (!$output) {
    fwrite(STDERR, "Error: No se pudo ejecutar APIMiner o no se encontraron rutas.\n");
    exit(1);
  }
  $data = json_decode($output, true);
  if (!$data || !isset($data['routes'])) {
    fwrite(STDERR, "Error: Formato de salida inesperado de APIMiner.\n");
    exit(1);
  }
  // Extrae solo rutas únicas
  return array_unique(array_map(function ($r) {
    return $r['path'];
  }, $data['routes']));
}

// 2. Comprobar rutas en backend
function check_route($url, $timeout)
{
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => false,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_FOLLOWLOCATION => true,
  ]);
  $response = curl_exec($ch);
  $err = curl_errno($ch);
  $info = curl_getinfo($ch);
  $status = $info['http_code'] ?? 0;
  curl_close($ch);
  return [
    'status' => $status,
    'error' => $err ? curl_strerror($err) : null,
    'body' => $response,
  ];
}

// 3. Main
echo "Extrayendo rutas del frontend...\n";
$routes = extract_routes($FRONTEND_PATH, $APIMINER_BIN);
$total = count($routes);
echo "Total de rutas encontradas: $total\n\n";

$success = [];
$failed = [];

foreach ($routes as $route) {
  $full_url = rtrim($BASE_URL, '/') . '/' . ltrim($route, '/');
  $result = check_route($full_url, $TIMEOUT);
  $status = $result['status'];
  if (in_array($status, [200, 405])) {
    $success[] = ['route' => $route, 'status' => $status];
    echo "[OK] $route ($status)\n";
  } else {
    $failed[] = [
      'route' => $route,
      'status' => $status,
      'error' => $result['error'],
    ];
    echo "[FAIL] $route ($status)" . ($result['error'] ? " - {$result['error']}" : "") . "\n";
  }
}

// 4. Resumen
echo "\n--- RESUMEN ---\n";
echo "Total comprobadas: $total\n";
echo "Exitosas: " . count($success) . "\n";
echo "Fallidas: " . count($failed) . "\n";
if ($failed) {
  echo "Rutas fallidas:\n";
  foreach ($failed as $f) {
    echo "  - {$f['route']} (status: {$f['status']}" . ($f['error'] ? ", error: {$f['error']}" : "") . ")\n";
  }
}
echo "----------------\n";

// Opcional: Salida con código de error si hay fallidas
exit(count($failed) > 0 ? 2 : 0);
