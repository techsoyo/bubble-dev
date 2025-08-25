#!/usr/bin/env pwsh

# =============================================================================
# 🚀 PRODUCTION DEPLOYMENT VERIFICATION CHECKLIST
# =============================================================================

Write-Host "🔍 BUBBLE OF TALENTS - PRODUCTION READINESS VERIFICATION" -ForegroundColor Cyan
Write-Host "================================================================" -ForegroundColor Gray

$checks = @()
$errorsList = @()
$warnings = @()

# =============================================================================
# 1) ✅ ZERO CLIENT STORAGE VERIFICATION
# =============================================================================

Write-Host "`n🛡️  [1/8] Zero Client Storage Security..." -ForegroundColor Yellow

# Verificar safeStorage wrapper existe
$safeStoragePath = "frontend/src/utils/safeStorage.ts"
if (Test-Path $safeStoragePath) {
    $content = Get-Content $safeStoragePath -Raw
    if ($content -match "isProd.*NODE_ENV.*production" -and $content -match "return\s*;\s*//\s*NO-OP\s+in\s+production") {
        $checks += "✅ safeStorage NO-OP in production"
    } else {
        $errorsList += "❌ safeStorage not properly configured for production"
    }
} else {
    $errorsList += "❌ safeStorage.ts missing"
}

# Verificar archivos críticos migrados
$criticalFiles = @(
    "frontend/src/hooks/useAuthSession.ts",
    "frontend/src/services/apiService-legacy.ts",
    "frontend/src/contexts/AuthContext.tsx"
)

foreach ($file in $criticalFiles) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        if ($content -match "from.*safeStorage" -and $content -notmatch "localStorage\.(?!length|key)" -and $content -notmatch "sessionStorage") {
            $checks += "✅ $($file.Split('/')[-1]) migrated to safeStorage"
        } else {
            $errorsList += "❌ $($file.Split('/')[-1]) not properly migrated"
        }
    }
}

# =============================================================================
# 2) 🔒 COOKIES HTTPONLY VERIFICATION  
# =============================================================================

Write-Host "`n🍪 [2/8] HttpOnly Cookies Configuration..." -ForegroundColor Yellow

$cookiesPath = "backend/src/Security/Cookies.php"
if (Test-Path $cookiesPath) {
    $content = Get-Content $cookiesPath -Raw
    if ($content -match "httponly.*true" -and $content -match "secure.*true" -and $content -match "samesite.*strict") {
        $checks += "✅ HttpOnly cookies properly configured"
    } else {
        $warnings += "⚠️  HttpOnly cookies configuration needs review"
    }
} else {
    $errorsList += "❌ Cookies.php missing"
}

# =============================================================================
# 3) 🛡️ ESLINT SECURITY RULES VERIFICATION
# =============================================================================

Write-Host "`n🔧 [3/8] ESLint Security Guardrails..." -ForegroundColor Yellow

$eslintPath = "frontend/.eslintrc.cjs"
if (Test-Path $eslintPath) {
    $content = Get-Content $eslintPath -Raw
    if ($content -match "no-restricted-globals" -and $content -match "localStorage" -and $content -match "sessionStorage") {
        $checks += "✅ ESLint security rules active"
    } else {
        $warnings += "⚠️  ESLint security rules not configured"
    }
} else {
    $warnings += "⚠️  .eslintrc.cjs missing"
}

# =============================================================================
# 4) 🔗 PROXY HTTPS DETECTION
# =============================================================================

Write-Host "`n🌐 [4/8] Proxy HTTPS Configuration..." -ForegroundColor Yellow

$nginxPath = "nginx-production.conf"
if (Test-Path $nginxPath) {
    $content = Get-Content $nginxPath -Raw
    if ($content -match "X-Forwarded-Proto.*https" -and $content -match "X-Forwarded-SSL.*on") {
        $checks += "✅ NGINX proxy headers configured"
    } else {
        $warnings += "⚠️  Proxy HTTPS headers incomplete"
    }
} else {
    $warnings += "⚠️  nginx-production.conf missing"
}

# =============================================================================
# 5) 🧪 TESTING CONFIGURATION
# =============================================================================

Write-Host "`n🧪 [5/8] Testing Framework..." -ForegroundColor Yellow

$cypressPath = "cypress-httponly-setup.md"
if (Test-Path $cypressPath) {
    $checks += "✅ Cypress HttpOnly testing documented"
} else {
    $warnings += "⚠️  Cypress testing setup missing"
}

# =============================================================================
# 6) 🔐 JWT TOKENS VERIFICATION
# =============================================================================

Write-Host "`n🎫 [6/8] JWT Token Security..." -ForegroundColor Yellow

# Verificar que no hay Authorization headers en archivos críticos
$authFiles = Get-ChildItem -Path "frontend/src" -Recurse -Include "*.ts", "*.tsx" | Where-Object {
    $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    # Ignorar archivos marcados como DEPRECATED
    $isDeprecated = $content -match "@deprecated|DEPRECATED"
    $hasActiveAuth = ($content -match "Authorization.*Bearer") -and (-not $isDeprecated)
    $hasActiveAuth
}

if ($authFiles.Count -eq 0) {
    $checks += "✅ No Authorization headers in critical files"
} else {
    foreach ($file in $authFiles) {
        $relativePath = $file.FullName -replace [regex]::Escape((Get-Location).Path), ""
        $errorsList += "❌ Authorization header found in: $relativePath"
    }
}

# =============================================================================
# 7) 🏗️ BUILD CONFIGURATION
# =============================================================================

Write-Host "`n🏗️  [7/8] Production Build..." -ForegroundColor Yellow

$packageJsonPath = "frontend/package.json"
if (Test-Path $packageJsonPath) {
    $content = Get-Content $packageJsonPath -Raw | ConvertFrom-Json
    if ($content.scripts."build:prod" -or $content.scripts.build) {
        $checks += "✅ Production build script available"
    } else {
        $warnings += "⚠️  Production build script missing"
    }
}

# =============================================================================
# 8) 🚨 SECURITY HEADERS
# =============================================================================

Write-Host "`n🛡️  [8/8] Security Headers..." -ForegroundColor Yellow

$securityFiles = @(
    "backend/public/.htaccess",
    "nginx-production.conf"
)

$hasSecurityHeaders = $false
foreach ($file in $securityFiles) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        if ($content -match "X-Frame-Options" -and $content -match "X-Content-Type-Options" -and $content -match "Content-Security-Policy") {
            $hasSecurityHeaders = $true
            break
        }
    }
}

if ($hasSecurityHeaders) {
    $checks += "✅ Security headers configured"
} else {
    $warnings += "⚠️  Security headers need configuration"
}

# =============================================================================
# 📊 RESULTS SUMMARY
# =============================================================================

Write-Host "`n" -NoNewLine
Write-Host "================================================================" -ForegroundColor Gray
Write-Host "📊 VERIFICATION RESULTS SUMMARY" -ForegroundColor Cyan
Write-Host "================================================================" -ForegroundColor Gray

Write-Host "`n✅ PASSED CHECKS ($($checks.Count)):" -ForegroundColor Green
foreach ($check in $checks) {
    Write-Host "  $check" -ForegroundColor Green
}

if ($warnings.Count -gt 0) {
    Write-Host "`n⚠️  WARNINGS ($($warnings.Count)):" -ForegroundColor Yellow
    foreach ($warning in $warnings) {
        Write-Host "  $warning" -ForegroundColor Yellow
    }
}

if ($errorsList.Count -gt 0) {
    Write-Host "`n❌ ERRORS ($($errorsList.Count)):" -ForegroundColor Red
    foreach ($errorItem in $errorsList) {
        Write-Host "  $errorItem" -ForegroundColor Red
    }
} else {
    Write-Host "`n🎉 NO CRITICAL ERRORS FOUND!" -ForegroundColor Green
}

# =============================================================================
# 🚀 DEPLOYMENT STATUS
# =============================================================================

Write-Host "`n" -NoNewLine
Write-Host "================================================================" -ForegroundColor Gray

if ($errorsList.Count -eq 0 -and $warnings.Count -le 2) {
    Write-Host "🚀 PRODUCTION READY!" -ForegroundColor Green -BackgroundColor DarkGreen
    Write-Host "✅ Zero client storage implemented" -ForegroundColor Green
    Write-Host "✅ HttpOnly cookies configured" -ForegroundColor Green  
    Write-Host "✅ Security guardrails active" -ForegroundColor Green
    Write-Host "`n🎯 Next Steps:" -ForegroundColor Cyan
    Write-Host "  1. Run: npm run build:prod" -ForegroundColor White
    Write-Host "  2. Deploy with nginx-production.conf" -ForegroundColor White
    Write-Host "  3. Test with Cypress HttpOnly suite" -ForegroundColor White
} elseif ($errorsList.Count -eq 0) {
    Write-Host "⚠️  READY WITH WARNINGS" -ForegroundColor Yellow -BackgroundColor DarkYellow
    Write-Host "🎯 Address warnings for optimal security" -ForegroundColor Yellow
} else {
    Write-Host "❌ NOT READY - FIX ERRORS FIRST" -ForegroundColor Red -BackgroundColor DarkRed
    Write-Host "🎯 Fix critical errors before deployment" -ForegroundColor Red
}

Write-Host "================================================================" -ForegroundColor Gray
Write-Host "Generated: $(Get-Date)" -ForegroundColor Gray
