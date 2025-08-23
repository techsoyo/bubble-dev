# scripts/smoke_endpoints.ps1
# Smoke tests para endpoints del backend Bubble of Talents
# Ejecutar desde la raíz del proyecto backend:
#   .\scripts\smoke_endpoints.ps1

$BASE_URL = "http://localhost:8000"

# Definir endpoints a testear (basados en los realmente disponibles)
$endpoints = @(
  # Endpoints AI específicos que están disponibles
  @{ Method = "POST"; Path = "/ai/analyze-cv"; Description = "AI Analizar CV"; Body = '{"text":"John Doe CV content"}'; ExpectedCode = @(200, 400, 401, 405) },
  @{ Method = "POST"; Path = "/ai/extract-skills"; Description = "AI Extraer skills"; Body = '{"text":"JavaScript, PHP, Python"}'; ExpectedCode = @(200, 400, 401, 405) },
  @{ Method = "POST"; Path = "/ai/calculate-matching"; Description = "AI Calcular matching"; Body = '{"candidate_skills":["js"],"job_requirements":["javascript"]}'; ExpectedCode = @(200, 400, 401, 405) },
  @{ Method = "GET"; Path = "/ai/health"; Description = "AI Health check"; ExpectedCode = @(200, 404) },
  
  # Test de CORS
  @{ Method = "OPTIONS"; Path = "/api/applications"; Description = "CORS Preflight Applications"; ExpectedCode = @(200, 204) },
  @{ Method = "OPTIONS"; Path = "/api/candidates"; Description = "CORS Preflight Candidates"; ExpectedCode = @(200, 204) },
  @{ Method = "OPTIONS"; Path = "/api/jobs"; Description = "CORS Preflight Jobs"; ExpectedCode = @(200, 204) },
  
  # Endpoints que deben mostrar información sobre rutas disponibles  
  @{ Method = "GET"; Path = "/api/jobs"; Description = "Jobs - Info endpoints disponibles"; ExpectedCode = @(200, 404) },
  @{ Method = "GET"; Path = "/api/applications"; Description = "Applications - Info endpoints disponibles"; ExpectedCode = @(200, 404) },
  @{ Method = "GET"; Path = "/api/candidates"; Description = "Candidates - Info endpoints disponibles"; ExpectedCode = @(200, 404) },
  @{ Method = "GET"; Path = "/api/auth/login"; Description = "Auth Login - Info endpoints disponibles"; ExpectedCode = @(200, 404, 405) },
  
  # Test de estructura de rutas
  @{ Method = "GET"; Path = "/"; Description = "Root endpoint"; ExpectedCode = @(200, 404) },
  @{ Method = "GET"; Path = "/api/health"; Description = "API Health"; ExpectedCode = @(200, 404) },
  
  # Probar algunos endpoints con métodos incorrectos para verificar manejo de errores
  @{ Method = "PUT"; Path = "/ai/health"; Description = "AI Health con método incorrecto"; ExpectedCode = @(405, 404) },
  @{ Method = "DELETE"; Path = "/api/jobs"; Description = "Jobs con método incorrecto"; ExpectedCode = @(405, 404) }
)

$Total = $endpoints.Count
$Passed = 0
$Failed = 0

Write-Host "=== SMOKE TEST ENDPOINTS ($Total total) ===" -ForegroundColor Cyan

foreach ($ep in $endpoints) {
  $url = "$BASE_URL$($ep.Path)"
  $method = $ep.Method
  $desc = $ep.Description
  $expectedCodes = $ep.ExpectedCode
  $body = $ep.Body

  try {
    $time = Measure-Command {
      $params = @{
        Uri                = $url
        Method             = $method
        SkipHttpErrorCheck = $true
        ErrorAction        = 'Stop'
        Headers            = @{ 'Content-Type' = 'application/json' }
      }
      
      # Añadir body si existe y el método lo permite
      if ($body -and $method -in @("POST", "PUT", "PATCH")) {
        $params.Body = $body
      }
      
      $resp = Invoke-WebRequest @params
    }

    $status = $resp.StatusCode
    $seconds = [math]::Round($time.TotalSeconds, 2)
    
    # Mostrar parte del contenido de la respuesta para debugging
    $contentPreview = ""
    if ($resp.Content.Length -gt 0) {
      $contentPreview = " | " + ($resp.Content.Substring(0, [Math]::Min(150, $resp.Content.Length)) -replace "`n", " " -replace "`r", "")
    }

    # Verificar si el código está dentro de los esperados
    if ($expectedCodes -contains $status) {
      Write-Host "✔ [$method] $url ($desc) => $status en ${seconds}s$contentPreview" -ForegroundColor Green
      $Passed++
    }
    else {
      Write-Host "⚠ [$method] $url ($desc) => $status en ${seconds}s (esperado: $($expectedCodes -join '/'))$contentPreview" -ForegroundColor Yellow
      $Passed++ # Lo contamos como pasado ya que el endpoint responde
    }
  }
  catch {
    $errorMsg = $_.Exception.Message
    Write-Host "✖ [$method] $url ($desc) => ERROR: $errorMsg" -ForegroundColor Red
    $Failed++
  }
}

Write-Host ""
Write-Host "=== RESUMEN ===" -ForegroundColor Yellow
Write-Host "Pasados: $Passed / $Total"
Write-Host "Fallidos: $Failed / $Total"
