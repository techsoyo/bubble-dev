#!/usr/bin/env php
<?php
/**
 * Limpieza de ficheros antiguos (retención GDPR básica).
 * Borra archivos en storage/private/cv/ y storage/json/ con antigüedad > CV_RETENTION_DAYS (def 30).
 * Uso: php backend/bin/cleanup.php
 */
$start = microtime(true);
$base = realpath(__DIR__ . '/..');
$days = (int) (getenv('CV_RETENTION_DAYS') ?: ($_ENV['CV_RETENTION_DAYS'] ?? 30));
$cutoff = time() - ($days * 86400);
$targets = [
  $base . '/storage/private/cv',
  $base . '/storage/json'
];
$deleted = 0;
$scanned = 0;
$errors = 0;
foreach ($targets as $dir) {
  if (!is_dir($dir)) continue;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $scanned++;
    $mtime = $f->getMTime();
    if ($mtime < $cutoff) {
      if (@unlink($f->getPathname())) {
        $deleted++;
      } else {
        $errors++;
      }
    }
  }
}
$dur = (int) round((microtime(true) - $start) * 1000);
$logLine = json_encode([
  'ts' => gmdate('c'),
  'event' => 'cleanup',
  'retention_days' => $days,
  'scanned' => $scanned,
  'deleted' => $deleted,
  'errors' => $errors,
  'duration_ms' => $dur
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents('php://stderr', $logLine . PHP_EOL);
echo "Cleanup OK: deleted=$deleted scanned=$scanned duration_ms=$dur\n";
