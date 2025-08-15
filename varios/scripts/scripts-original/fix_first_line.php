<?php

declare(strict_types=1);

// scripts/fix_first_line.php
// Recorre todos los *.php (excluye vendor/, storage/, public/uploads/)
// - Si el archivo comienza con BOM UTF-8 o con una línea vacía/espacios antes de <?php
//   lo reescribe para que la primera línea sea exactamente "<?php" sin espacios previos,
//   manteniendo el resto del contenido intacto.
// Uso:
//   php scripts/fix_first_line.php           # Corrige en sitio
//   php scripts/fix_first_line.php --check   # Sólo verifica, exit!=0 si hay problemas
//   php scripts/fix_first_line.php -v        # Verbose

const EXCLUDED_DIRS = [
  'vendor' . DIRECTORY_SEPARATOR,
  'storage' . DIRECTORY_SEPARATOR,
  'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
];

function shouldExclude(string $path): bool
{
  $pathNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path) . (is_dir($path) ? DIRECTORY_SEPARATOR : '');
  foreach (EXCLUDED_DIRS as $ex) {
    if (stripos($pathNorm, DIRECTORY_SEPARATOR . $ex) !== false) {
      return true;
    }
    // También excluir si la ruta comienza exactamente por el directorio excluido
    if (str_starts_with(strtolower($pathNorm), strtolower($ex))) {
      return true;
    }
  }
  return false;
}

function listPhpFiles(string $root): array
{
  $rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
  );
  $files = [];
  /** @var SplFileInfo $file */
  foreach ($rii as $file) {
    if (!$file->isFile()) {
      continue;
    }
    $path = $file->getPathname();
    if (shouldExclude($path)) {
      continue;
    }
    if (strtolower($file->getExtension()) !== 'php') {
      continue;
    }
    $base = strtolower($file->getBasename());
    // Excluir backups/copias
    if (preg_match('/(_backup| copy)\.php$/i', $base)) {
      continue;
    }
    $files[] = $path;
  }
  sort($files);
  return $files;
}

function hasBom(string $content): bool
{
  return str_starts_with($content, "\xEF\xBB\xBF");
}

function firstLineIsEmpty(string $content): bool
{
  $pos = strpos($content, "\n");
  $firstLine = $pos === false ? $content : substr($content, 0, $pos);
  // Normalizar CRLF
  $firstLine = rtrim($firstLine, "\r\n");
  return trim($firstLine) === '';
}

function normalizeHeader(string $path, string $content): array
{
  // Devuelve [changed(bool), newContent(string)]
  $original = $content;
  $changed = false;

  // Strip BOM al inicio si existe
  if (hasBom($content)) {
    $content = substr($content, 3);
    $changed = true;
  }

  // Recortar espacios/saltos iniciales
  $trimmedLead = ltrim($content, "\x00\x09\x0A\x0B\x0C\x0D\x20");
  if ($trimmedLead !== $content) {
    $content = $trimmedLead;
    $changed = true;
  }

  // Si ahora empieza por <?php, ya está
  if (str_starts_with($content, '<?php')) {
    return [$changed || ($original !== $content), $content];
  }

  // Si no comienza con <?php pero el archivo era objetivo (tenía BOM o primera línea vacía),
  // añadir la etiqueta PHP al inicio respetando el resto del contenido
  $content = "<?php\n" . $content;
  $changed = true;

  return [$changed, $content];
}

function main(array $argv): int
{
  $root = dirname(__DIR__);
  $checkOnly = in_array('--check', $argv, true);
  $verbose = in_array('-v', $argv, true) || in_array('--verbose', $argv, true);

  $files = listPhpFiles($root);
  $issues = [];
  $changedCount = 0;

  foreach ($files as $f) {
    $content = file_get_contents($f);
    if ($content === false) {
      fwrite(STDERR, "No se pudo leer: $f\n");
      continue;
    }

    $hadBom = hasBom($content);
    $firstEmpty = firstLineIsEmpty($content);
    $needsFix = $hadBom || $firstEmpty;

    if ($checkOnly) {
      if ($hadBom) {
        $issues[] = ["file" => $f, "issue" => "BOM"];
      }
      if ($firstEmpty) {
        $issues[] = ["file" => $f, "issue" => "FIRST_LINE_EMPTY"];
      }
      continue;
    }

    if ($needsFix) {
      [$changed, $newContent] = normalizeHeader($f, $content);
      if ($changed && $newContent !== $content) {
        if (!is_writable($f)) {
          fwrite(STDERR, "No se puede escribir: $f\n");
          continue;
        }
        file_put_contents($f, $newContent);
        $changedCount++;
        if ($verbose) {
          echo "Arreglado: $f\n";
        }
      } else {
        // No se cambió: registrar como issue para revisión manual
        if ($needsFix) {
          $issues[] = ["file" => $f, "issue" => $hadBom ? 'BOM(no_auto_fix)' : 'FIRST_LINE_EMPTY(no_auto_fix)'];
        }
      }
    }
  }

  if ($checkOnly) {
    if (!empty($issues)) {
      foreach ($issues as $i) {
        echo $i['issue'] . " -> " . $i['file'] . "\n";
      }
      echo "Total issues: " . count($issues) . "\n";
      return 2;
    }
    echo "OK: sin BOM y sin primera línea vacía.\n";
    return 0;
  }

  if (!empty($issues)) {
    fwrite(STDERR, "Advertencia: hay archivos que requieren revisión manual (no se forzaron cambios).\n");
    foreach ($issues as $i) {
      fwrite(STDERR, $i['issue'] . " -> " . $i['file'] . "\n");
    }
  }
  echo "Cambios aplicados: $changedCount\n";
  return 0;
}

// Ejecutar sólo si se invoca directamente, no al hacer require
if (php_sapi_name() === 'cli') {
  $called = $_SERVER['SCRIPT_FILENAME'] ?? '';
  if ($called && realpath($called) === __FILE__) {
    exit(main($argv));
  }
}
