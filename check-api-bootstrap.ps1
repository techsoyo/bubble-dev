# Verifica que cada endpoint PHP bajo backend/public/api (excepto bootstrap.php)
# comience con:
# 1) <?php
# 2) declare(strict_types=1);
# 3) require_once __DIR__ . '/bootstrap.php';

param(
  [string]$ApiDir = "backend\public\api"
)

# Patrones tolerantes a espacios y CRLF/LF; acepta '/./bootstrap.php'
$phpOpenPattern = '^\s*<\?php'
$declPattern = '^\s*declare\s*\(\s*strict_types\s*:\s*1\s*\)\s*;'
$reqPattern = '^\s*require_once\s+__DIR__\s*\.\s*[\'"]\/(?:\.\/)?bootstrap\.php[\'"]\s*; '
$commentPattern = '^\s*(// | /\* | \* | \*/)'

if (-not (Test-Path $ApiDir)) {
  Write-Host "Error: La carpeta '$ApiDir' no existe." -ForegroundColor Red
  exit 1
}

$ok = 0; $fail = 0
$files = Get-ChildItem -Path $ApiDir -Filter *.php -Recurse -File |
         Where-Object { $_.Name -ne 'bootstrap.php' }

if ($files.Count -eq 0) {
  Write-Host "No se encontraron archivos PHP en '$ApiDir'." -ForegroundColor Yellow
  exit 0
}

foreach ($f in $files) {
  try {
    $lines = Get-Content -LiteralPath $f.FullName -Encoding UTF8
    if ($lines.Count -eq 0) {
      Write-Host "FAIL  $($f.FullName)  (archivo vacío)" -ForegroundColor Red
      $fail++; continue
    }

    # tomar 3 primeras líneas "significativas" (ignora vacías, comentarios, BOM)
    $sig = New-Object System.Collections.Generic.List[string]
    $upper = [Math]::Min(50, $lines.Count)
    for ($i=0; $i -lt $upper; $i++) {
      $ln = if ($i -eq 0) { $lines[$i] -replace "^\uFEFF","" } else { $lines[$i] }
      $t  = $ln.Trim()
      if ($t -eq '') { continue }
      if ($t -match $commentPattern) { continue }
      $sig.Add($ln)
      if ($sig.Count -ge 3) { break }
    }

    $rel = $f.FullName.Replace((Get-Location).Path + [IO.Path]::DirectorySeparatorChar, "")
    if ($sig.Count -lt 3) {
      Write-Host "FAIL  $rel  (no hay 3 líneas significativas al inicio)" -ForegroundColor Red
      $fail++; continue
    }

    $okPhp  = ($sig[0] -match $phpOpenPattern)
    $okDecl = ($sig[1] -match $declPattern)
    $okReq  = ($sig[2] -match $reqPattern)

    if ($okPhp -and $okDecl -and $okReq) {
      Write-Host "OK    $rel" -ForegroundColor Green
      $ok++
    } else {
      Write-Host "FAIL  $rel" -ForegroundColor Red
      Write-Host ("      L1: {0}" -f $sig[0])
      Write-Host ("      L2: {0}" -f $sig[1])
      Write-Host ("      L3: {0}" -f $sig[2])
      if (-not $okPhp)  { Write-Host "      ⮕ L1 debe ser <?php" }
      if (-not $okDecl) { Write-Host "      ⮕ L2 debe ser declare(strict_types=1);" }
      if (-not $okReq)  { Write-Host "      ⮕ L3 debe ser require_once __DIR__ . '/bootstrap.php';" }
      $fail++
    }
  } catch {
    Write-Host "⚠ ERROR al leer: $($f.FullName) - $($_.Exception.Message)" -ForegroundColor Yellow
    $fail++
  }
}

Write-Host ""
Write-Host "=== RESUMEN ===" -ForegroundColor Cyan
Write-Host "Archivos correctos: $ok" -ForegroundColor Green
Write-Host "Archivos incorrectos: $fail" -ForegroundColor Red
Write-Host "Total archivos: $($ok + $fail)" -ForegroundColor White

exit ($(if ($fail -gt 0) {1} else {0}))
