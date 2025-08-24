<?php

/**
 * PHP 8+ - Smoke suite paralela para endpoints.
 * - Lee tests de endpoints.json
 * - Ejecuta en paralelo con curl_multi
 * - Aserciones: status, JSON válido, claves requeridas, “forbid substrings”
 * - Reporte final con tiempos y errores
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

$startAll = microtime(true);
$inputFile = __DIR__ . '/endpoints.json';

if (!file_exists($inputFile)) {
  fwrite(STDERR, "FATAL: No se encuentra endpoints.json en " . $inputFile . PHP_EOL);
  exit(2);
}
$tests = json_decode(file_get_contents($inputFile), true);
if (!is_array($tests)) {
  fwrite(STDERR, "FATAL: endpoints.json inválido" . PHP_EOL);
  exit(2);
}

$mh = curl_multi_init();
$handles = [];
$results = [];

foreach ($tests as $i => $t) {
  $ch = curl_init();
  $method = strtoupper($t['method'] ?? 'GET');
  $url    = $t['url'] ?? '';
  if (!$url) {
    $results[$i] = ['name' => $t['name'] ?? "test_$i", 'ok' => false, 'error' => 'URL vacía'];
    continue;
  }

  $headers = [];
  if (!empty($t['headers']) && is_array($t['headers'])) {
    foreach ($t['headers'] as $k => $v) {
      $headers[] = $k . ': ' . $v;
    }
  }

  // Config base
  curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 25,
  ]);

  // Método + cuerpo
  switch ($method) {
    case 'GET':
      // default
      break;
    case 'POST':
    case 'PUT':
    case 'PATCH':
    case 'DELETE':
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      if (isset($t['bodyJson'])) {
        $payload = json_encode($t['bodyJson'], JSON_UNESCAPED_UNICODE);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
      } elseif (isset($t['bodyForm']) && is_array($t['bodyForm'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($t['bodyForm']));
      }
      break;
    default:
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  }

  if ($headers) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }

  $handles[$i] = [
    'name'   => $t['name'] ?? "test_$i",
    'expect' => [
      'status'          => $t['expectStatus'] ?? 200,
      'json'            => (bool)($t['expectJson'] ?? false),
      'keysAll'         => $t['expectKeys']    ?? [],
      'keysAny'         => $t['expectKeysAny'] ?? [],
      'forbidSubstrings' => $t['forbidSubstrings'] ?? [],
    ],
    'meta'   => [
      'method' => $method,
      'url'    => $url,
    ],
    'ch'     => $ch,
    'start'  => microtime(true),
  ];

  curl_multi_add_handle($mh, $ch);
}

// Ejecutar multi
$running = null;
do {
  $mrc = curl_multi_exec($mh, $running);
  curl_multi_select($mh, 1.0);
} while ($running > 0 && $mrc == CURLM_OK);

// Recoger resultados
foreach ($handles as $i => $info) {
  $ch   = $info['ch'];
  $raw  = curl_multi_getcontent($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $time = microtime(true) - $info['start'];

  $ok = true;
  $errors = [];

  // Aserción: status
  $expStatus = $info['expect']['status'];
  if ($code !== $expStatus) {
    $ok = false;
    $errors[] = "Status esperado $expStatus, recibido $code";
  }

  // Aserción: JSON válido
  $decoded = null;
  if ($info['expect']['json']) {
    $decoded = json_decode($raw, true);
    if ($decoded === null) {
      $ok = false;
      $errors[] = "Respuesta no es JSON válido";
    }
  }

  // Aserción: claves obligatorias (todas)
  if ($decoded !== null && !empty($info['expect']['keysAll'])) {
    foreach ($info['expect']['keysAll'] as $k) {
      if (!array_key_exists($k, $decoded)) {
        $ok = false;
        $errors[] = "Falta clave JSON obligatoria: '$k'";
      }
    }
  }

  // Aserción: al menos una clave
  if ($decoded !== null && !empty($info['expect']['keysAny'])) {
    $found = false;
    foreach ($info['expect']['keysAny'] as $k) {
      if (array_key_exists($k, $decoded)) {
        $found = true;
        break;
      }
    }
    if (!$found) {
      $ok = false;
      $errors[] = "Ninguna de las claves esperadas presentes: [" . implode(', ', $info['expect']['keysAny']) . "]";
    }
  }

  // Aserción: NO contenga substrings prohibidos (mock/fixture/etc)
  if (!empty($info['expect']['forbidSubstrings'])) {
    foreach ($info['expect']['forbidSubstrings'] as $bad) {
      if ($bad !== '' && stripos($raw, $bad) !== false) {
        $ok = false;
        $errors[] = "Salida contiene '$bad' (posible MOCK en producción)";
      }
    }
  }

  $results[$i] = [
    'name'   => $info['name'],
    'method' => $info['meta']['method'],
    'url'    => $info['meta']['url'],
    'status' => $code,
    'time_ms' => (int)round($time * 1000),
    'ok'     => $ok,
    'errors' => $errors,
  ];

  curl_multi_remove_handle($mh, $ch);
  curl_close($ch);
}

curl_multi_close($mh);

// Reporte
$okCnt = 0;
$failCnt = 0;
echo "=== API SMOKE SUITE ===\n";
foreach ($results as $r) {
  $label = $r['ok'] ? 'OK ' : 'FAIL';
  $line  = sprintf(
    "[%s] %-20s %s %s  (%d ms, %d)\n",
    $label,
    $r['name'],
    $r['method'],
    $r['url'],
    $r['time_ms'],
    $r['status']
  );
  echo $line;
  if (!$r['ok']) {
    foreach ($r['errors'] as $e) {
      echo "    - $e\n";
    }
  }
  $r['ok'] ? $okCnt++ : $failCnt++;
}

$dur = (int)round((microtime(true) - $startAll) * 1000);
echo "TOTAL: OK={$okCnt}  FAIL={$failCnt}  DUR={$dur}ms\n";

exit($failCnt > 0 ? 1 : 0);
