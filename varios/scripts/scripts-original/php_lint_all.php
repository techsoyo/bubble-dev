<?php

declare(strict_types=1);

require_once __DIR__ . '/fix_first_line.php'; // Reusa listPhpFiles()

$root = dirname(__DIR__);
$files = listPhpFiles($root);
$errors = 0;
foreach ($files as $f) {
  $cmd = sprintf('php -l %s 2>&1', escapeshellarg($f));
  $out = [];
  $code = 0;
  exec($cmd, $out, $code);
  echo implode(PHP_EOL, $out) . PHP_EOL;
  if ($code !== 0) {
    $errors++;
  }
}
if ($errors > 0) {
  fwrite(STDERR, "Errores de sintaxis en $errors archivos\n");
  exit(1);
}

echo "php -l OK en todos los archivos" . PHP_EOL;
