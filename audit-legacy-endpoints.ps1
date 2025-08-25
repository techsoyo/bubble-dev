#!/usr/bin/env pwsh

# =============================================================================
# 🔍 LEGACY ENDPOINTS AUDIT - Identificar endpoints sin cookies+CSRF
# =============================================================================

Write-Host "🔍 BUBBLE OF TALENTS - LEGACY ENDPOINTS AUDIT" -ForegroundColor Cyan
Write-Host "================================================================" -ForegroundColor Gray

$legacyEndpoints = @()
$warnings = @()

# =============================================================================
# BUSCAR ENDPOINTS SIN PROTECCIÓN CSRF
# =============================================================================

Write-Host "`n🛡️ [1/4] Buscando endpoints sin protección CSRF..." -ForegroundColor Yellow

# Solo puntos de entrada reales en backend/public/api
$paths = Get-ChildItem -Path "backend/public/api" -Recurse -Include "*.php" |
  Where-Object {
    $_.FullName -notmatch "\\vendor\\|\\tests\\|\\scripts\\|\\config\\|\\tools\\|\\index\.php$|\\simple\.php$"
  }

# 1) SIN CSRF (solo métodos de escritura)
$phpFiles = $paths | Where-Object {
  $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
  ($content -match "POST|PUT|DELETE|PATCH") -and
  ($content -notmatch "CsrfMiddleware") -and
  ($content -notmatch "@public")
}

if ($phpFiles.Count -gt 0) {
    Write-Host "⚠️  ENDPOINTS SIN CSRF ENCONTRADOS:" -ForegroundColor Yellow
    foreach ($file in $phpFiles) {
        $relativePath = $file.FullName -replace [regex]::Escape((Get-Location).Path), ""
        Write-Host "   📄 $relativePath" -ForegroundColor Red
        $legacyEndpoints += $relativePath
    }
} else {
    Write-Host "   ✅ Todos los endpoints usan CsrfMiddleware" -ForegroundColor Green
}

# =============================================================================  
# BUSCAR ENDPOINTS QUE AÚN USAN Authorization HEADER
# =============================================================================

Write-Host "`n🎫 [2/4] Buscando endpoints con Authorization header fallback..." -ForegroundColor Yellow

# 2) Authorization fallback
$authFiles = $paths | Where-Object {
  $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
  ($content -match "Authorization.*Bearer|getallheaders.*Authorization") -and
  ($content -notmatch "@public")
}

if ($authFiles.Count -gt 0) {
    Write-Host "⚠️  ENDPOINTS CON AUTHORIZATION FALLBACK:" -ForegroundColor Yellow
    foreach ($file in $authFiles) {
        $relativePath = $file.FullName -replace [regex]::Escape((Get-Location).Path), ""
        Write-Host "   📄 $relativePath" -ForegroundColor Red
        $legacyEndpoints += $relativePath
    }
} else {
    Write-Host "   ✅ Todos los endpoints usan solo cookies httpOnly" -ForegroundColor Green  
}

# =============================================================================
# BUSCAR ENDPOINTS SIN JWTMiddleware
# =============================================================================

Write-Host "`n🔐 [3/4] Buscando endpoints sin JWTMiddleware..." -ForegroundColor Yellow

# 3) SIN JWT
$unprotectedFiles = $paths | Where-Object {
  $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
  ($content -notmatch "JWTMiddleware::requireAuth") -and
  ($content -notmatch "@public") -and
  ($content -notmatch "login|register|logout")
}

if ($unprotectedFiles.Count -gt 0) {
    Write-Host "⚠️  ENDPOINTS SIN JWT PROTECTION:" -ForegroundColor Yellow
    foreach ($file in $unprotectedFiles) {
        $relativePath = $file.FullName -replace [regex]::Escape((Get-Location).Path), ""
        Write-Host "   📄 $relativePath" -ForegroundColor Red
        $legacyEndpoints += $relativePath
    }
} else {
    Write-Host "   ✅ Todos los endpoints privados usan JWTMiddleware" -ForegroundColor Green
}

# =============================================================================
# GENERAR PLAN DE MIGRACIÓN
# =============================================================================

Write-Host "`n📋 [4/4] Generando plan de migración..." -ForegroundColor Yellow

$uniqueEndpoints = $legacyEndpoints | Sort-Object | Get-Unique

if ($uniqueEndpoints.Count -gt 0) {
    Write-Host "`n================================================================" -ForegroundColor Gray
    Write-Host "📊 RESUMEN DE ENDPOINTS LEGACY" -ForegroundColor Cyan
    Write-Host "================================================================" -ForegroundColor Gray
    
    Write-Host "`n🔴 ENDPOINTS LEGACY IDENTIFICADOS ($($uniqueEndpoints.Count)):" -ForegroundColor Red
    foreach ($endpoint in $uniqueEndpoints) {
        Write-Host "   🟡 $endpoint" -ForegroundColor Yellow
    }
    
    Write-Host "`n🎯 PLAN DE MIGRACIÓN RECOMENDADO:" -ForegroundColor Cyan
    Write-Host "=================================" -ForegroundColor Gray
    Write-Host "1. 🛡️ Añadir CsrfMiddleware::protect() a endpoints de escritura" -ForegroundColor White
    Write-Host "2. 🔐 Integrar JWTMiddleware::requireAuth() para autenticacion" -ForegroundColor White  
    Write-Host "3. 🍪 Eliminar fallback Authorization headers" -ForegroundColor White
    Write-Host "4. 🧪 Añadir tests Cypress para verificar cookies httpOnly" -ForegroundColor White
    Write-Host "5. ⚠️  Marcar endpoints no críticos como @deprecated" -ForegroundColor White
    
    Write-Host "`n🚨 PRIORIDAD:" -ForegroundColor Red
    if ($uniqueEndpoints.Count -le 5) {
        Write-Host "   🔥 ALTA - Pocos endpoints, migración inmediata" -ForegroundColor Red
    } elseif ($uniqueEndpoints.Count -le 15) {
        Write-Host "   🟡 MEDIA - Migración por fases en 2-3 sprints" -ForegroundColor Yellow
    } else {
        Write-Host "   🟢 BAJA - Migración gradual, marcar deprecated primero" -ForegroundColor Green
    }
    
} else {
    Write-Host "`n🎉 ¡EXCELENTE!" -ForegroundColor Green
    Write-Host "================================================================" -ForegroundColor Gray
    Write-Host "✅ NO HAY ENDPOINTS LEGACY DETECTADOS" -ForegroundColor Green
    Write-Host "✅ Todos los endpoints usan cookies httpOnly + CSRF protection" -ForegroundColor Green
    Write-Host "🚀 Sistema completamente enterprise-ready" -ForegroundColor Green
}

Write-Host "`n================================================================" -ForegroundColor Gray
Write-Host "Generated: $(Get-Date -Format 'MM/dd/yyyy HH:mm:ss')" -ForegroundColor Gray
