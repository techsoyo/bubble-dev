# Simple Smoke Test - Bubble of Talents v1.0

param(
  [string]$BaseUrl = "http://localhost:8000"
)

Write-Host "INICIANDO PRUEBA RAPIDA DE ENDPOINTS" -ForegroundColor Cyan
Write-Host "Base URL: $BaseUrl" -ForegroundColor White
Write-Host ""

$passed = 0
$failed = 0
$total = 0

function Test-Endpoint {
  param(
    [string]$Method,
    [string]$Endpoint,
    [int]$ExpectedStatus,
    [string]$Description
  )
    
  $script:total++
    
  try {
    $uri = "$BaseUrl$Endpoint"
    $headers = @{ "Content-Type" = "application/json" }
        
    $response = Invoke-WebRequest -Uri $uri -Method $Method -Headers $headers -TimeoutSec 10 -UseBasicParsing
    $status = $response.StatusCode
        
    if ($status -eq $ExpectedStatus) {
      Write-Host "PASS [$status] $Method $Endpoint - $Description" -ForegroundColor Green
      $script:passed++
    }
    else {
      Write-Host "FAIL [$status] $Method $Endpoint - $Description (Expected: $ExpectedStatus)" -ForegroundColor Red
      $script:failed++
    }
  }
  catch {
    # Capturar el codigo de estado de la excepcion si es HTTP error
    if ($_.Exception.Response) {
      $status = [int]$_.Exception.Response.StatusCode
      if ($status -eq $ExpectedStatus) {
        Write-Host "PASS [$status] $Method $Endpoint - $Description" -ForegroundColor Green
        $script:passed++
      }
      else {
        Write-Host "FAIL [$status] $Method $Endpoint - $Description (Expected: $ExpectedStatus)" -ForegroundColor Red
        $script:failed++
      }
    }
    else {
      Write-Host "ERROR $Method $Endpoint - $Description : $($_.Exception.Message)" -ForegroundColor Red
      $script:failed++
    }
  }
}

# TESTS BASICOS
Write-Host "EJECUTANDO TESTS BASICOS..." -ForegroundColor Yellow
Write-Host ""

# Health check
Test-Endpoint "GET" "/api/health" 200 "Health check"

# Auth (deberia dar 401 sin token)
Test-Endpoint "GET" "/api/auth/me" 401 "Usuario actual (sin auth)"

# Trabajos publicos
Test-Endpoint "GET" "/api/jobs" 200 "Lista de trabajos"

# AI endpoints
Test-Endpoint "GET" "/api/ai/health" 200 "AI Health check"

# CORS preflight
Test-Endpoint "OPTIONS" "/api/health" 204 "CORS preflight"

# RESUMEN
Write-Host ""
Write-Host "RESUMEN DE RESULTADOS" -ForegroundColor Cyan
Write-Host "==================================="
Write-Host "Passed: $script:passed" -ForegroundColor Green
Write-Host "Failed: $script:failed" -ForegroundColor Red
Write-Host "Total:  $script:total" -ForegroundColor White

$successRate = if ($script:total -gt 0) { [Math]::Round(($script:passed * 100) / $script:total, 1) } else { 0 }
Write-Host "Success Rate: $successRate%" -ForegroundColor Cyan
Write-Host "==================================="

if ($script:failed -eq 0) {
  Write-Host "ALL TESTS PASSED!" -ForegroundColor Green
}
else {
  Write-Host "SOME TESTS FAILED" -ForegroundColor Yellow
}
