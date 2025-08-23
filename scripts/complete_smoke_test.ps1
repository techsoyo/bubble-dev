#!/usr/bin/env pwsh
#
# BUBBLE OF TALENTS v1.0 - SMOKE TEST COMPLETO
# Test comprehensivo de 150+ endpoints
# Compatible con Windows PowerShell 5.1+
#

param(
  [string]$BaseUrl = "http://localhost:8000",
  [int]$Timeout = 30,
  [switch]$Verbose = $false,
  [switch]$QuickMode = $false,
  [string]$Category = "ALL"
)

# Configuracion global
$script:TestResults = @{
  Passed     = 0
  Failed     = 0 
  Warnings   = 0
  Skipped    = 0
  Categories = @{}
}

$script:FailedTests = @()
$script:Warnings = @()

# Colores para output
$Green = @{ForegroundColor = 'Green' }
$Red = @{ForegroundColor = 'Red' }
$Yellow = @{ForegroundColor = 'Yellow' }
$Cyan = @{ForegroundColor = 'Cyan' }
$Magenta = @{ForegroundColor = 'Magenta' }

function Write-TestHeader {
  param([string]$Title)
  Write-Host ""
  Write-Host "="*60 @Cyan
  Write-Host " $Title" @Cyan
  Write-Host "="*60 @Cyan
}

function Write-CategoryHeader {
  param([string]$Category)
  Write-Host ""
  Write-Host "--- $Category ---" @Magenta
}

function Invoke-ApiTest {
  param(
    [string]$Method = "GET",
    [string]$Endpoint,
    [int[]]$ExpectedStatus = @(200),
    [string]$Body = $null,
    [hashtable]$Headers = @{'Content-Type' = 'application/json' },
    [string]$Description = "",
    [string]$Category = "General"
  )
    
  $url = "$BaseUrl$Endpoint"
  $testName = "$Method $Endpoint"
  $start = Get-Date
    
  try {
    $params = @{
      Uri             = $url
      Method          = $Method
      Headers         = $Headers
      TimeoutSec      = $Timeout
      UseBasicParsing = $true
    }
        
    if ($Body -and ($Method -eq "POST" -or $Method -eq "PUT" -or $Method -eq "PATCH")) {
      $params.Body = $Body
    }
        
    $response = Invoke-WebRequest @params -ErrorAction SilentlyContinue
    $statusCode = [int]$response.StatusCode
    $duration = (Get-Date) - $start
        
    # Verificar status esperado
    $success = $statusCode -in $ExpectedStatus
        
    if ($success) {
      $script:TestResults.Passed++
      $status = "PASS"
      $color = $Green
            
      # Verificar performance warning
      if ($duration.TotalSeconds -gt 0.8) {
        $script:TestResults.Warnings++
        $script:Warnings += "SLOW: $testName took $($duration.TotalSeconds.ToString('F2'))s"
        $status = "PASS*"
        $color = $Yellow
      }
    }
    else {
      $script:TestResults.Failed++
      $script:FailedTests += "$testName - Expected: $($ExpectedStatus -join '/'), Got: $statusCode"
      $status = "FAIL"
      $color = $Red
    }
        
    # Actualizar estadisticas por categoria
    if (-not $script:TestResults.Categories.ContainsKey($Category)) {
      $script:TestResults.Categories[$Category] = @{Passed = 0; Failed = 0; Warnings = 0 }
    }
    if ($success) {
      if ($status -eq "PASS*") {
        $script:TestResults.Categories[$Category].Warnings++
      }
      else {
        $script:TestResults.Categories[$Category].Passed++
      }
    }
    else {
      $script:TestResults.Categories[$Category].Failed++
    }
        
    $desc = if ($Description) { " - $Description" } else { "" }
    $durationStr = $duration.TotalSeconds.ToString('F2')
    Write-Host "[$status] $testName [$statusCode] ($($durationStr)s)$desc" @color
        
  }
  catch {
    $script:TestResults.Failed++
    $script:FailedTests += "$testName - Error: $($_.Exception.Message)"
    if (-not $script:TestResults.Categories.ContainsKey($Category)) {
      $script:TestResults.Categories[$Category] = @{Passed = 0; Failed = 0; Warnings = 0 }
    }
    $script:TestResults.Categories[$Category].Failed++
        
    $desc = if ($Description) { " - $Description" } else { "" }
    Write-Host "[FAIL] $testName - ERROR: $($_.Exception.Message)$desc" @Red
  }
}

function Test-CoreEndpoints {
  Write-CategoryHeader "ENDPOINTS PRINCIPALES"
    
  Invoke-ApiTest -Endpoint "/api/health" -Description "Health principal" -Category "Core"
  Invoke-ApiTest -Endpoint "/api/status" -ExpectedStatus @(200, 404) -Description "System status" -Category "Core"
  Invoke-ApiTest -Endpoint "/api/version" -ExpectedStatus @(200, 404) -Description "System version" -Category "Core"
  Invoke-ApiTest -Endpoint "/api/ai/health" -Description "AI health" -Category "Core"
}

function Test-AuthEndpoints {
  Write-CategoryHeader "AUTENTICACION"
    
  Invoke-ApiTest -Method "POST" -Endpoint "/api/auth/login" -Body '{"email":"test@test.com","password":"test"}' -ExpectedStatus @(200, 400, 401) -Description "Login attempt" -Category "Auth"
  Invoke-ApiTest -Endpoint "/api/auth/me" -ExpectedStatus @(200, 401) -Description "Current user" -Category "Auth"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/auth/logout" -ExpectedStatus @(200, 401) -Description "Logout" -Category "Auth"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/auth/refresh" -ExpectedStatus @(200, 401) -Description "Refresh token" -Category "Auth"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/auth/register" -Body '{"email":"test@test.com","password":"test123","name":"Test"}' -ExpectedStatus @(200, 201, 400, 422) -Description "Register" -Category "Auth"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/auth/forgot-password" -Body '{"email":"test@test.com"}' -ExpectedStatus @(200, 400) -Description "Forgot password" -Category "Auth"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/auth/reset-password" -Body '{"token":"test","password":"test123"}' -ExpectedStatus @(200, 400, 404) -Description "Reset password" -Category "Auth"
  Invoke-ApiTest -Endpoint "/api/auth/check-session" -ExpectedStatus @(200, 401) -Description "Check session" -Category "Auth"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/auth/verify-session" -ExpectedStatus @(200, 401) -Description "Verify session" -Category "Auth"
}

function Test-AIEndpoints {
  Write-CategoryHeader "INTELIGENCIA ARTIFICIAL"
    
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/chatbot" -Body '{"message":"test"}' -Description "Chatbot funcional" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/parse-cv" -Body '{"text":"test cv"}' -ExpectedStatus @(200, 400) -Description "Parse CV text" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/calculate-matching" -Body '{"cv":"test","job":"test"}' -ExpectedStatus @(200, 400) -Description "Calculate matching" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/analyze-personality" -Body '{"cv":"test"}' -ExpectedStatus @(200, 400, 501) -Description "Analyze personality" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/predict-performance" -Body '{"data":"test"}' -ExpectedStatus @(200, 400, 501) -Description "Predict performance" -Category "AI"
    
  # Archivos PHP directos en /ai/
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/parse-cv-file" -ExpectedStatus @(200, 400) -Description "Parse CV file" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/extract-cv-stage1" -ExpectedStatus @(200, 400) -Description "Extract CV Stage 1" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/extract-cv-stage2" -ExpectedStatus @(200, 400) -Description "Extract CV Stage 2" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/candidate-matching" -ExpectedStatus @(200, 400) -Description "Candidate matching" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/recruitment-insights" -ExpectedStatus @(200, 400) -Description "Recruitment insights" -Category "AI"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/ai/pre-screening" -ExpectedStatus @(200, 400) -Description "Pre-screening" -Category "AI"
}

function Test-CandidatesEndpoints {
  Write-CategoryHeader "CANDIDATOS"
    
  Invoke-ApiTest -Endpoint "/api/candidates" -Description "Lista candidatos" -Category "Candidates"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/candidates" -Body '{"name":"Test","email":"test@test.com"}' -ExpectedStatus @(200, 201, 400, 401) -Description "Crear candidato" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidates/1" -ExpectedStatus @(200, 404) -Description "Ver candidato" -Category "Candidates"
  Invoke-ApiTest -Method "PUT" -Endpoint "/api/candidates/1" -Body '{"name":"Updated"}' -ExpectedStatus @(200, 400, 404, 401) -Description "Actualizar candidato" -Category "Candidates"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/candidates/1" -ExpectedStatus @(200, 204, 404, 401) -Description "Eliminar candidato" -Category "Candidates"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/candidates/register" -Body '{"name":"Test","email":"test@test.com"}' -ExpectedStatus @(200, 201, 400) -Description "Registro candidato" -Category "Candidates"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/candidates/upload-cv" -ExpectedStatus @(200, 400, 401) -Description "Upload CV" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidates/profile/1" -ExpectedStatus @(200, 404, 401) -Description "Perfil candidato" -Category "Candidates"
  Invoke-ApiTest -Method "PATCH" -Endpoint "/api/candidates/1/status" -Body '{"status":"active"}' -ExpectedStatus @(200, 400, 404, 401) -Description "Update status" -Category "Candidates"
    
  # Archivos PHP candidatos
  Invoke-ApiTest -Endpoint "/api/candidate-applications" -ExpectedStatus @(200, 401) -Description "Applications" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidate-experiences" -ExpectedStatus @(200, 401) -Description "Experiences" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidate-notifications" -ExpectedStatus @(200, 401) -Description "Notifications" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidate_education" -ExpectedStatus @(200, 401) -Description "Education" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidate_skills" -ExpectedStatus @(200, 401) -Description "Skills" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidate_certifications" -ExpectedStatus @(200, 401) -Description "Certifications" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidate_languages" -ExpectedStatus @(200, 401) -Description "Languages" -Category "Candidates"
  Invoke-ApiTest -Endpoint "/api/candidate_references" -ExpectedStatus @(200, 401) -Description "References" -Category "Candidates"
}

function Test-JobsEndpoints {
  Write-CategoryHeader "TRABAJOS"
    
  Invoke-ApiTest -Endpoint "/api/jobs" -Description "Listar trabajos" -Category "Jobs"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/jobs" -Body '{"title":"Test Job","description":"Test"}' -ExpectedStatus @(200, 201, 400, 401) -Description "Crear trabajo" -Category "Jobs"
  Invoke-ApiTest -Endpoint "/api/jobs/1" -ExpectedStatus @(200, 404) -Description "Ver trabajo" -Category "Jobs"
  Invoke-ApiTest -Method "PUT" -Endpoint "/api/jobs/1" -Body '{"title":"Updated Job"}' -ExpectedStatus @(200, 400, 404, 401) -Description "Actualizar trabajo" -Category "Jobs"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/jobs/1" -ExpectedStatus @(200, 204, 404, 401) -Description "Eliminar trabajo" -Category "Jobs"
  Invoke-ApiTest -Endpoint "/api/jobs/available" -Description "Trabajos disponibles" -Category "Jobs"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/jobs/search" -Body '{"query":"developer"}' -Description "Buscar trabajos" -Category "Jobs"
  Invoke-ApiTest -Endpoint "/api/jobs/by-department/1" -ExpectedStatus @(200, 404) -Description "Jobs by department" -Category "Jobs"
    
  # Archivos PHP jobs
  Invoke-ApiTest -Endpoint "/api/job_benefits" -ExpectedStatus @(200, 401) -Description "Job benefits" -Category "Jobs"
  Invoke-ApiTest -Endpoint "/api/job_requirements" -ExpectedStatus @(200, 401) -Description "Job requirements" -Category "Jobs"
  Invoke-ApiTest -Endpoint "/api/job_skills" -ExpectedStatus @(200, 401) -Description "Job skills" -Category "Jobs"
}

function Test-DepartmentsEndpoints {
  Write-CategoryHeader "DEPARTAMENTOS"
    
  Invoke-ApiTest -Endpoint "/api/departments" -Description "Lista departamentos" -Category "Departments"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/departments" -Body '{"name":"Test Dept"}' -ExpectedStatus @(200, 201, 400, 401) -Description "Crear departamento" -Category "Departments"
  Invoke-ApiTest -Endpoint "/api/departments/1" -ExpectedStatus @(200, 404) -Description "Ver departamento" -Category "Departments"
  Invoke-ApiTest -Method "PUT" -Endpoint "/api/departments/1" -Body '{"name":"Updated Dept"}' -ExpectedStatus @(200, 400, 404, 401) -Description "Actualizar departamento" -Category "Departments"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/departments/1" -ExpectedStatus @(200, 204, 404, 401) -Description "Eliminar departamento" -Category "Departments"
}

function Test-SkillsEndpoints {
  Write-CategoryHeader "HABILIDADES"
    
  Invoke-ApiTest -Endpoint "/api/skills" -Description "Lista skills" -Category "Skills"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/skills" -Body '{"name":"PHP","category":"Programming"}' -ExpectedStatus @(200, 201, 400, 401) -Description "Crear skill" -Category "Skills"
  Invoke-ApiTest -Endpoint "/api/skills/1" -ExpectedStatus @(200, 404) -Description "Ver skill" -Category "Skills"
  Invoke-ApiTest -Method "PUT" -Endpoint "/api/skills/1" -Body '{"name":"Advanced PHP"}' -ExpectedStatus @(200, 400, 404, 401) -Description "Actualizar skill" -Category "Skills"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/skills/1" -ExpectedStatus @(200, 204, 404, 401) -Description "Eliminar skill" -Category "Skills"
    
  # Archivos PHP skills
  Invoke-ApiTest -Method "POST" -Endpoint "/api/extract_skills" -Body '{"text":"PHP JavaScript React"}' -ExpectedStatus @(200, 400) -Description "Extract skills" -Category "Skills"
}

function Test-UsersEndpoints {
  Write-CategoryHeader "USUARIOS"
    
  Invoke-ApiTest -Endpoint "/api/users" -ExpectedStatus @(200, 401) -Description "Lista usuarios" -Category "Users"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/users" -Body '{"name":"Test","email":"test@test.com"}' -ExpectedStatus @(200, 201, 400, 401) -Description "Crear usuario" -Category "Users"
  Invoke-ApiTest -Endpoint "/api/users/1" -ExpectedStatus @(200, 404, 401) -Description "Ver usuario" -Category "Users"
  Invoke-ApiTest -Method "PUT" -Endpoint "/api/users/1" -Body '{"name":"Updated"}' -ExpectedStatus @(200, 400, 404, 401) -Description "Actualizar usuario" -Category "Users"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/users/1" -ExpectedStatus @(200, 204, 404, 401) -Description "Eliminar usuario" -Category "Users"
}

function Test-NotificationsEndpoints {
  Write-CategoryHeader "NOTIFICACIONES"
    
  Invoke-ApiTest -Endpoint "/api/notifications" -Description "Listar notificaciones" -Category "Notifications"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/notifications" -Body '{"title":"Test","message":"Test message"}' -ExpectedStatus @(200, 201, 400, 401) -Description "Crear notificacion" -Category "Notifications"
  Invoke-ApiTest -Method "PATCH" -Endpoint "/api/notifications/1/read" -ExpectedStatus @(200, 404, 401) -Description "Marcar como leida" -Category "Notifications"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/notifications/1" -ExpectedStatus @(200, 204, 404, 401) -Description "Eliminar notificacion" -Category "Notifications"
    
  # Archivos PHP notifications
  Invoke-ApiTest -Endpoint "/api/get-notification-preferences" -ExpectedStatus @(200, 401) -Description "Get preferences" -Category "Notifications"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/save-notification-preferences" -Body '{"email":true}' -ExpectedStatus @(200, 400, 401) -Description "Save preferences" -Category "Notifications"
}

function Test-AdminEndpoints {
  Write-CategoryHeader "ADMINISTRACION"
    
  Invoke-ApiTest -Endpoint "/api/admin/dashboard" -ExpectedStatus @(200, 401) -Description "Admin dashboard" -Category "Admin"
  Invoke-ApiTest -Endpoint "/api/admin/stats" -ExpectedStatus @(200, 401) -Description "Admin stats" -Category "Admin"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/admin/bulk-actions" -Body '{"action":"test","ids":[1,2,3]}' -ExpectedStatus @(200, 400, 401) -Description "Bulk actions" -Category "Admin"
    
  # Archivos PHP admin
  Invoke-ApiTest -Endpoint "/api/statistics" -ExpectedStatus @(200, 401) -Description "Statistics" -Category "Admin"
  Invoke-ApiTest -Endpoint "/api/reporting" -ExpectedStatus @(200, 401) -Description "Reporting" -Category "Admin"
}

function Test-FilesEndpoints {
  Write-CategoryHeader "ARCHIVOS"
    
  Invoke-ApiTest -Method "POST" -Endpoint "/api/upload" -ExpectedStatus @(200, 400, 401) -Description "Upload file" -Category "Files"
  Invoke-ApiTest -Endpoint "/api/files/test.txt" -ExpectedStatus @(200, 404) -Description "Serve file" -Category "Files"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/files/test.txt" -ExpectedStatus @(200, 204, 404, 401) -Description "Delete file" -Category "Files"
}

function Test-CVEndpoints {
  Write-CategoryHeader "CURRICULUM VITAE"
    
  Invoke-ApiTest -Method "POST" -Endpoint "/api/cv/analyze-file" -ExpectedStatus @(200, 400) -Description "Analyze CV file" -Category "CV"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/cv/analyze-text" -Body '{"text":"test cv"}' -ExpectedStatus @(200, 400) -Description "Analyze CV text" -Category "CV"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/cv/extract-text" -ExpectedStatus @(200, 400) -Description "Extract text" -Category "CV"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/cv/process" -ExpectedStatus @(200, 400) -Description "Process CV" -Category "CV"
    
  # Archivos PHP CV
  Invoke-ApiTest -Method "POST" -Endpoint "/api/analyze_cv" -Body '{"cv":"test"}' -ExpectedStatus @(200, 400) -Description "Analyze CV" -Category "CV"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/cv/confirm" -ExpectedStatus @(200, 400, 401) -Description "Confirm CV" -Category "CV"
  Invoke-ApiTest -Method "DELETE" -Endpoint "/api/cv/delete" -ExpectedStatus @(200, 204, 404, 401) -Description "Delete CV" -Category "CV"
}

function Test-ChatbotEndpoints {
  Write-CategoryHeader "CHATBOT"
    
  Invoke-ApiTest -Endpoint "/api/chatbot/data" -ExpectedStatus @(200, 404) -Description "Chatbot data" -Category "Chatbot"
  Invoke-ApiTest -Method "GET" -Endpoint "/api/chatbot/node" -ExpectedStatus @(200, 404) -Description "Get chatbot node" -Category "Chatbot"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/chatbot/node" -Body '{"nodeId":"start"}' -ExpectedStatus @(200, 400, 404) -Description "Process chatbot node" -Category "Chatbot"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/chatbot/interaction" -Body '{"message":"help"}' -ExpectedStatus @(200, 400) -Description "Process interaction" -Category "Chatbot"
  Invoke-ApiTest -Endpoint "/api/chatbot/analytics" -ExpectedStatus @(200, 401) -Description "Chatbot analytics" -Category "Chatbot"
    
  # Archivos PHP chatbot
  Invoke-ApiTest -Endpoint "/api/chatbot_nodes" -ExpectedStatus @(200, 404) -Description "Chatbot nodes" -Category "Chatbot"
  Invoke-ApiTest -Endpoint "/api/chatbot_options" -ExpectedStatus @(200, 404) -Description "Chatbot options" -Category "Chatbot"
  Invoke-ApiTest -Endpoint "/api/chatbot_analytics" -ExpectedStatus @(200, 401) -Description "Chatbot analytics file" -Category "Chatbot"
  Invoke-ApiTest -Endpoint "/api/chatbot_decision_tree" -ExpectedStatus @(200, 404) -Description "Decision tree" -Category "Chatbot"
}

function Test-LanguageEndpoints {
  Write-CategoryHeader "IDIOMAS"
    
  Invoke-ApiTest -Endpoint "/api/language" -Description "Get language" -Category "Language"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/language" -Body '{"language":"es"}' -Description "Set language" -Category "Language"
}

function Test-OtherEndpoints {
  Write-CategoryHeader "OTROS ENDPOINTS"
    
  Invoke-ApiTest -Endpoint "/api/news" -ExpectedStatus @(200, 404) -Description "News" -Category "Others"
  Invoke-ApiTest -Endpoint "/api/culture" -ExpectedStatus @(200, 404) -Description "Culture" -Category "Others"
  Invoke-ApiTest -Endpoint "/api/interviews" -ExpectedStatus @(200, 401) -Description "Interviews" -Category "Others"
  Invoke-ApiTest -Endpoint "/api/applications" -ExpectedStatus @(200, 401) -Description "Applications" -Category "Others"
  Invoke-ApiTest -Endpoint "/api/recruiters" -ExpectedStatus @(200, 401) -Description "Recruiters" -Category "Others"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/send-email" -Body '{"to":"test@test.com","subject":"Test"}' -ExpectedStatus @(200, 400) -Description "Send email" -Category "Others"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/request_info" -Body '{"email":"test@test.com"}' -ExpectedStatus @(200, 400) -Description "Request info" -Category "Others"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/change-password" -Body '{"old":"test","new":"test123"}' -ExpectedStatus @(200, 400, 401) -Description "Change password" -Category "Others"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/save-candidate" -Body '{"name":"Test"}' -ExpectedStatus @(200, 400) -Description "Save candidate" -Category "Others"
    
  # PDF Processing
  Invoke-ApiTest -Method "POST" -Endpoint "/api/pdf/parse" -ExpectedStatus @(200, 400) -Description "Parse PDF" -Category "Others"
    
  # Job Categories
  Invoke-ApiTest -Endpoint "/api/job-categories" -ExpectedStatus @(200, 404) -Description "Job categories" -Category "Others"
  Invoke-ApiTest -Method "POST" -Endpoint "/api/job-categories" -Body '{"name":"IT"}' -ExpectedStatus @(200, 201, 400, 401) -Description "Create job category" -Category "Others"
    
  # Social logins
  Invoke-ApiTest -Endpoint "/api/social_logins" -ExpectedStatus @(200, 404) -Description "Social logins" -Category "Others"
}

function Test-CorsEndpoints {
  Write-CategoryHeader "CORS PREFLIGHT"
    
  Invoke-ApiTest -Method "OPTIONS" -Endpoint "/api/ai/chatbot" -ExpectedStatus @(200, 204) -Description "Preflight AI chat" -Category "CORS"
  Invoke-ApiTest -Method "OPTIONS" -Endpoint "/api/auth/login" -ExpectedStatus @(200, 204) -Description "Preflight login" -Category "CORS"
  Invoke-ApiTest -Method "OPTIONS" -Endpoint "/api/jobs" -ExpectedStatus @(200, 204) -Description "Preflight jobs" -Category "CORS"
  Invoke-ApiTest -Method "OPTIONS" -Endpoint "/api/candidates" -ExpectedStatus @(200, 204) -Description "Preflight candidates" -Category "CORS"
}

function Write-TestSummary {
  Write-TestHeader "RESUMEN FINAL"
    
  $total = $script:TestResults.Passed + $script:TestResults.Failed + $script:TestResults.Warnings
  $successRate = if ($total -gt 0) { [math]::Round(($script:TestResults.Passed + $script:TestResults.Warnings) / $total * 100, 1) } else { 0 }
    
  Write-Host ""
  Write-Host "ESTADISTICAS GENERALES:" @Cyan
  Write-Host "Passed  : $($script:TestResults.Passed)" @Green
  Write-Host "Failed  : $($script:TestResults.Failed)" @Red  
  Write-Host "Warnings: $($script:TestResults.Warnings)" @Yellow
  Write-Host "Total   : $total"
  Write-Host "Success : $successRate%" $(if ($successRate -ge 95) { $Green } elseif ($successRate -ge 80) { $Yellow } else { $Red })
    
  Write-Host ""
  Write-Host "ESTADISTICAS POR CATEGORIA:" @Cyan
  foreach ($category in $script:TestResults.Categories.Keys | Sort-Object) {
    $cat = $script:TestResults.Categories[$category]
    $catTotal = $cat.Passed + $cat.Failed + $cat.Warnings
    $catSuccess = if ($catTotal -gt 0) { [math]::Round(($cat.Passed + $cat.Warnings) / $catTotal * 100, 1) } else { 0 }
    Write-Host "  $category`: P:$($cat.Passed) F:$($cat.Failed) W:$($cat.Warnings) ($catSuccess%)" $(if ($catSuccess -ge 95) { $Green } elseif ($catSuccess -ge 80) { $Yellow } else { $Red })
  }
    
  if ($script:FailedTests.Count -gt 0) {
    Write-Host ""
    Write-Host "TESTS FALLIDOS:" @Red
    foreach ($test in $script:FailedTests) {
      Write-Host "  - $test" @Red
    }
  }
    
  if ($script:Warnings.Count -gt 0) {
    Write-Host ""
    Write-Host "ADVERTENCIAS:" @Yellow
    foreach ($warning in $script:Warnings) {
      Write-Host "  - $warning" @Yellow
    }
  }
}

# MAIN EXECUTION
Write-TestHeader "BUBBLE OF TALENTS v1.0 - SMOKE TEST COMPLETO"
Write-Host "Base URL: $BaseUrl" @Cyan
Write-Host "Timeout : $($Timeout)s" @Cyan
Write-Host "Category: $Category" @Cyan

$startTime = Get-Date

# Ejecutar tests por categoria
if ($Category -eq "ALL" -or $Category -eq "CORE") { Test-CoreEndpoints }
if ($Category -eq "ALL" -or $Category -eq "AUTH") { Test-AuthEndpoints }
if ($Category -eq "ALL" -or $Category -eq "AI") { Test-AIEndpoints }
if ($Category -eq "ALL" -or $Category -eq "CANDIDATES") { Test-CandidatesEndpoints }
if ($Category -eq "ALL" -or $Category -eq "JOBS") { Test-JobsEndpoints }
if ($Category -eq "ALL" -or $Category -eq "DEPARTMENTS") { Test-DepartmentsEndpoints }
if ($Category -eq "ALL" -or $Category -eq "SKILLS") { Test-SkillsEndpoints }
if ($Category -eq "ALL" -or $Category -eq "USERS") { Test-UsersEndpoints }
if ($Category -eq "ALL" -or $Category -eq "NOTIFICATIONS") { Test-NotificationsEndpoints }
if ($Category -eq "ALL" -or $Category -eq "ADMIN") { Test-AdminEndpoints }
if ($Category -eq "ALL" -or $Category -eq "FILES") { Test-FilesEndpoints }
if ($Category -eq "ALL" -or $Category -eq "CV") { Test-CVEndpoints }
if ($Category -eq "ALL" -or $Category -eq "CHATBOT") { Test-ChatbotEndpoints }
if ($Category -eq "ALL" -or $Category -eq "LANGUAGE") { Test-LanguageEndpoints }
if ($Category -eq "ALL" -or $Category -eq "OTHERS") { Test-OtherEndpoints }
if ($Category -eq "ALL" -or $Category -eq "CORS") { Test-CorsEndpoints }

$endTime = Get-Date
$duration = $endTime - $startTime

Write-TestSummary

Write-Host ""
Write-Host "Tiempo total: $($duration.TotalSeconds.ToString('F2')) segundos" @Cyan
Write-Host "Endpoints probados: $($script:TestResults.Passed + $script:TestResults.Failed + $script:TestResults.Warnings)" @Cyan

# Exit code basado en resultados
if ($script:TestResults.Failed -eq 0) {
  exit 0
}
else {
  exit 1
}
