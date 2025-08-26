# fix_psr4_now.ps1 — ejecuta desde backend/
# Corrige namespaces PSR-4 según carpeta y cabecera strict_types en TODOS los src/**/*.php
# No borra nada. Hace backup y log.

$ErrorActionPreference = 'Stop'
$root   = (Get-Location).Path
if (!(Test-Path "$root\composer.json")) { throw "Ejecuta desde 'backend/'" }
$srcDir = Join-Path $root 'src'

$ts     = Get-Date -Format yyyyMMdd_HHmmss
$backup = Join-Path $root "backup_psr4_$ts"
New-Item -ItemType Directory -Force -Path $backup | Out-Null
$log    = Join-Path $backup "psr4_fix_$ts.log"
function Log($m){ $m | Tee-Object -FilePath $log -Append }

$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
function Write-NoBom($p,$c){ [System.IO.File]::WriteAllText($p,$c,$Utf8NoBom) }

Log "== START PSR-4 FIX =="

# Rutas mapeadas esperadas (según tus errores de composer)
$roots = @('Models','Utils','Services','Parsers','Middleware','Controllers','Router','Domain')

# Recorre todos los PHP bajo src
$files = Get-ChildItem -Recurse -Path $srcDir -Include *.php -File
foreach ($f in $files) {
  $rel = $f.FullName.Substring($srcDir.Length).TrimStart('\').Replace('/','\')
  # Debe estar bajo uno de los roots
  $found = $false
  foreach ($r in $roots) {
    if ($rel.StartsWith("$r\")) { $found = $true; break }
  }
  if (-not $found) { continue } # fuera de PSR-4, ignora

  $orig = Get-Content $f.FullName -Raw

  # Quitar BOM y cualquier apertura previa, declare y namespace
  $c = [regex]::Replace($orig, '^\uFEFF', '')
  $c = [regex]::Replace($c, '^\s*<\?php', '')
  $c = [regex]::Replace($c, '^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*', '')
  $c = [regex]::Replace($c, '^\s*namespace\s+[^;]+;\s*', '')

  # Calcular namespace por ruta: src/<Top>/<Sub1>/.../<File>.php  =>  namespace Top\Sub1\...
  $parts = $rel -split '\\'
  $top   = $parts[0]
  $subs  = @()
  if ($parts.Count -gt 2) { $subs = $parts[1..($parts.Count-2)] }
  $ns = $top
  if ($subs.Count -gt 0) { $ns = $ns + '\' + ($subs -join '\') }

  # Cabecera correcta
  $header = "<?php declare(strict_types=1);`r`n`r`nnamespace $ns;`r`n`r`n"
  $c = $header + $c

  # Normalizar referencias típicas
  $c = $c -replace 'use\s+Src\\Models\\', 'use Models\\'
  $c = $c -replace 'new\s+Src\\Models\\', 'new Models\\'
  $c = $c -replace 'namespace\s+Src\\Models;', 'namespace Models;'

  if ($c -ne $orig) {
    # backup individual y escribir sin BOM
    $destB = Join-Path $backup $rel
    New-Item -ItemType Directory -Force -Path (Split-Path $destB) | Out-Null
    Copy-Item $f.FullName $destB -Force
    Write-NoBom $f.FullName $c
    Log "fixed: $rel  -> namespace $ns"
  }
}

# Renombrar duplicados típicos a su nombre canónico si no existe el canónico
$dupPatterns = @('* copy*.php','*_new.php')
foreach ($pat in $dupPatterns) {
  Get-ChildItem -Recurse -Path $srcDir -Filter $pat -File | ForEach-Object {
    $dir  = Split-Path $_.FullName
    $base = [System.IO.Path]::GetFileNameWithoutExtension($_.Name)
    $canonical = $base -replace '\s+copy(\s+\d+)?$','' -replace '_new$',''
    $destPath  = Join-Path $dir ($canonical + '.php')
    if (-not (Test-Path $destPath)) {
      $rel = $_.FullName.Substring($srcDir.Length).TrimStart('\')
      $destB = Join-Path $backup $rel
      New-Item -ItemType Directory -Force -Path (Split-Path $destB) | Out-Null
      Copy-Item $_.FullName $destB -Force
      Move-Item $_.FullName $destPath -Force
      Log "renamed: $rel -> $($destPath.Substring($srcDir.Length).TrimStart('\'))"
    } else {
      # Si existe el canónico, mueve duplicado a backup y elimina del src
      $rel = $_.FullName.Substring($srcDir.Length).TrimStart('\')
      $destB = Join-Path $backup $rel
      New-Item -ItemType Directory -Force -Path (Split-Path $destB) | Out-Null
      Move-Item $_.FullName $destB -Force
      Log "moved duplicate: $rel -> backup"
    }
  }
}

# Regenerar autoload y mostrar 3 checks
composer dump-autoload -o | Out-Null
Write-Host "Checks:"
php -r "require 'vendor/autoload.php'; echo class_exists('Utils\\ResponseHelper') ? 'Utils\\ResponseHelper OK' : 'Utils\\ResponseHelper NO'; echo PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('Firebase\\JWT\\JWT') ? 'JWT OK' : 'JWT NO'; echo PHP_EOL;"
php -r "require 'vendor/autoload.php'; echo class_exists('Models\\BaseModel') ? 'Models\\BaseModel OK' : 'Models\\BaseModel NO'; echo PHP_EOL;"

Write-Host "✅ Backup: $backup"
Write-Host "📄 Log:    $log"
