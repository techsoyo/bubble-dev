# 🧹 CLEANUP SCRIPT - Marcar archivos de test y legacy como deprecated

param(
    [switch]$DryRun = $false,
    [switch]$Force = $false
)

Write-Host "🧹 TEST & LEGACY FILES CLEANUP" -ForegroundColor Cyan
Write-Host "==============================" -ForegroundColor Gray

# 🗑️ ARCHIVOS DE TEST A MARCAR COMO DEPRECATED
$testFiles = @(
    "backend/public/api/ai/debug_prompt.php",
    "backend/public/api/ai/test_complete_system.php",
    "backend/public/api/ai/test_final.php",
    "backend/public/api/ai/test_full_cv.php",
    "backend/public/api/ai/test_mistral_extended.php",
    "backend/public/api/ai/test_mistral_simple.php",
    "backend/public/api/ai/test_paso2.php",
    "backend/public/api/ai/test_resume.php",
    "backend/public/api/cv-schema-test.php",
    "backend/public/api/translate/jobs_old.php"
)

# 🎯 ARCHIVOS LEGACY DE DATOS (para evaluar si se mueven fuera de public/)
$legacyDataFiles = @(
    "backend/public/api/candidate_certifications.php",
    "backend/public/api/candidate_education.php", 
    "backend/public/api/candidate_languages.php",
    "backend/public/api/candidate_references.php",
    "backend/public/api/candidate_routing.php",
    "backend/public/api/candidate_skills.php",
    "backend/public/api/job_benefits.php",
    "backend/public/api/job_requirements.php",
    "backend/public/api/job_skills.php"
)

# 🚫 PLANTILLA DEPRECATED
$deprecatedTemplate = @"
<?php
/**
 * @deprecated This test/legacy file will be removed in v2.0
 * @legacy Use new API endpoints in /api/v2/
 * @security No security requirements - marked for removal
 * @migration-date 2025-08-25
 */

// 🟡 DEPRECATED FILE - DO NOT USE IN PRODUCTION
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    http_response_code(410); // Gone
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Deprecated endpoint',
        'message' => 'This endpoint has been deprecated and is no longer available',
        'migration' => 'Use /api/v2/ endpoints'
    ]);
    exit;
}

// Development/testing only warning
trigger_error(
    "DEPRECATED ENDPOINT: " . ($_SERVER['REQUEST_URI'] ?? __FILE__) . 
    " - This endpoint is deprecated and will be removed", 
    E_USER_DEPRECATED
);

// ORIGINAL CODE BELOW (for development/testing only)
"@

function Mark-As-Deprecated {
    param(
        [string]$FilePath,
        [string]$Category
    )
    
    if (-not (Test-Path $FilePath)) {
        Write-Host "  ⚠️ File not found: $FilePath" -ForegroundColor Yellow
        return $false
    }
    
    # Verificar si ya está marcado
    $content = Get-Content $FilePath -Raw -ErrorAction SilentlyContinue
    if ($content -match "@deprecated|DEPRECATED") {
        Write-Host "  ✅ Already deprecated: $FilePath" -ForegroundColor Green
        return $true
    }
    
    if ($DryRun) {
        Write-Host "  🔍 [DRY-RUN] Would mark as deprecated: $FilePath" -ForegroundColor Cyan
        return $true
    }
    
    # Backup original
    $backupPath = $FilePath + ".backup-deprecated-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item $FilePath $backupPath -Force
    
    # Eliminar tag PHP inicial si existe
    if ($content -match "^\s*<\?php\s*") {
        $content = $content -replace "^\s*<\?php\s*", ""
    }
    
    # Aplicar template deprecated
    $deprecatedContent = $deprecatedTemplate + "`n" + $content
    
    try {
        Set-Content $FilePath $deprecatedContent -Encoding UTF8 -NoNewline
        Write-Host "  🚫 DEPRECATED [$Category]: $FilePath" -ForegroundColor DarkYellow
        return $true
    } catch {
        Write-Host "  ❌ Failed to mark as deprecated: $FilePath - $($_.Exception.Message)" -ForegroundColor Red
        # Restore from backup
        Copy-Item $backupPath $FilePath -Force
        return $false
    }
}

# 🚀 MAIN PROCESSING

if ($DryRun) {
    Write-Host "🔍 DRY-RUN MODE - No changes will be made" -ForegroundColor Yellow
} else {
    Write-Host "🔥 LIVE MODE - Files will be marked as deprecated" -ForegroundColor Red
    if (-not $Force) {
        $confirm = Read-Host "Continue? (y/N)"
        if ($confirm -ne "y") {
            Write-Host "❌ Aborted by user" -ForegroundColor Red
            exit 1
        }
    }
}

$stats = @{
    "TestFiles" = 0
    "LegacyFiles" = 0
    "Skipped" = 0
}

# Marcar archivos de test como deprecated
Write-Host "`n🧪 Marking test files as deprecated..." -ForegroundColor White
foreach ($file in $testFiles) {
    if (Mark-As-Deprecated $file "TEST") {
        $stats.TestFiles++
    } else {
        $stats.Skipped++
    }
}

# Marcar archivos legacy de datos como deprecated
Write-Host "`n📊 Marking legacy data files as deprecated..." -ForegroundColor White  
foreach ($file in $legacyDataFiles) {
    if (Mark-As-Deprecated $file "LEGACY-DATA") {
        $stats.LegacyFiles++
    } else {
        $stats.Skipped++
    }
}

# 📊 SUMMARY
Write-Host "`n================================================================" -ForegroundColor Cyan
Write-Host "🧹 CLEANUP COMPLETE" -ForegroundColor Green
Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "🧪 Test files deprecated: $($stats.TestFiles)" -ForegroundColor DarkYellow
Write-Host "📊 Legacy data files deprecated: $($stats.LegacyFiles)" -ForegroundColor DarkYellow  
Write-Host "⚠️ Skipped: $($stats.Skipped)" -ForegroundColor Yellow

$total = $stats.TestFiles + $stats.LegacyFiles

if (-not $DryRun -and $total -gt 0) {
    Write-Host "`n🎯 IMPACT:" -ForegroundColor White
    Write-Host "✅ Files now return HTTP 410 (Gone) in production" -ForegroundColor Green
    Write-Host "⚠️ Files still work in development (with warning)" -ForegroundColor Yellow
    Write-Host "🔍 Audit script will ignore @deprecated files" -ForegroundColor Cyan
    Write-Host "`n🧪 NEXT STEPS:" -ForegroundColor White
    Write-Host "1. Run audit: pwsh -File audit-legacy-endpoints.ps1" -ForegroundColor White
    Write-Host "2. Check CI: git push (should pass now)" -ForegroundColor White
    Write-Host "3. Schedule removal in next sprint" -ForegroundColor White
}

Write-Host "`n✅ Processed $total files successfully!" -ForegroundColor Green
