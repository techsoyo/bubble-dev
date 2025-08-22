# Script de Validación de Headers de Seguridad
# Uso: .\validate_security.ps1 https://tudominio.com

param(
  [Parameter(Mandatory = $true)]
  [string]$Url
)

Write-Host "=== VALIDACIÓN DE HEADERS DE SEGURIDAD ===" -ForegroundColor Green
Write-Host "URL: $Url" -ForegroundColor Cyan
Write-Host ""

function Test-SecurityHeader {
  param($HeaderName, $ExpectedValue, $Headers)
    
  $header = $Headers[$HeaderName]
  if ($header) {
    Write-Host "✅ $HeaderName" -ForegroundColor Green
    Write-Host "   Valor: $header" -ForegroundColor White
        
    if ($ExpectedValue -and $header -notmatch $ExpectedValue) {
      Write-Host "   ⚠️  No coincide con el valor esperado" -ForegroundColor Yellow
    }
  }
  else {
    Write-Host "❌ $HeaderName: AUSENTE" -ForegroundColor Red
  }
  Write-Host ""
}

try {
  # Realizar petición HTTP
  $response = Invoke-WebRequest -Uri $Url -Method HEAD -ErrorAction Stop
  $headers = $response.Headers
    
  Write-Host "=== ANÁLISIS DE HEADERS ===" -ForegroundColor Yellow
    
  # Verificar headers críticos de seguridad
  Test-SecurityHeader -HeaderName "Content-Security-Policy" -ExpectedValue "default-src 'self'" -Headers $headers
  Test-SecurityHeader -HeaderName "X-Frame-Options" -ExpectedValue "DENY" -Headers $headers
  Test-SecurityHeader -HeaderName "X-Content-Type-Options" -ExpectedValue "nosniff" -Headers $headers
  Test-SecurityHeader -HeaderName "Referrer-Policy" -ExpectedValue "strict-origin" -Headers $headers
  Test-SecurityHeader -HeaderName "Permissions-Policy" -Headers $headers
  Test-SecurityHeader -HeaderName "X-XSS-Protection" -ExpectedValue "1; mode=block" -Headers $headers
  Test-SecurityHeader -HeaderName "Strict-Transport-Security" -Headers $headers
    
  Write-Host "=== HEADERS DE CACHE ===" -ForegroundColor Yellow
  Test-SecurityHeader -HeaderName "Cache-Control" -Headers $headers
    
  Write-Host "=== VERIFICACIÓN CSP ===" -ForegroundColor Magenta
  $csp = $headers["Content-Security-Policy"]
  if ($csp) {
    if ($csp -match "sha256-") {
      Write-Host "✅ CSP contiene hashes SHA256" -ForegroundColor Green
    }
    else {
      Write-Host "⚠️  CSP no contiene hashes SHA256" -ForegroundColor Yellow
    }
        
    if ($csp -match "'unsafe-inline'.*script-src") {
      Write-Host "❌ CSP permite script-src unsafe-inline (INSEGURO)" -ForegroundColor Red
    }
    else {
      Write-Host "✅ CSP no permite script-src unsafe-inline" -ForegroundColor Green
    }
  }
    
  Write-Host "=== PUNTUACIÓN DE SEGURIDAD ===" -ForegroundColor Cyan
    
  $securityScore = 0
  $maxScore = 7
    
  if ($headers["Content-Security-Policy"]) { $securityScore++ }
  if ($headers["X-Frame-Options"]) { $securityScore++ }
  if ($headers["X-Content-Type-Options"]) { $securityScore++ }
  if ($headers["Referrer-Policy"]) { $securityScore++ }
  if ($headers["Permissions-Policy"]) { $securityScore++ }
  if ($headers["X-XSS-Protection"]) { $securityScore++ }
  if ($headers["Strict-Transport-Security"]) { $securityScore++ }
    
  $percentage = [math]::Round(($securityScore / $maxScore) * 100)
    
  if ($percentage -ge 85) {
    Write-Host "PUNTUACIÓN: $securityScore/$maxScore ($percentage%) - EXCELENTE" -ForegroundColor Green
  }
  elseif ($percentage -ge 70) {
    Write-Host "PUNTUACIÓN: $securityScore/$maxScore ($percentage%) - BUENO" -ForegroundColor Yellow
  }
  else {
    Write-Host "PUNTUACIÓN: $securityScore/$maxScore ($percentage%) - NECESITA MEJORAS" -ForegroundColor Red
  }
    
}
catch {
  Write-Host "❌ Error al conectar con $Url" -ForegroundColor Red
  Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== RECOMENDACIONES ADICIONALES ===" -ForegroundColor Cyan
Write-Host "1. Verificar con: https://securityheaders.com/"
Write-Host "2. Validar CSP con: https://csp-evaluator.withgoogle.com/"
Write-Host "3. Revisar DevTools Console para errores CSP"
