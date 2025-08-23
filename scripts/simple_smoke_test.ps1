# =================================================================================
# SIMPLE SMOKE TEST - BUBBLE OF TALENTS v1.0 - QUICK CHECK
# =================================================================================

param(
  [string]$BaseUrl = "http://localhost:8000"
)

Write-Host "🚀 INICIANDO PRUEBA RÁPIDA DE ENDPOINTS" -ForegroundColor Cyan
Write-Host "🌐 Base URL: $BaseUrl" -ForegroundColor White
Write-Host ""

$passed = 0
$failed = 0
$total = 0

function Test-Endpoint {
  param(
    [string]$Method,
    [string]$Endpoint,
    [int]$ExpectedStatus,
    [string]$Description,
    [string]$Body = ""
  )
    
  $total++
    
  try {
    $uri = "$BaseUrl$Endpoint"
    $headers = @{ "Content-Type" = "application/json" }
        
    $params = @{
      Uri                = $uri
      Method             = $Method
      Headers            = $headers
      TimeoutSec         = 10
      SkipHttpErrorCheck = $true
    }
        
    if ($Body -ne "") {
      $params.Body = $Body
    }
        
    $response = Invoke-WebRequest @params
    $status = $response.StatusCode
        
    if ($status -eq $ExpectedStatus) {
      Write-Host "✅ PASS [$status] $Method $Endpoint - $Description" -ForegroundColor Green
      $script:passed++
    }
    else {
      Write-Host "❌ FAIL [$status] $Method $Endpoint - $Description (Expected: $ExpectedStatus)" -ForegroundColor Red
      $script:failed++
    }
  }
  catch {
    Write-Host "❌ ERROR $Method $Endpoint - $Description : $($_.Exception.Message)" -ForegroundColor Red
    $script:failed++
  }
}

# === TESTS BÁSICOS ===
Write-Host "📋 EJECUTANDO TESTS BÁSICOS..." -ForegroundColor Yellow
Write-Host ""

# Health check
Test-Endpoint "GET" "/api/health" 200 "Health check"

# Sistema
Test-Endpoint "GET" "/api/status" 200 "Status del sistema"
Test-Endpoint "GET" "/api/version" 200 "Versión del sistema"

# Auth (deberían dar 401 sin token)
Test-Endpoint "GET" "/api/auth/me" 401 "Usuario actual (sin auth)"

# Trabajos públicos
Test-Endpoint "GET" "/api/jobs" 200 "Lista de trabajos"
Test-Endpoint "GET" "/api/jobs/job-100" 200 "Trabajo específico"
Test-Endpoint "POST" "/api/jobs/search" 200 "Búsqueda de trabajos"

# Candidatos (algunos públicos, otros privados)
Test-Endpoint "GET" "/api/candidates" 401 "Lista candidatos (privado)"
Test-Endpoint "POST" "/api/candidates/register" 422 "Registro (sin datos)"

# AI endpoints
Test-Endpoint "GET" "/api/ai/health" 200 "AI Health check"
Test-Endpoint "POST" "/api/ai/chatbot" 422 "AI Chatbot (sin datos)"

# Idiomas
Test-Endpoint "GET" "/api/language" 200 "Obtener idioma"
Test-Endpoint "POST" "/api/language" 200 "Establecer idioma"

# CORS preflight
Test-Endpoint "OPTIONS" "/api/health" 204 "CORS preflight"

# === RESUMEN ===
Write-Host ""
Write-Host "📊 RESUMEN DE RESULTADOS" -ForegroundColor Cyan
Write-Host "==================================="
Write-Host "✅ Passed: $script:passed" -ForegroundColor Green
Write-Host "❌ Failed: $script:failed" -ForegroundColor Red
Write-Host "📈 Total:  $total" -ForegroundColor White

$successRate = if ($total -gt 0) { [Math]::Round(($script:passed * 100) / $total, 1) } else { 0 }
Write-Host "🎯 Success Rate: $successRate%" -ForegroundColor Cyan
Write-Host "==================================="

if ($script:failed -eq 0) {
  Write-Host "🎉 ALL TESTS PASSED!" -ForegroundColor Green
  exit 0
}
else {
  Write-Host "⚠️ SOME TESTS FAILED" -ForegroundColor Yellow
  exit 1
}
