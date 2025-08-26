# repair_project_final.ps1
# Uso:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\repair_project_final.ps1
$ErrorActionPreference = 'Stop'

# Rutas base
$root = (Get-Location).Path
if (!(Test-Path "$root\composer.json")) { throw "Ejecuta este script desde la carpeta 'backend' (donde está composer.json)." }
$srcDir = Join-Path $root 'src'


# Backup + log
$ts = Get-Date -Format yyyyMMdd_HHmmss
$backup = Join-Path $root "backup_$ts"
New-Item -ItemType Directory -Force -Path $backup | Out-Null
$log    = Join-Path $backup "psr4_fix_$ts.log"

function Log($m) { $m | Tee-Object -FilePath $log -Append }
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
function Write-NoBom($p, $c) { [System.IO.File]::WriteAllText($p, $c, $Utf8NoBom) }

Log "== START $ts =="

# 1) JWT ^6.0 y autoload optimizado
Log "1) composer require firebase/php-jwt"
try { composer remove firebase/php-jwt --no-interaction | Out-Null } catch {}
composer require firebase/php-jwt --no-interaction | Tee-Object -FilePath $log -Append | Out-Null
composer dump-autoload -o | Tee-Object -FilePath $log -Append | Out-Null

# === 2) Duplicados: * copy*.php y *_new.php
#     - Si existe archivo canónico => mover duplicado a backup
#     - Si NO existe canónico => renombrar a canónico
Log "2) Resolviendo duplicados (* copy*.php, *_new.php)"
$dupFiles = @()

$dupFiles += Get-ChildItem -Recurse -Path $srcDir -Filter "* copy*.php" -ErrorAction SilentlyContinue
$dupFiles += Get-ChildItem -Recurse -Path $srcDir -Filter "*_new.php"   -ErrorAction SilentlyContinue

foreach ($f in $dupFiles) {
  $dir = Split-Path $f.FullName
  $base = [System.IO.Path]::GetFileNameWithoutExtension($f.Name)

  # Canonical: quitar " copy", " copy NN", y sufijo "_new"
  $canonical = $base -replace '\s+copy(\s+\d+)?$', '' -replace '_new$', ''
  $destPath = Join-Path $dir ($canonical + '.php')

  if (Test-Path $destPath) {
    # Ya existe el canónico: mover duplicado al backup
    $rel = $f.FullName.Substring($root.Length).TrimStart('\')
    $bDest = Join-Path $backup $rel
    New-Item -ItemType Directory -Force -Path (Split-Path $bDest) | Out-Null
    Move-Item $f.FullName $bDest -Force
    Log "moved duplicate -> $rel"
  }
  else {
    # No existe canónico: renombrar al canónico
    $rel = $f.FullName.Substring($root.Length).TrimStart('\')
    Move-Item $f.FullName $destPath -Force
    Log "renamed -> $rel => $($destPath.Substring($root.Length).TrimStart('\'))"
  }
}

# === 3) Normalizar cabecera y namespace PSR-4 en TODOS los src/**/*.php
#     - Cabecera exacta: <?php declare(strict_types=1);
#     - Namespace calculado por ruta (con subcarpetas)
#     - Fix 'use/new Src\Models\' -> 'Models\'
Log "3) Normalizando cabeceras y namespaces"
$srcFiles = Get-ChildItem -Recurse -Path $srcDir -Include *.php -ErrorAction SilentlyContinue
 
foreach ($f in $srcFiles) {
  $orig = Get-Content $f.FullName -Raw
  $c = $orig
  # Quitar BOM y apertura previa
  $c = [regex]::Replace($c, '^\uFEFF', '')
  $c = [regex]::Replace($c, '^\s*<\?php', '')        # elimina apertura anterior
  $c = [regex]::Replace($c, '^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*', '')  # elimina declare anterior
  $c = [regex]::Replace($c, '^\s*namespace\s+[^;]+;\s*', '') # elimina primer namespace si hay

  # Namespace calculado por ruta
  $rel = $f.FullName.Substring($srcDir.Length).TrimStart('\')
  $parts = $rel -split '\\'
  if ($parts.Count -lt 2) { continue } # fuera de src/<Top>/
  $top = $parts[0]                     # Models | Utils | Parsers | Middleware | Controllers | ...
  $sub = $parts[1..($parts.Count - 2)]   # subnamespaces (si hay)
  $ns = $top
  if ($sub.Count -gt 0) { $ns = $ns + '\' + ($sub -join '\') }

  # Ensamblar cabecera correcta
  $header = "<?php declare(strict_types=1);`r`n`r`nnamespace $ns;`r`n`r`n"
  $c = $header + $c


  # Fix de referencias Src\Models
  $c = $c -replace 'use\s+Src\\Models\\', 'use Models\\'
  $c = $c -replace 'new\s+Src\\Models\\', 'new Models\\'
  $c = $c -replace 'namespace\s+Src\\Models;', 'namespace Models;'

  if ($c -ne $orig) {
    # backup individual y escritura sin BOM
    $relFile = $f.FullName.Substring($root.Length).TrimStart('\')
    $bDest = Join-Path $backup $relFile
    New-Item -ItemType Directory -Force -Path (Split-Path $bDest) | Out-Null
    Copy-Item $f.FullName $bDest -Force
    Write-NoBom $f.FullName $c
    Log "fixed ns: $relFile -> $ns"
  }
}

# === 4) Recompilar autoload y checks clave ===
Log "4) composer dump-autoload -o"
composer dump-autoload -o | Tee-Object -FilePath $log -Append | Out-Null

Log "4.1) Comprobación JWT class"
& php -r "require 'vendor/autoload.php'; echo class_exists('Firebase\\JWT\\JWT') ? 'JWT OK' : 'JWT NO'; echo PHP_EOL;" | Tee-Object -FilePath $log -Append
Log "4.2) Comprobación Utils\\JWTHelper (si existe)"
& php -r "require 'vendor/autoload.php'; echo class_exists('Utils\\JWTHelper') ? 'JWTHelper OK' : 'JWTHelper NO'; echo PHP_EOL;" | Tee-Object -FilePath $log -Append
Log "4.3) Comprobación Utils\\JWTMiddleware (si existe)"
& php -r "require 'vendor/autoload.php'; echo class_exists('Utils\\JWTMiddleware') ? 'JWTMiddleware OK' : 'JWTMiddleware NO'; echo PHP_EOL;" | Tee-Object -FilePath $log -Append

Write-Host "✅ Hecho. Backup: $backup" -ForegroundColor Green
Write-Host "📄 Log: $log" -ForegroundColor Yellow
