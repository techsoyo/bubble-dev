# 🔧 AUTO-HARDENING SCRIPT - Aplicar plantillas de seguridad a endpoints
param(
    [switch]$DryRun = $false,
    [switch]$SkipAI = $false,
    [switch]$Force = $false
)

$ErrorActionPreference = "Stop"

Write-Host "🛡️ ENDPOINTS HARDENING AUTOMATION" -ForegroundColor Cyan
Write-Host "=================================" -ForegroundColor Gray

# 🎯 PLANTILLAS DE SEGURIDAD
$privateEndpointTemplate = @"
<?php
require_once __DIR__ . '/bootstrap.php';
JWTMiddleware::requireAuth();                  // cookie HttpOnly obligatoria

// Proteger solo métodos que cambian estado
`$method = `$_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array(`$method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect();                 // double-submit cookie
}

// En producción NO aceptar Authorization header (solo cookie)
if ((`$_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty(`$_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
"@

$readOnlyEndpointTemplate = @"
<?php
require_once __DIR__ . '/bootstrap.php';
JWTMiddleware::requireAuth();                  // cookie HttpOnly obligatoria

// En producción NO aceptar Authorization header (solo cookie)
if ((`$_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty(`$_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
"@

$publicEndpointTemplate = @"
<?php
// @public
require_once __DIR__ . '/bootstrap.php';
// NO JWTMiddleware::requireAuth() aquí - endpoint público
// NO CsrfMiddleware::protect() aquí - endpoint público

// ORIGINAL CODE BELOW
"@

# 📋 ENDPOINTS POR CATEGORÍA
$endpoints = @{
    "PrivateWithCSRF" = @(
        "backend/public/api/candidates/save_v2.php",
        "backend/public/api/application_notes.php",
        "backend/public/api/auth_register.php",
        "backend/public/api/change-password.php",
        "backend/public/api/chatbot_analytics.php",
        "backend/public/api/chatbot_decision_tree.php",
        "backend/public/api/chatbot_nodes.php",
        "backend/public/api/chatbot_options.php",
        "backend/public/api/chatbot.php",
        "backend/public/api/cv/delete.php",
        "backend/public/api/cv/ingest.php",
        "backend/public/api/send-email.php",
        "backend/public/api/staff_login.php"
    )
    
    "ReadOnlyPrivate" = @(
        "backend/public/api/departments.php",
        "backend/public/api/get-notification-preferences.php",
        "backend/public/api/health-ai.php",
        "backend/public/api/health-check.php", 
        "backend/public/api/health-ocr.php",
        "backend/public/api/health.php",
        "backend/public/api/interviews.php",
        "backend/public/api/jobs.php",
        "backend/public/api/language.php",
        "backend/public/api/news.php",
        "backend/public/api/recruiters.php",
        "backend/public/api/statistics.php"
    )
    
    "Public" = @(
        "backend/public/api/auth/csrf-token.php",
        "backend/public/api/health.php"
    )
    
    "AIEndpoints" = @(
        "backend/public/api/ai/calculate-matching.php",
        "backend/public/api/ai/candidate-matching.php", 
        "backend/public/api/ai/communication.php",
        "backend/public/api/ai/content-generation.php",
        "backend/public/api/ai/extract-cv-stage1.php",
        "backend/public/api/ai/extract-cv-stage2.php",
        "backend/public/api/ai/extract-skills.php",
        "backend/public/api/ai/parse-cv-file.php",
        "backend/public/api/ai/parse-cv-openai.php",
        "backend/public/api/ai/pre-screening.php",
        "backend/public/api/ai/process-cv-complete.php",
        "backend/public/api/ai/recruitment-insights.php",
        "backend/public/api/ai/resume-cv.php"
    )
}

function Apply-Template {
    param(
        [string]$FilePath,
        [string]$Template,
        [string]$Category
    )
    
    if (-not (Test-Path $FilePath)) {
        Write-Host "  ⚠️ File not found: $FilePath" -ForegroundColor Yellow
        return $false
    }
    
    # Leer contenido original
    $originalContent = Get-Content $FilePath -Raw -ErrorAction SilentlyContinue
    
    if ($originalContent -match "JWTMiddleware::requireAuth|@public") {
        Write-Host "  ✅ Already secured: $FilePath" -ForegroundColor Green
        return $true
    }
    
    if ($DryRun) {
        Write-Host "  🔍 [DRY-RUN] Would apply $Category template to: $FilePath" -ForegroundColor Cyan
        return $true
    }
    
    # Backup original
    $backupPath = $FilePath + ".backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item $FilePath $backupPath -Force
    
    # Eliminar tag PHP inicial si existe
    if ($originalContent -match "^\s*<\?php\s*") {
        $originalContent = $originalContent -replace "^\s*<\?php\s*", ""
    }
    
    # Aplicar template
    $securedContent = $Template + "`n" + $originalContent
    
    try {
        Set-Content $FilePath $securedContent -Encoding UTF8 -NoNewline
        Write-Host "  🛡️ SECURED [$Category]: $FilePath" -ForegroundColor Green
        return $true
    } catch {
        Write-Host "  ❌ Failed to secure: $FilePath - $($_.Exception.Message)" -ForegroundColor Red
        # Restore from backup
        Copy-Item $backupPath $FilePath -Force
        return $false
    }
}

# 🚀 MAIN PROCESSING

if ($DryRun) {
    Write-Host "🔍 DRY-RUN MODE - No changes will be made" -ForegroundColor Yellow
} else {
    Write-Host "🔥 LIVE MODE - Files will be modified" -ForegroundColor Red
    if (-not $Force) {
        $confirm = Read-Host "Continue? (y/N)"
        if ($confirm -ne "y") {
            Write-Host "❌ Aborted by user" -ForegroundColor Red
            exit 1
        }
    }
}

$stats = @{
    "PrivateWithCSRF" = 0
    "ReadOnlyPrivate" = 0
    "Public" = 0
    "AIEndpoints" = 0
    "Skipped" = 0
}

# Procesar endpoints privados con CSRF
Write-Host "`n🔒 Processing Private Endpoints (with CSRF)..." -ForegroundColor White
foreach ($endpoint in $endpoints.PrivateWithCSRF) {
    if (Apply-Template $endpoint $privateEndpointTemplate "Private+CSRF") {
        $stats.PrivateWithCSRF++
    } else {
        $stats.Skipped++
    }
}

# Procesar endpoints de solo lectura
Write-Host "`n📖 Processing Read-Only Private Endpoints..." -ForegroundColor White  
foreach ($endpoint in $endpoints.ReadOnlyPrivate) {
    if (Apply-Template $endpoint $readOnlyEndpointTemplate "ReadOnly") {
        $stats.ReadOnlyPrivate++
    } else {
        $stats.Skipped++
    }
}

# Procesar endpoints públicos
Write-Host "`n🌐 Processing Public Endpoints..." -ForegroundColor White
foreach ($endpoint in $endpoints.Public) {
    if (Apply-Template $endpoint $publicEndpointTemplate "Public") {
        $stats.Public++
    } else {
        $stats.Skipped++
    }
}

# Procesar endpoints de AI (si no se omite)
if (-not $SkipAI) {
    Write-Host "`n🤖 Processing AI Endpoints (as Private+CSRF)..." -ForegroundColor White
    foreach ($endpoint in $endpoints.AIEndpoints) {
        if (Apply-Template $endpoint $privateEndpointTemplate "AI+Private") {
            $stats.AIEndpoints++
        } else {
            $stats.Skipped++
        }
    }
} else {
    Write-Host "`n🤖 Skipping AI Endpoints (use -SkipAI:$false to include)" -ForegroundColor Yellow
}

# 📊 SUMMARY
Write-Host "`n================================================================" -ForegroundColor Cyan
Write-Host "🎯 HARDENING COMPLETE" -ForegroundColor Green
Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "🔒 Private+CSRF endpoints: $($stats.PrivateWithCSRF)" -ForegroundColor Green
Write-Host "📖 Read-Only private endpoints: $($stats.ReadOnlyPrivate)" -ForegroundColor Green  
Write-Host "🌐 Public endpoints: $($stats.Public)" -ForegroundColor Green
Write-Host "🤖 AI endpoints: $($stats.AIEndpoints)" -ForegroundColor Green
Write-Host "⚠️ Skipped: $($stats.Skipped)" -ForegroundColor Yellow

$total = $stats.Values | Measure-Object -Sum | Select-Object -ExpandProperty Sum

if (-not $DryRun) {
    Write-Host "`n🧪 RECOMMENDED NEXT STEPS:" -ForegroundColor White
    Write-Host "1. Run audit: pwsh -File audit-legacy-endpoints.ps1" -ForegroundColor White
    Write-Host "2. Test endpoints: npm run test:api" -ForegroundColor White  
    Write-Host "3. Run Cypress: npm run cypress:run" -ForegroundColor White
    Write-Host "4. Check CI: git push" -ForegroundColor White
}

Write-Host "`n✅ Processed $total endpoints successfully!" -ForegroundColor Green
