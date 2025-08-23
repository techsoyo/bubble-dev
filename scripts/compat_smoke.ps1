# scripts/compat_smoke.ps1
# Smoke basico compatible con Windows PowerShell 5.1
# Uso:
#   powershell -ExecutionPolicy Bypass -File .\scripts\compat_smoke.ps1 -BaseUrl "http://localhost:8000" -AuthCookie "token=eyJ..."

param(
  [string]$BaseUrl = "http://localhost:8000",
  [string]$AuthCookie = "",
  [int]$TimeoutSec = 15,
  [switch]$VerboseMode
)

function Write-Info([string]$msg) { Write-Host ("[INFO]  {0}" -f $msg) -ForegroundColor Cyan }
function Write-Ok([string]$msg) { Write-Host ("[PASS]  {0}" -f $msg) -ForegroundColor Green }
function Write-Warn([string]$msg) { Write-Host ("[WARN]  {0}" -f $msg) -ForegroundColor Yellow }
function Write-Err([string]$msg) { Write-Host ("[FAIL]  {0}" -f $msg) -ForegroundColor Red }

function Invoke-ApiCompat {
  param(
    [string]$Method,
    [string]$Path,
    [int]$Expected,
    [string]$Desc,
    [string]$BodyJson = $null,
    [hashtable]$Headers = $null
  )

  $url = "$BaseUrl$Path"
  $hdrs = @{}
  if ($Headers) { $Headers.GetEnumerator() | ForEach-Object { $hdrs[$_.Key] = $_.Value } }
  if (-not $hdrs.ContainsKey("Content-Type")) { $hdrs["Content-Type"] = "application/json" }
  if ($AuthCookie -ne "") { $hdrs["Cookie"] = $AuthCookie }

  $sw = [System.Diagnostics.Stopwatch]::StartNew()
  try {
    if ($BodyJson -and ($Method -in @("POST", "PUT", "PATCH"))) {
      $resp = Invoke-WebRequest -Uri $url -Method $Method -Body $BodyJson -Headers $hdrs -TimeoutSec $TimeoutSec -UseBasicParsing -ErrorAction Stop
    }
    else {
      $resp = Invoke-WebRequest -Uri $url -Method $Method -Headers $hdrs -TimeoutSec $TimeoutSec -UseBasicParsing -ErrorAction Stop
    }
    $sw.Stop()
    $status = [int]$resp.StatusCode
  }
  catch {
    $sw.Stop()
    # En Windows PowerShell 5.1, cuando el codigo es 4xx/5xx lanza excepcion
    $status = 0
    try {
      if ($_.Exception.Response -ne $null) {
        $status = [int]$_.Exception.Response.StatusCode.value__
      }
    }
    catch { $status = 0 }
  }

  $sec = [math]::Round($sw.Elapsed.TotalSeconds, 2)
  if ($VerboseMode) { Write-Info ("{0} {1} -> HTTP {2} in {3}s" -f $Method, $Path, $status, $sec) }

  # CORS: acepta 204 para OPTIONS
  if ($Method -eq "OPTIONS" -and $status -eq 204 -and $Expected -eq 204) {
    Write-Ok ("OPTIONS {0} [{1}] - {2}" -f $Path, $status, $Desc)
    return @{ ok = $true }
  }

  if ($status -eq $Expected) {
    if ($sec -gt 0.80) { Write-Warn ("{0} {1} [{2}] ({3}s) - {4}" -f $Method, $Path, $status, $sec, $Desc); return @{ ok = $true; warn = $true } }
    else { Write-Ok ("{0} {1} [{2}] ({3}s) - {4}" -f $Method, $Path, $status, $sec, $Desc); return @{ ok = $true } }
  }
  else {
    Write-Err ("{0} {1} - Expected {2} but got {3} - {4}" -f $Method, $Path, $Expected, $status, $Desc)
    return @{ ok = $false }
  }
}

# ---------- Lista de pruebas (puedes ampliar segun tu matriz) ----------
$tests = @(
  # Salud del sistema
  @{ Method = "GET"; Path = "/api/health"; Expected = 200; Desc = "Health principal" }

  # AI modulo
  @{ Method = "GET"; Path = "/api/ai/health"; Expected = 200; Desc = "AI health" }
  @{ Method = "POST"; Path = "/api/ai/chatbot"; Expected = 200; Desc = "Chatbot funcional"; Body = '{"message":"hello"}' }

  # Auth (sin cookie => 401)
  @{ Method = "GET"; Path = "/api/auth/me"; Expected = 401; Desc = "Usuario actual (sin auth)" }

  # Jobs publicos
  @{ Method = "GET"; Path = "/api/jobs"; Expected = 200; Desc = "Listar trabajos" }
  @{ Method = "GET"; Path = "/api/jobs/available"; Expected = 200; Desc = "Trabajos disponibles" }
  @{ Method = "POST"; Path = "/api/jobs/search"; Expected = 200; Desc = "Buscar trabajos"; Body = '{}' }

  # Candidates (ajusta segun tu diseno)
  @{ Method = "GET"; Path = "/api/candidates"; Expected = 200; Desc = "Lista candidatos (publico por ahora)" }

  # Departamentos
  @{ Method = "GET"; Path = "/api/departments"; Expected = 200; Desc = "Lista departamentos" }

  # Skills
  @{ Method = "GET"; Path = "/api/skills"; Expected = 200; Desc = "Lista skills" }

  # Notificaciones
  @{ Method = "GET"; Path = "/api/notifications"; Expected = 200; Desc = "Listar notificaciones (publico por ahora)" }

  # Idioma
  @{ Method = "GET"; Path = "/api/language"; Expected = 200; Desc = "Get language" }
  @{ Method = "POST"; Path = "/api/language"; Expected = 200; Desc = "Set language"; Body = '{"language":"es"}' }

  # CORS preflight (aceptamos 204)
  @{ Method = "OPTIONS"; Path = "/api/ai/chatbot"; Expected = 204; Desc = "Preflight AI chat" }
  @{ Method = "OPTIONS"; Path = "/api/auth/login"; Expected = 204; Desc = "Preflight login" }
)

# ------------------ Ejecucion ------------------
$Total = $tests.Count
$Passed = 0
$Failed = 0
$Warnings = 0

Write-Info ("INICIANDO SMOKE COMPATIBLE")
Write-Info ("Base URL: {0}" -f $BaseUrl)
Write-Info ("Timeout : {0}s" -f $TimeoutSec)
if ($AuthCookie -ne "") { Write-Info ("Cookie : SET") } else { Write-Info ("Cookie : NOT SET") }
Write-Host ""

foreach ($t in $tests) {
  $r = Invoke-ApiCompat -Method $t.Method -Path $t.Path -Expected $t.Expected -Desc $t.Desc -BodyJson ($t.Body)
  if ($r.ok) {
    $Passed++
    if ($r.warn) { $Warnings++ }
  }
  else {
    $Failed++
  }
}

Write-Host ""
Write-Host "RESUMEN" -ForegroundColor Yellow
Write-Host ("Passed  : {0}" -f $Passed)
Write-Host ("Failed  : {0}" -f $Failed)
Write-Host ("Warnings: {0}" -f $Warnings)
Write-Host ("Total   : {0}" -f $Total)
if ($Total -gt 0) {
  $rate = [math]::Round(($Passed * 100.0) / $Total, 1)
  Write-Host ("Success : {0}%" -f $rate)
}

if ($Failed -gt 0) { exit 1 } else { exit 0 }
