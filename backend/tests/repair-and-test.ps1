<# ===========================
 repair-and-test.ps1
 Todo-en-uno: normaliza endpoints, test CORS, login y bulk update
 =========================== #>

# ======= CONFIG =======
# PREVIEW: Si es $true, solo muestra “Would update…”. Pon $false para aplicar.
$DRY = $true

# URLs locales (ajusta si usas otros puertos)
$FrontendOrigin = "http://localhost:3002"
$BackendBase    = "http://localhost:8000"

# Credenciales de prueba para staff-login (AJUSTA)
$StaffEmail    = "admin@bubblegum.agency"   # el endpoint fuerza este dominio salvo que cambies .env (STAFF_EMAIL_DOMAIN)
$StaffPassword = "TU_PASSWORD"

# ======= UTILS =======
function Write-Utf8NoBom { param([string]$Path,[string[]]$Lines)
  $enc = New-Object System.Text.UTF8Encoding($false)
  [System.IO.File]::WriteAllLines($Path,$Lines,$enc)
}

function Remove-BootCheckBlock {
  param([string[]]$Lines)
  $out = New-Object System.Collections.Generic.List[string]
  $i = 0; $n = $Lines.Count
  while ($i -lt $n) {
    $line = $Lines[$i]

    # Quita $ROOT= y $BOOT=
    if ($line -match '^\s*\$ROOT\s*=') { $i++; continue }
    if ($line -match '^\s*\$BOOT\s*=') { $i++; continue }

    # Quita if (!is_file($BOOT)) { ... }
    if ($line -match 'if\s*\(\s*!is_file\(\s*\$BOOT\s*\)\s*\)\s*\{') {
      $depth = 0
      while ($i -lt $n) {
        if ($Lines[$i] -match '\{') { $depth++ }
        if ($Lines[$i] -match '\}') { $depth-- }
        $i++
        if ($depth -le 0) { break }
      }
      continue
    }

    $out.Add($line); $i++
  }
  ,$out.ToArray()
}

function Normalize-Endpoints {
  Write-Host "== Normalizando endpoints (public/api) ==" -ForegroundColor Cyan
  $apiRoot = (Resolve-Path .\backend\public\api)

  # Raíz (excepto el propio bootstrap.php)
  Get-ChildItem -Path $apiRoot -File -Filter *.php |
    Where-Object { $_.Name -ne 'bootstrap.php' } |
    ForEach-Object {
      $p = $_.FullName
      $lines = Get-Content $p

      $norm = $lines | ForEach-Object {
        $_ `
          -replace "require_once\s*__DIR__\s*\.\s*'/_?bootstrap\.php'\s*;", "require_once __DIR__ . '/bootstrap.php';" `
          -replace 'require_once\s*__DIR__\s*\.\s*"/_?bootstrap\.php"\s*;', 'require_once __DIR__ . "/bootstrap.php";' `
          -replace "require_once\s*__DIR__\s*\.\s*'/\.\./\.\./config/bootstrap\.php'\s*;", "require_once __DIR__ . '/bootstrap.php';" `
          -replace 'require_once\s*__DIR__\s*\.\s*"/\.\./\.\./config/bootstrap\.php"\s*;', 'require_once __DIR__ . "/bootstrap.php";'
      }

      $norm = Remove-BootCheckBlock -Lines $norm

      if ($DRY) {
        if (($norm -join "`n") -ne ($lines -join "`n")) { Write-Host "Would update (root): $p" }
      } else {
        Write-Utf8NoBom -Path $p -Lines $norm
        Write-Host "Updated (root): $p"
      }
    }

  # Subcarpetas
  Get-ChildItem -Recurse -Path $apiRoot -File -Filter *.php |
    Where-Object { $_.DirectoryName -ne $apiRoot.Path } |
    ForEach-Object {
      $p = $_.FullName
      $lines = Get-Content $p

      $norm = $lines | ForEach-Object {
        $_ `
          -replace "require_once\s*__DIR__\s*\.\s*'/_?bootstrap\.php'\s*;", "require_once dirname(__DIR__) . '/bootstrap.php';" `
          -replace 'require_once\s*__DIR__\s*\.\s*"/_?bootstrap\.php"\s*;', 'require_once dirname(__DIR__) . "/bootstrap.php";' `
          -replace "require_once\s*__DIR__\s*\.\s*'/\.\./\.\./config/bootstrap\.php'\s*;", "require_once dirname(__DIR__) . '/bootstrap.php';" `
          -replace 'require_once\s*__DIR__\s*\.\s*"/\.\./\.\./config/bootstrap\.php"\s*;', 'require_once dirname(__DIR__) . "/bootstrap.php";' `
          -replace "require_once\s*dirname\(__DIR__\s*,\s*\d+\)\s*\.\s*'/config/bootstrap\.php'\s*;", "require_once dirname(__DIR__) . '/bootstrap.php';" `
          -replace 'require_once\s*dirname\(__DIR__\s*,\s*\d+\)\s*\.\s*"/config/bootstrap\.php"\s*;', 'require_once dirname(__DIR__) . "/bootstrap.php";'
      }

      $norm = Remove-BootCheckBlock -Lines $norm

      if ($DRY) {
        if (($norm -join "`n") -ne ($lines -join "`n")) { Write-Host "Would update (subdir): $p" }
      } else {
        Write-Utf8NoBom -Path $p -Lines $norm
        Write-Host "Updated (subdir): $p"
      }
    }

  # Limpia restos ($BOOT, vendor/autoload, src/Utils/* includes directos)
  Get-ChildItem -Recurse $apiRoot -Filter *.php | ForEach-Object {
    $p = $_.FullName
    $lines = Get-Content $p
    $clean = $lines | Where-Object {
      $_ -notmatch 'require_once\s*\$BOOT\s*;' -and
      $_ -notmatch 'require_once.*vendor/autoload\.php' -and
      $_ -notmatch 'require_once.*?/src/Utils/.*?\.php'
    }
    if (($clean -join "`n") -ne ($lines -join "`n")) {
      if ($DRY) {
        Write-Host "Would clean: $p"
      } else {
        [System.IO.File]::WriteAllLines($p, $clean, (New-Object System.Text.UTF8Encoding($false)))
        Write-Host "Cleaned: $p"
      }
    }
  }

  # Sanity checks
  Write-Host "`n--- Verificación rápida ---" -ForegroundColor Yellow
  $left1 = Get-ChildItem -Recurse $apiRoot -Filter *.php | Select-String "config/bootstrap\.php" | Select-Object -ExpandProperty Path -Unique
  if ($left1) { Write-Host "Aún apuntan a config/bootstrap.php:" -ForegroundColor Red; $left1 | ForEach-Object { "  $_" } }
  else { Write-Host "OK: Sin includes directos a config/bootstrap.php" -ForegroundColor Green }

  $left2 = Get-ChildItem -Recurse $apiRoot -Filter *.php | Select-String 'require_once\s*\$BOOT|vendor/autoload\.php|/src/Utils/' | Select-Object -ExpandProperty Path -Unique
  if ($left2) { Write-Host "Restos (BOOT/vendor/Utils) encontrados:" -ForegroundColor Red; $left2 | ForEach-Object { "  $_" } }
  else { Write-Host "OK: Sin restos de BOOT/vendor/Utils directos" -ForegroundColor Green }
}

function Test-Preflight {
  param([string]$Path = "/api/jobs.php")
  Write-Host "`n== Preflight CORS: $Path ==" -ForegroundColor Cyan
  $url = "$BackendBase$Path"
  try {
    $cmd = @(
      "curl.exe","-i","-X","OPTIONS",$url,
      "-H","Origin: $FrontendOrigin",
      "-H","Access-Control-Request-Method: POST",
      "-H","Access-Control-Request-Headers: content-type, authorization"
    )
    $res = & $cmd
    $res | ForEach-Object { $_ }  # imprime headers y status
  } catch {
    Write-Host "Error lanzando curl.exe: $($_.Exception.Message)" -ForegroundColor Red
  }
}

function Invoke-StaffLogin {
  param([string]$Email,[string]$Password)
  Write-Host "`n== Login staff ==" -ForegroundColor Cyan

  # OJO: el endpoint exige action=staff_login (si no, responde 'Acción no válida')
  $body = @{ action="staff_login"; email=$Email; password=$Password } | ConvertTo-Json
  try {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $login = Invoke-RestMethod -WebSession $session -Method POST `
      -Uri "$BackendBase/api/auth/staff-login.php" `
      -ContentType "application/json" -Body $body

    # Maneja variantes { token } o { data: { token } }
    $token = $null
    if ($login.PSObject.Properties.Name -contains 'token') {
      $token = $login.token
    } elseif ($login.PSObject.Properties.Name -contains 'data' -and $login.data.PSObject.Properties.Name -contains 'token') {
      $token = $login.data.token
    }

    if (-not $token) {
      Write-Host "Login sin token. Respuesta:" -ForegroundColor Yellow
      $login | ConvertTo-Json -Depth 6 | Write-Host
    } else {
      Write-Host "Token OK (truncado): $($token.Substring(0,24))..." -ForegroundColor Green
    }

    return @{ token=$token; session=$session }
  } catch {
    $resp = $_.Exception.Response
    if ($resp) {
      $reader = New-Object IO.StreamReader($resp.GetResponseStream())
      $txt = $reader.ReadToEnd()
      Write-Host "Login ERROR => $txt" -ForegroundColor Red
    } else {
      Write-Host "Login ERROR => $($_.Exception.Message)" -ForegroundColor Red
    }
    return $null
  }
}

function Invoke-BulkUpdateApplications {
  param(
    [Parameter(Mandatory=$true)][string]$Token,
    [Parameter(Mandatory=$true)][object[]]$Updates,
    [Microsoft.PowerShell.Commands.WebRequestSession]$Session
  )
  Write-Host "`n== Bulk update applications ==" -ForegroundColor Cyan

  $payload = @{ bulk_update = $Updates } | ConvertTo-Json -Depth 5
  $headers = @{ Authorization = "Bearer $Token"; Accept = "application/json" }

  try {
    $res = Invoke-RestMethod -WebSession $Session -Method POST `
      -Uri "$BackendBase/api/applications.php" `
      -Headers $headers -ContentType "application/json" -Body $payload
    "Respuesta:" | Write-Host
    $res | ConvertTo-Json -Depth 6 | Write-Host
  } catch {
    $resp = $_.Exception.Response
    if ($resp) {
      $reader = New-Object IO.StreamReader($resp.GetResponseStream())
      $txt = $reader.ReadToEnd()
      Write-Host "Bulk update ERROR => $txt" -ForegroundColor Red
    } else {
      Write-Host "Bulk update ERROR => $($_.Exception.Message)" -ForegroundColor Red
    }
  }
}

# ======= RUN =======
Normalize-Endpoints

# Test CORS (preflight)
Test-Preflight -Path "/api/jobs.php"

# Login + bulk update (ejemplo)
$loginRes = Invoke-StaffLogin -Email $StaffEmail -Password $StaffPassword
if ($loginRes -and $loginRes.token) {
  $updates = @(
    @{ id = 123; status = "review"   },
    @{ id = 456; status = "rejected" }
  )
  Invoke-BulkUpdateApplications -Token $loginRes.token -Updates $updates -Session $loginRes.session
} else {
  Write-Host "`nOmito bulk update: no hay token." -ForegroundColor Yellow
  Write-Host "Si necesitas loguearte con email NO corporativo, en tu .env pon:  STAFF_EMAIL_DOMAIN=" -ForegroundColor Yellow
  Write-Host "(cadena vacía) o cambia el dominio a tu email de pruebas." -ForegroundColor Yellow
}