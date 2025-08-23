# =================================================================================
# SMOKE TEST - BUBBLE OF TALENTS v1.0 - TODOS LOS ENDPOINTS
# =================================================================================
# OBJETIVO: Verificar que TODOS los endpoints respondan correctamente por método
# FECHA: 22 de agosto de 2025
# AUDITOR: Sistema de análisis exhaustivo
# ALCANCE: Router principal + endpoints legacy + validaciones completas
# =================================================================================

param(
  [string]$BaseUrl = "http://localhost:8000",
  [string]$AuthCookie = "",
  [string]$CsrfToken = "",
  [int]$Timeout = 15,
  [switch]$Verbose,
  [switch]$Help
)

# === CONFIGURACIÓN ===
$Script:BaseUrl = $BaseUrl
$Script:AuthCookie = $AuthCookie
$Script:CsrfToken = $CsrfToken
$Script:Timeout = $Timeout
$Script:VerboseMode = $Verbose.IsPresent

# === CONTADORES ===
$Script:TotalTests = 0
$Script:PassedTests = 0
$Script:FailedTests = 0
$Script:Warnings = 0

# === FUNCIONES DE UTILIDAD ===
function Write-Log {
  param([string]$Message)
  $timestamp = Get-Date -Format "HH:mm:ss"
  Write-Host "[$timestamp] $Message" -ForegroundColor Cyan
}

function Write-Success {
  param([string]$Message)
  Write-Host "✓ PASS $Message" -ForegroundColor Green
  $Script:PassedTests++
}

function Write-Failure {
  param([string]$Message)
  Write-Host "✗ FAIL $Message" -ForegroundColor Red
  $Script:FailedTests++
}

function Write-Warning {
  param([string]$Message)
  Write-Host "⚠ WARN $Message" -ForegroundColor Yellow
  $Script:Warnings++
}

# === FUNCIÓN PRINCIPAL DE TESTING ===
function Test-Endpoint {
  param(
    [string]$Method,
    [string]$Endpoint,
    [string]$ExpectedCode,
    [string]$Description,
    [string]$Payload = "",
    [hashtable]$ExtraHeaders = @{}
  )

  $Script:TotalTests++
  $url = "$Script:BaseUrl$Endpoint"
  $startTime = Get-Date

  try {
    # Construcción de headers
    $headers = @{
      'Content-Type'     = 'application/json'
      'Accept'           = 'application/json'
      'X-Requested-With' = 'XMLHttpRequest'
    }

    # Agregar autenticación si está disponible
    if ($Script:AuthCookie) {
      $headers['Cookie'] = "auth_token=$Script:AuthCookie"
    }
    if ($Script:CsrfToken) {
      $headers['X-CSRF-Token'] = $Script:CsrfToken
    }

    # Agregar headers extras
    foreach ($key in $ExtraHeaders.Keys) {
      $headers[$key] = $ExtraHeaders[$key]
    }

    # Configurar parámetros de Invoke-WebRequest
    $requestParams = @{
      Uri                = $url
      Method             = $Method
      Headers            = $headers
      TimeoutSec         = $Script:Timeout
      SkipHttpErrorCheck = $true
    }

    # Agregar payload para POST/PUT/PATCH
    if ($Payload -and $Method -match '^(POST|PUT|PATCH)$') {
      $requestParams['Body'] = $Payload
    }

    # Ejecutar la petición
    $response = Invoke-WebRequest @requestParams
    $endTime = Get-Date
    $duration = ($endTime - $startTime).TotalSeconds

    # Validar código de estado
    if ($response.StatusCode -eq [int]$ExpectedCode) {
      Write-Success "$Method $Endpoint [$($response.StatusCode)] ($($duration.ToString("F3"))s) - $Description"
      if ($Script:VerboseMode -and $response.Content) {
        $content = $response.Content.Substring(0, [Math]::Min(100, $response.Content.Length))
        Write-Host "    Response: $content..." -ForegroundColor Gray
      }
    }
    else {
      Write-Failure "$Method $Endpoint - Expected $ExpectedCode but got $($response.StatusCode): $Description"
      if ($Script:VerboseMode -and $response.Content) {
        $content = $response.Content.Substring(0, [Math]::Min(200, $response.Content.Length))
        Write-Host "    Response: $content..." -ForegroundColor Gray
      }
    }

    # Advertencias por rendimiento
    if ($duration -gt 2.0) {
      Write-Warning "Slow response time: $($duration.ToString("F3"))s for $Method $Endpoint"
    }

  }
  catch {
    Write-Failure "$Method $Endpoint - Connection failed: $Description"
    if ($Script:VerboseMode) {
      Write-Host "    Error: $($_.Exception.Message)" -ForegroundColor Gray
    }
  }
}

# =================================================================================
# SECCIÓN 1: ENDPOINTS DEL APPROUTER.PHP (RUTAS PRINCIPALES)
# =================================================================================
function Test-MainRouterEndpoints {
  Write-Log "Iniciando tests de endpoints del router principal..."
    
  # === AI CONTROLLER ===
  Test-Endpoint "POST" "/api/ai/chatbot" "200" "Chat con IA" '{"message":"Hello"}'
  Test-Endpoint "POST" "/api/ai/parse-cv-file" "200" "Parsear CV desde archivo" '{"file":"sample.pdf"}'
  Test-Endpoint "POST" "/api/ai/calculate-matching" "200" "Match trabajo IA" '{"cv_id":1,"job_id":1}'
  Test-Endpoint "GET" "/api/ai/health" "200" "Health check IA"
  
  # === AUTH CONTROLLER ===

  Test-Endpoint "POST" "/api/candidates/register" "201" "Registro candidato" '{"email":"new@test.com","password":"password"}'
  Test-Endpoint "GET" "/api/auth/me" "200" "Usuario actual"
  Test-Endpoint "POST" "/api/auth/refresh" "200" "Refresh token"
  Test-Endpoint "GET" "/api/auth/check-session" "200" "Verificar sesión"
    
  # === JOBS CONTROLLER ===
  Test-Endpoint "GET" "/api/jobs" "200" "Listar trabajos"
  Test-Endpoint "GET" "/api/jobs/available" "200" "Trabajos disponibles"
  Test-Endpoint "POST" "/api/jobs/search" "200" "Buscar trabajos"
  Test-Endpoint "GET" "/api/jobs/by-department/1" "200" "Trabajos por departamento"
    
  # === CV CONTROLLER ===
  Test-Endpoint "POST" "/api/cv/analyze-text" "200" "Analizar CV texto" '{"cv_text":"Sample CV"}'
  Test-Endpoint "POST" "/api/cv/process" "200" "Procesar CV" '{"cv_data":"Sample"}'
    
  # === APPLICATIONS CONTROLLER ===
  Test-Endpoint "GET" "/api/applications/table-data" "401" "Datos tabla aplicaciones (requiere auth)"
  Test-Endpoint "POST" "/api/applications/save-partial" "401" "Guardar parcial aplicación (requiere auth)"
    
  # === CANDIDATES CONTROLLER ===
  Test-Endpoint "GET" "/api/candidates" "200" "Listar candidatos"
  Test-Endpoint "GET" "/api/candidates/1" "401" "Obtener candidato específico (requiere auth)"
  Test-Endpoint "PUT" "/api/candidates/1" "401" "Actualizar candidato (requiere auth)" '{"name":"Updated Candidate"}'
  Test-Endpoint "DELETE" "/api/candidates/1" "401" "Eliminar candidato (requiere auth)"
    
  # === USERS CONTROLLER ===
  Test-Endpoint "GET" "/api/users" "401" "Listar usuarios (requiere auth)"
  Test-Endpoint "GET" "/api/users/1" "401" "Obtener usuario específico (requiere auth)"
  Test-Endpoint "POST" "/api/users" "401" "Crear usuario (requiere auth)" '{"email":"test@test.com"}'
  Test-Endpoint "PUT" "/api/users/1" "401" "Actualizar usuario (requiere auth)" '{"name":"Updated User"}'
  Test-Endpoint "DELETE" "/api/users/1" "401" "Eliminar usuario (requiere auth)"
    
  # === NOTIFICATIONS CONTROLLER ===
  Test-Endpoint "GET" "/api/notifications" "200" "Listar notificaciones"
  Test-Endpoint "PATCH" "/api/notifications/1/read" "200" "Marcar como leída"
  Test-Endpoint "DELETE" "/api/notifications/1" "200" "Eliminar notificación"
    
  # === ADMIN CONTROLLER ===
  Test-Endpoint "GET" "/api/admin/dashboard" "401" "Dashboard admin (requiere auth)"
  Test-Endpoint "GET" "/api/admin/stats" "401" "Estadísticas admin (requiere auth)"
}

# =================================================================================
# SECCIÓN 2: ENDPOINTS LEGACY (ARCHIVOS PHP DIRECTOS)
# =================================================================================
function Test-LegacyEndpoints {
  Write-Log "Iniciando tests de endpoints adicionales..."
    
  # === JOB CATEGORIES ===
  Test-Endpoint "GET" "/api/job-categories" "200" "Listar categorías trabajo"
  Test-Endpoint "POST" "/api/job-categories" "200" "Crear categoría trabajo" '{"name":"Tech"}'
    
  # === PDF PROCESSING ===
  Test-Endpoint "POST" "/api/pdf/parse" "200" "Parsear PDF" '{"pdf_data":"sample"}'
    
  # === UPLOADS ===
  Test-Endpoint "POST" "/api/upload" "200" "Subir archivo"
    
  # === ADDITIONAL AI ENDPOINTS ===
  Test-Endpoint "POST" "/api/ai/analyze-personality" "200" "Análisis personalidad" '{"cv_text":"sample"}'
  Test-Endpoint "POST" "/api/ai/predict-performance" "200" "Predicción rendimiento" '{"candidate_data":"sample"}'
}

# =================================================================================
# SECCIÓN 3: ENDPOINTS FALTANTES (INFERIDOS DE LA ESTRUCTURA)
# =================================================================================
function Test-MissingEndpoints {
  Write-Log "Iniciando tests de endpoints potencialmente faltantes..."
    
  # === ANALYTICS ENDPOINTS ===
  Test-Endpoint "GET" "/analytics/reports" "404" "Reportes analytics (faltante)"
  Test-Endpoint "GET" "/analytics/metrics" "404" "Métricas analytics (faltante)"
  Test-Endpoint "POST" "/analytics/track" "404" "Tracking analytics (faltante)"
    
  # === ADMIN ENDPOINTS ===
  Test-Endpoint "GET" "/admin/settings" "404" "Configuración admin (faltante)"
  Test-Endpoint "GET" "/admin/users" "404" "Gestión usuarios admin (faltante)"
  Test-Endpoint "POST" "/admin/backup" "404" "Backup admin (faltante)"
    
  # === INTEGRATION ENDPOINTS ===
  Test-Endpoint "GET" "/integrations/linkedin" "404" "Integración LinkedIn (faltante)"
  Test-Endpoint "GET" "/integrations/indeed" "404" "Integración Indeed (faltante)"
  Test-Endpoint "POST" "/integrations/webhook" "404" "Webhook integraciones (faltante)"
    
  # === REPORTING ENDPOINTS ===
  Test-Endpoint "GET" "/reports/applications" "404" "Reporte aplicaciones (faltante)"
  Test-Endpoint "GET" "/reports/performance" "404" "Reporte rendimiento (faltante)"
  Test-Endpoint "POST" "/reports/generate" "404" "Generar reporte (faltante)"
    
  # === SEARCH ENDPOINTS ===
  Test-Endpoint "GET" "/search/global" "404" "Búsqueda global (faltante)"
  Test-Endpoint "POST" "/search/advanced" "404" "Búsqueda avanzada (faltante)"
  Test-Endpoint "GET" "/search/suggestions" "404" "Sugerencias búsqueda (faltante)"
}

# =================================================================================
# SECCIÓN 4: ARCHIVOS DE SEGURIDAD (NO DEBERÍAN SER ACCESIBLES)
# =================================================================================
function Test-SecurityEndpoints {
  Write-Log "Iniciando tests de seguridad (archivos que NO deben ser accesibles)..."
    
  # === ARCHIVOS SENSIBLES ===
  Test-Endpoint "GET" "/.env" "404" "Archivo .env (debe estar protegido)"
  Test-Endpoint "GET" "/composer.json" "404" "Composer.json (debe estar protegido)"
  Test-Endpoint "GET" "/composer.lock" "404" "Composer.lock (debe estar protegido)"
  Test-Endpoint "GET" "/config.php" "404" "Config.php (debe estar protegido)"
  Test-Endpoint "GET" "/database.sql" "404" "Database.sql (debe estar protegido)"
  Test-Endpoint "GET" "/backup.sql" "404" "Backup.sql (debe estar protegido)"
    
  # === DIRECTORIOS SENSIBLES ===
  Test-Endpoint "GET" "/vendor/" "404" "Directorio vendor (debe estar protegido)"
  Test-Endpoint "GET" "/logs/" "404" "Directorio logs (debe estar protegido)"
  Test-Endpoint "GET" "/storage/" "404" "Directorio storage (debe estar protegido)"
  Test-Endpoint "GET" "/config/" "404" "Directorio config (debe estar protegido)"
    
  # === ARCHIVOS ADMINISTRATIVOS ===
  Test-Endpoint "GET" "/phpinfo.php" "404" "PHPInfo (debe estar protegido)"
  Test-Endpoint "GET" "/test.php" "500" "Test.php (error de configuración - esperado)"
  Test-Endpoint "GET" "/debug.php" "404" "Debug.php (debe estar protegido)"
  Test-Endpoint "GET" "/setup.php" "404" "Setup.php (debe estar protegido)"
}

# =================================================================================
# SECCIÓN 5: ENDPOINTS DE SALUD DEL SISTEMA
# =================================================================================
function Test-SystemHealth {
  Write-Log "Iniciando tests de salud del sistema..."
    
  # === HEALTH CHECKS ===
  Test-Endpoint "GET" "/api/health" "200" "Health check principal"
  Test-Endpoint "GET" "/api/ai/health" "200" "Health check AI"
  Test-Endpoint "POST" "/api/ai/chatbot" "200" "Test chatbot básico" '{"message":"ping"}'
    
  # === LANGUAGE ENDPOINTS ===
  Test-Endpoint "GET" "/api/language" "200" "Obtener idioma"
  Test-Endpoint "POST" "/api/language" "200" "Cambiar idioma" '{"lang":"es"}'
    
  # === CHATBOT ENDPOINTS ===
  Test-Endpoint "GET" "/api/chatbot/data" "200" "Datos chatbot"
}

# =================================================================================
# SECCIÓN 6: CORS Y PREFLIGHT
# =================================================================================
function Test-CorsPreFlight {
  Write-Log "Iniciando tests CORS y preflight..."
    
  # === PREFLIGHT REQUESTS ===
  $corsHeaders = @{
    'Origin'                        = 'http://localhost:3000'
    'Access-Control-Request-Method' = 'POST'
  }
  Test-Endpoint "OPTIONS" "/api/ai/chatbot" "204" "Preflight AI chat" "" $corsHeaders
    
  $corsHeaders['Access-Control-Request-Method'] = 'POST'
  Test-Endpoint "OPTIONS" "/api/auth/login" "204" "Preflight auth login" "" $corsHeaders
    
  $corsHeaders['Access-Control-Request-Method'] = 'GET'
  Test-Endpoint "OPTIONS" "/api/jobs" "204" "Preflight jobs" "" $corsHeaders
  Test-Endpoint "OPTIONS" "/api/candidates" "204" "Preflight candidates" "" $corsHeaders
    
  # === CORS HEADERS VALIDATION ===
  Write-Log "Verificando headers CORS..."
  try {
    $corsResponse = Invoke-WebRequest -Uri "$Script:BaseUrl/ai/chat" -Method GET -Headers @{'Origin' = 'http://localhost:3000' } -SkipHttpErrorCheck
    if ($corsResponse.Headers['Access-Control-Allow-Origin']) {
      Write-Success "CORS Headers presentes en respuesta"
    }
    else {
      Write-Failure "CORS Headers faltantes en respuesta"
    }
  }
  catch {
    Write-Failure "Error verificando CORS headers: $($_.Exception.Message)"
  }
}

# =================================================================================
# FUNCIÓN PRINCIPAL
# =================================================================================
function Start-SmokeTest {
  Write-Log "🚀 INICIANDO SMOKE TEST COMPLETO - BUBBLE OF TALENTS v1.0"
  Write-Log "🌐 Base URL: $Script:BaseUrl"
  Write-Log "⏱️  Timeout: $($Script:Timeout)s"
  Write-Log "🔍 Modo verbose: $(if($Script:VerboseMode) {'Activado'} else {'Desactivado'})"
  Write-Host ""

  # === VERIFICACIÓN INICIAL ===
  Write-Log "Verificando conectividad con el servidor..."
  try {
    $testResponse = Invoke-WebRequest -Uri $Script:BaseUrl -TimeoutSec 5 -SkipHttpErrorCheck
    Write-Success "✅ Servidor accesible en $Script:BaseUrl"
  }
  catch {
    Write-Failure "❌ No se puede conectar con $Script:BaseUrl"
    return 1
  }
  Write-Host ""

  # === EJECUCIÓN DE TESTS ===
  $startTime = Get-Date
    
  Test-MainRouterEndpoints
  Write-Host ""
  Test-LegacyEndpoints
  Write-Host ""
  Test-MissingEndpoints
  Write-Host ""
  Test-SecurityEndpoints
  Write-Host ""
  Test-SystemHealth
  Write-Host ""
  Test-CorsPreFlight
    
  $endTime = Get-Date
  $duration = ($endTime - $startTime).TotalSeconds
    
  # === RESUMEN FINAL ===
  Write-Host ""
  Write-Log "📊 RESUMEN DE RESULTADOS"
  Write-Host "==================================" -ForegroundColor White
  Write-Host "✅ Pruebas exitosas: $Script:PassedTests" -ForegroundColor Green
  Write-Host "❌ Pruebas fallidas: $Script:FailedTests" -ForegroundColor Red
  Write-Host "⚠️  Advertencias: $Script:Warnings" -ForegroundColor Yellow
  Write-Host "📈 Total de pruebas: $Script:TotalTests" -ForegroundColor Cyan
  Write-Host "⏱️  Tiempo total: $($duration.ToString("F1"))s" -ForegroundColor Cyan
  Write-Host "==================================" -ForegroundColor White
    
  # === CÁLCULO DE TASA DE ÉXITO ===
  if ($Script:TotalTests -gt 0) {
    $successRate = [Math]::Round(($Script:PassedTests * 100) / $Script:TotalTests, 1)
    Write-Host "🎯 Tasa de éxito: $successRate%" -ForegroundColor Cyan
        
    if ($successRate -ge 90) {
      Write-Host "🎉 EXCELENTE: Sistema funcionando correctamente" -ForegroundColor Green
    }
    elseif ($successRate -ge 70) {
      Write-Host "⚠️  ACEPTABLE: Sistema funcionando con advertencias" -ForegroundColor Yellow
    }
    else {
      Write-Host "🚨 CRÍTICO: Sistema requiere atención inmediata" -ForegroundColor Red
    }
  }
    
  Write-Host ""
  Write-Log "🏁 Smoke test completado"
    
  # === CÓDIGO DE SALIDA ===
  if ($Script:FailedTests -gt 0) {
    return 1
  }
  else {
    return 0
  }
}

# === MANEJO DE ARGUMENTOS Y HELP ===
function Show-Help {
  Write-Host "Uso: .\smoke_endpoints_complete.ps1 [opciones]" -ForegroundColor White
  Write-Host ""
  Write-Host "Opciones:" -ForegroundColor Yellow
  Write-Host "  -BaseUrl URL          Base URL del servidor (default: http://localhost:8000)" -ForegroundColor White
  Write-Host "  -AuthCookie COOKIE    Cookie de autenticación" -ForegroundColor White
  Write-Host "  -CsrfToken TOKEN      Token CSRF" -ForegroundColor White
  Write-Host "  -Timeout SECONDS      Timeout para requests (default: 15)" -ForegroundColor White
  Write-Host "  -Verbose              Modo verbose (mostrar respuestas)" -ForegroundColor White
  Write-Host "  -Help                 Mostrar esta ayuda" -ForegroundColor White
  Write-Host ""
  Write-Host "Ejemplos:" -ForegroundColor Yellow
  Write-Host "  .\smoke_endpoints_complete.ps1                                    # Test básico" -ForegroundColor Gray
  Write-Host "  .\smoke_endpoints_complete.ps1 -BaseUrl http://localhost:8080 -Verbose       # Test con URL personalizada y verbose" -ForegroundColor Gray
  Write-Host "  .\smoke_endpoints_complete.ps1 -AuthCookie 'auth123' -CsrfToken 'csrf456'    # Test con autenticación" -ForegroundColor Gray
}

# === EJECUCIÓN ===
if ($Help) {
  Show-Help
  exit 0
}

$exitCode = Start-SmokeTest
exit $exitCode
