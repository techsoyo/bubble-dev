<?php

declare(strict_types=1);

// scripts/normalize_endpoints.php
// - Garantiza arranque único vía config/bootstrap.php en endpoints públicos
// - Elimina CORS manual (headers Access-Control-Allow-*) y bloques OPTIONS
// - Quita require de cors.php en endpoints, ya que bootstrap lo carga centralizado
// Rutas objetivo: backend/public/*.php, backend/public/api/**/*.php, backend/api/*.php, backend/auth/*.php, backend/endpoints/*.php

$root = realpath(dirname(__DIR__));
$backend = $root . DIRECTORY_SEPARATOR . 'backend';

$targets = [];

function addGlob(array &$arr, string $pattern): void
{
  foreach (glob($pattern, GLOB_BRACE) as $f) {
    if (is_file($f)) {
      $arr[] = $f;
    }
  }
}

addGlob($targets, $backend . '/public/*.php');
addGlob($targets, $backend . '/public/api/*.php');
addGlob($targets, $backend . '/public/api/*/*.php');
addGlob($targets, $backend . '/public/api/*/*/*.php');
addGlob($targets, $backend . '/api/*.php');
addGlob($targets, $backend . '/auth/*.php');
addGlob($targets, $backend . '/endpoints/*.php');

$targets = array_values(array_unique($targets));

function computeDepthN(string $backendPath, string $file): int
{
  $fileDir = dirname($file);
  $rel = substr($fileDir, strlen($backendPath)); // e.g. \public\api\cv
  $rel = trim($rel, DIRECTORY_SEPARATOR);
  if ($rel === '') {
    return 0;
  }
  return substr_count($rel, DIRECTORY_SEPARATOR) + 1; // number of segments after backend
}

function ensureBootstrapHeader(string $backendPath, string $file, string $code): string
{
  // Si ya tiene require bootstrap, no modificar
  if (preg_match('#require_once\s+\$BOOT;#', $code)) {
    return $code; // ya normalizado por nosotros
  }
  if (preg_match('#require_once\s+[^;]*config/bootstrap\.php#', $code)) {
    return $code; // ya incluye bootstrap manualmente
  }
  // Asegurar que empieza con <?php y declare(strict_types=1);
  if (!str_starts_with($code, '<?php')) {
    $code = ltrim($code); // quitar espacios previos
    if (!str_starts_with($code, '<?php')) {
      $code = "<?php\n" . $code;
    }
  }
  // Insertar nuestro bloque tras <?php (o encabezados PHP existentes)
  $n = computeDepthN($backendPath, $file);
  $header = "<?php\n" .
    "declare(strict_types=1);\n" .
    "\$ROOT = dirname(__DIR__, $n);\n" .
    "\$BOOT = \$ROOT . '/config/bootstrap.php';\n" .
    "if (!is_file(\$BOOT)) { http_response_code(500); exit('Bootstrap no encontrado'); }\n" .
    "require_once \$BOOT;\n\n";
  // Si ya comienza con <?php declare, sustituimos el bloque inicial por el nuestro con bootstrap
  if (preg_match('#^<\?php\s+declare\(strict_types=1\);#', $code)) {
    // Insertar header justo después de la apertura PHP, evitando duplicar declare
    $code = preg_replace('#^<\?php\s+declare\(strict_types=1\);\s*#', $header, $code, 1);
  } else {
    // Preprender header completo
    // Quitar la etiqueta inicial si la vamos a repetir
    if (str_starts_with($code, '<?php')) {
      $code = substr($code, 5); // quitar '<?php'
    }
    $code = $header . $code;
  }
  return $code;
}

function stripManualCorsAndOptions(string $code): string
{
  // Quitar header('Access-Control-Allow-*');
  $code = preg_replace("#\n?\s*header\s*\(\s*['\"]Access-Control-Allow-[^'\"]+['\"][^)]*\)\s*;\s*#i", "\n", $code);
  // Quitar bloques OPTIONS
  $code = preg_replace("#if\s*\(\s*\$_SERVER\s*\[\s*['\"]REQUEST_METHOD['\"]\s*\]\s*={1,3}\s*['\"]OPTIONS['\"]\s*\)\s*\{[\s\S]*?\}\s*#i", "\n", $code);
  // Quitar require de cors.php
  $code = preg_replace("#\n?\s*require(_once)?\s*[^;]*cors\.php\s*;#i", "\n", $code);
  return $code;
}

$changed = 0;
foreach ($targets as $file) {
  $orig = file_get_contents($file);
  if ($orig === false) {
    fwrite(STDERR, "No se pudo leer $file\n");
    continue;
  }
  $code = $orig;
  $code = ensureBootstrapHeader($backend, $file, $code);
  $code = stripManualCorsAndOptions($code);
  if ($code !== $orig) {
    file_put_contents($file, $code);
    echo "Normalizado: $file\n";
    $changed++;
  }
}

echo "Total normalizados: $changed\n";
