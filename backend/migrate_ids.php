<?php

/**
 * Migración segura UUID → INT (dry-run por defecto)
 * Uso:
 *   php migrar_uuid_a_int.php
 *   php migrar_uuid_a_int.php --apply            # aplica cambios
 *   php migrar_uuid_a_int.php --root=/ruta/base  # raíz del repo
 */

declare(strict_types=1);

// ------------------ CLI ------------------
$dryRun  = true;
$rootDir = getcwd();
$args    = array_slice($argv ?? [], 1);

foreach ($args as $opt) {
  if ($opt === '--apply') {
    $dryRun = false;
  } elseif (strpos($opt, '--root=') === 0) { // compat 7.4+
    $rootDir = substr($opt, 7);
  }
}
$rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);

// ------------------ Archivos a procesar ------------------
$files = [
  // CAPA 1: HTTP API
  'public/api/application_notes.php',
  'public/api/applications.php',
  'public/api/candidate-applications.php',
  'public/api/candidate-experiences.php',
  'public/api/candidate_certifications.php',
  'public/api/candidate_education.php',
  'public/api/candidate_languages.php',
  'public/api/candidate_references.php',
  'public/api/candidate_routing.php',
  'public/api/candidate_skills.php',
  'public/api/candidates.php',
  'public/api/chatbot_analytics.php',
  'public/api/chatbot_nodes.php',
  'public/api/chatbot_options.php',
  'public/api/departments.php',
  'public/api/interviews.php',
  'public/api/job_benefits.php',
  'public/api/jobs.php',
  'public/api/news.php',
  'public/api/notifications.php',
  'public/api/recruiters.php',
  'public/api/social_logins.php',

  // CAPA 2: Auth y específicos
  'public/api/ai_assignment.php',
  'public/api/analyze_cv.php',
  'public/api/auth/OAuthHandler.php',
  'public/api/auth/social-callback.php',
  'public/api/auth/social-login.php',
  'public/api/auth/sso/callback.php',
  'public/api/auth/sso/start.php',
  'public/api/candidates/save.php',
  'public/api/candidates/save_v2.php',
  'public/api/cv/confirm.php',
  'public/api/cv/ingest.php',
  'public/api/cv/parse.php',
  'public/api/save-candidate.php',
  'public/api/save-notification-preferences.php',
  'public/api/route.php',
  'public/api/reporting.php',

  // CAPA 3: Models y Controllers
  'src/Models/BaseModel.php',
  'src/Models/Recruiter.php',
  'src/Controllers/AuthController.php',
  'src/Controllers/CandidateController.php',

  // CAPA 4: Middleware, Router y Services
  'Middleware/CsrfMiddleware.php',
  'Router/AppRouter.php',
  'Services/AIIntegrationService.php',
  'Services/FileService.php',
  'Services/OpenAIService.php',
  'Services/PDFExtractorService.php',
  'Services/PdfTextService.php',
];

// ------------------ Patrones (revisados) ------------------
// Cada entrada: pattern, replace, description
$patterns = [
  // 1) Validadores tipo DSL: string/regex → int (mantiene comillas)
  [
    'pattern'     => '#([\'"])required\|string:1,36(?:\|regex:/\^[^/]+/)?\1#',
    'replace'     => '$1required|int$1',
    'description' => 'Validador DSL: string/regex UUID → int',
  ],

  // 2) Casts a string sobre variables *_id → int
  [
    'pattern'     => '#\(\s*string\s*\)\s*(\$(?:[A-Za-z_]\w*?(?:id|_id)))\b#i',
    'replace'     => '(int)$1',
    'description' => 'Cast (string)$foo_id → (int)$foo_id',
  ],
  [
    'pattern'     => '#\bstrval\s*\(\s*(\$(?:[A-Za-z_]\w*?(?:id|_id)))\s*\)#i',
    'replace'     => '(int)$1',
    'description' => 'strval($foo_id) → (int)$foo_id',
  ],

  // 3) INSERT: quitar columna id en primera posición + primer valor UUID/prefijo+uniqid
  [
    'pattern'     => '#\bINSERT\s+INTO\s+([^\(]+)\(\s*id\s*,#i',
    'replace'     => 'INSERT INTO $1(',
    'description' => 'INSERT: quitar columna id (autoincrement)',
  ],
  [
    'pattern'     => '#\(\s*UUID\s*\(\s*\)\s*,#i',
    'replace'     => '(',
    'description' => 'VALUES: quitar UUID() en la 1ª posición',
  ],
  [
    'pattern'     => '#\(\s*[\'"](cand|rec|news|usr|job)[-_]?[\'"]\s*\.\s*uniqid\s*\(\s*\)\s*,#i',
    'replace'     => '(',
    'description' => 'VALUES: quitar prefijo+uniqid() en la 1ª posición',
  ],

  // 4) Generación manual de IDs: comentar SOLO si es variable *_id
  [
    'pattern'     => '#\$(?:[A-Za-z_]\w*?(?:id|_id))\s*=\s*uniqid\s*\([^)]*\)\s*;#i',
    'replace'     => '/* ID AUTO_INCREMENT */',
    'description' => 'Asignación ID: uniqid(...) → comentar',
  ],
  [
    'pattern'     => '#\$(?:[A-Za-z_]\w*?(?:id|_id))\s*=\s*bin2hex\s*\(\s*random_bytes\s*\(\s*\d+\s*\)\s*\)\s*;#i',
    'replace'     => '/* ID AUTO_INCREMENT */',
    'description' => 'Asignación ID: bin2hex(random_bytes(...)) → comentar',
  ],
];

// (Opcional, agresivo) SQL: campo = '' → campo IS NULL (desactivado por defecto)
// $patterns[] = [
//   'pattern'     => '#\b(\w+)\s*=\s*\'\'#',
//   'replace'     => '$1 IS NULL',
//   'description' => "SQL: campo = '' → campo IS NULL (revisar caso a caso)",
// ];

// ------------------ Utilidades ------------------
function compileCheck(string $pattern): bool
{
  set_error_handler(function () {}); // silenciar warnings de PCRE
  $ok = @preg_match($pattern, '') !== false;
  restore_error_handler();
  return $ok;
}

function unifiedDiff(string $old, string $new, int $ctx = 2): string
{
  $a = explode("\n", $old);
  $b = explode("\n", $new);
  $out = [];
  $max = max(count($a), count($b));
  for ($i = 0; $i < $max; $i++) {
    $oa = $a[$i] ?? '';
    $nb = $b[$i] ?? '';
    if ($oa !== $nb) {
      $start = max(0, $i - $ctx);
      $end   = min($max - 1, $i + $ctx);
      $out[] = sprintf("@@ línea %d @@", $i + 1);
      for ($j = $start; $j <= $end; $j++) {
        $la = $a[$j] ?? '';
        $lb = $b[$j] ?? '';
        if ($la === $lb)            $out[] = '  ' . $la;
        elseif ($la !== '')         $out[] = '- ' . $la;
        if ($lb !== '' && $lb !== $la) $out[] = '+ ' . $lb;
      }
      $i = $end;
    }
  }
  return implode("\n", $out);
}

// ------------------ Ejecución ------------------
echo "=== Migración UUID → INT ===\n";
echo "Raíz: {$rootDir}\n";
echo "Modo: " . ($dryRun ? "DRY RUN (simulación)" : "APLICAR CAMBIOS") . "\n\n";

$totalFiles   = 0;
$totalRepl    = 0;
$perPattern   = array_fill_keys(array_map(fn($p) => $p['description'], $patterns), 0);

foreach ($files as $relPath) {
  $path = $rootDir . DIRECTORY_SEPARATOR . $relPath;
  if (!is_file($path)) {
    echo "⚠️  No encontrado: {$relPath}\n";
    continue;
  }
  $totalFiles++;

  $original = file_get_contents($path);
  if ($original === false) {
    echo "❌  Error leyendo: {$relPath}\n";
    continue;
  }
  // Evita tocar binarios accidentalmente
  if (strpos($original, "\0") !== false) {
    echo "⏭️  Binario detectado, salto: {$relPath}\n";
    continue;
  }

  $content   = $original;
  $fileCount = 0;

  foreach ($patterns as $p) {
    $pattern = $p['pattern'] ?? null;
    $replace = $p['replace'] ?? null;
    $desc    = $p['description'] ?? 'sin descripción';

    if (!is_string($pattern) || !is_string($replace)) {
      echo "❌  Patrón inválido ({$desc})\n";
      continue;
    }
    if (!compileCheck($pattern)) {
      echo "❌  Regex no compila, omitida: {$desc}\n";
      continue;
    }
    $new = @preg_replace($pattern, $replace, $content, -1, $count);
    if ($new === null) {
      echo "❌  preg_replace falló: {$desc}\n";
      continue;
    }
    if ($count > 0) {
      $content          = $new;
      $fileCount       += $count;
      $totalRepl       += $count;
      $perPattern[$desc] += $count;
    }
  }

  // Aviso no destructivo: INSERT con 'id' en cualquier posición (pendiente revisión manual)
  if (preg_match('#\bINSERT\s+INTO\s+[^\(]+\(\s*[^)]*\bid\b[^)]*\)\s*VALUES#i', $content)) {
    echo "⚠️  Aún hay INSERT con columna 'id' en {$relPath} (posible no en 1ª posición). Revisión manual recomendada.\n";
  }

  if ($fileCount > 0) {
    echo "🔧 Cambios en: {$relPath} (reemplazos: {$fileCount})\n";
    if ($dryRun) {
      $diff = unifiedDiff($original, $content, 2);
      if ($diff) echo $diff . "\n";
    } else {
      $bak = $path . '.bak';
      if (!copy($path, $bak)) {
        echo "❌  No se pudo crear backup: {$bak}\n";
        continue;
      }
      if (file_put_contents($path, $content) === false) {
        echo "❌  Error escribiendo: {$relPath}\n";
        @copy($bak, $path); // intentar restaurar
        continue;
      }
      echo "✅  Aplicado y backup creado: {$relPath} → {$relPath}.bak\n\n";
    }
  }
}

// ------------------ Resumen ------------------
echo "=== Resumen ===\n";
echo "- Archivos existentes procesados: {$totalFiles}\n";
echo "- Reemplazos totales: {$totalRepl}\n";
echo "- Modo: " . ($dryRun ? "DRY RUN" : "APLICADO") . "\n\n";
echo "Por patrón:\n";
foreach ($perPattern as $desc => $n) {
  printf("  • %-65s %5d\n", $desc, $n);
}
if ($dryRun) {
  echo "\n💡 Para aplicar los cambios: php migrar_uuid_a_int.php --apply\n";
}
