<#
.SYNOPSIS
  Lista endpoints PHP bajo backend/public/api (incluye subcarpetas).
  Genera:
    - endpoints_list.txt      (lista plana ordenada)
    - endpoints_by_dir.txt    (agrupado por subcarpeta inmediata de 'api')
    - endpoints_list.json     (jerárquico) si se pasa -Json

.USAGE
  .\list_endpoints.ps1
  .\list_endpoints.ps1 -Json
#>

param(
  [switch]$Json
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Get-Location).Path
$apiRootRel  = 'backend\public\api'
$apiRootAbs  = Join-Path $projectRoot $apiRootRel

if (-not (Test-Path $apiRootAbs)) {
  throw "No existe la ruta esperada: $apiRootRel. Ejecuta el script desde la raíz del proyecto."
}

function Normalize-RelPath([string]$full) {
  return $full.Replace($projectRoot + '\','').Replace('\','/')
}

# 1) Recolectar PHP recursivamente (excluye directorios comunes no relevantes)
$excludeDirs = @('\vendor\','\node_modules\','\tests\','\test\','\__mocks__\')
$files = Get-ChildItem -Path $apiRootAbs -Recurse -Filter *.php -File -ErrorAction SilentlyContinue |
  Where-Object {
    $full = $_.FullName
    -not ($excludeDirs | Where-Object { $full -like "*$_*" })
  }

if (-not $files) {
  throw "No se encontraron archivos .php bajo $apiRootRel."
}

# 2) Normalizar a rutas relativas POSIX y ordenar
$rel = $files |
  Select-Object -ExpandProperty FullName |
  ForEach-Object { Normalize-RelPath $_ } |
  Sort-Object -Unique

# 3) Salida TXT (lista plana)
$txtOut = Join-Path $projectRoot 'endpoints_list.txt'
$rel | Out-File -FilePath $txtOut -Encoding UTF8

# 4) Agrupado por subcarpeta inmediata de 'api'
#    Ej.: backend/public/api/auth/login.php -> grupo "auth"
$grouped = @()
foreach ($path in $rel) {
  if ($path -match '(?i)^backend/public/api/(.+)$') {
    $underApi     = $Matches[1]
    $firstSegment = ($underApi -split '/')[0]
    $grouped += [PSCustomObject]@{ Group = $firstSegment; Path = $path }
  }
}
$byDir = $grouped | Group-Object Group | Sort-Object Name

$byDirOut = Join-Path $projectRoot 'endpoints_by_dir.txt'
"=== ENDPOINTS AGRUPADOS POR SUBCARPETA INMEDIATA DE 'api' ===" | Out-File $byDirOut -Encoding UTF8
foreach ($g in $byDir) {
  "`n[$($g.Name)]" | Out-File $byDirOut -Append -Encoding UTF8
  ($g.Group | Select-Object -ExpandProperty Path | Sort-Object) | Out-File $byDirOut -Append -Encoding UTF8
}

# 5) (Opcional) JSON jerárquico
if ($Json) {
  $tree = @{}
  foreach ($path in $rel) {
    if ($path -match '(?i)^(backend/public/api)/(.*)$') {
      $tail = $Matches[2]
      $segments = $tail -split '/'
      $cursor = $tree
      for ($i=0; $i -lt ($segments.Count - 1); $i++) {
        $seg = $segments[$i]
        if (-not $cursor.ContainsKey($seg)) { $cursor[$seg] = @{} }
        $cursor = $cursor[$seg]
      }
      if (-not $cursor.ContainsKey('_files')) { $cursor['_files'] = @() }
      $cursor['_files'] += $segments[-1]
    }
  }
  $jsonObj = [PSCustomObject]@{
    apiRoot = 'backend/public/api'
    tree    = $tree
  }
  $jsonOut = Join-Path $projectRoot 'endpoints_list.json'
  ($jsonObj | ConvertTo-Json -Depth 50) | Out-File -FilePath $jsonOut -Encoding UTF8
}

Write-Host "✅ endpoints_list.txt generado: $txtOut" -ForegroundColor Green
Write-Host "✅ endpoints_by_dir.txt generado: $byDirOut" -ForegroundColor Green
if ($Json) { Write-Host "✅ endpoints_list.json generado" -ForegroundColor Green }
Write-Host ("Total endpoints PHP: {0}" -f $rel.Count) -ForegroundColor Yellow
$rel | Select-Object -First 10 | ForEach-Object { Write-Host " - $_" }
