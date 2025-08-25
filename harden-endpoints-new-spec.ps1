#!/usr/bin/env pwsh

# SCRIPT DE ENDURECIMIENTO SEGÚN ESPECIFICACIÓN NUEVA
# Aplica las plantillas exactas especificadas por el usuario

$ErrorActionPreference = "Continue"

Write-Host "=== ENDURECIMIENTO AUTOMÁTICO BACKEND/PUBLIC/API ===" -ForegroundColor Cyan
Write-Host "Aplicando especificación detallada..." -ForegroundColor Yellow
Write-Host ""

# Obtener archivos en scope según especificación
$apiPath = "backend/public/api"
$paths = Get-ChildItem -Path $apiPath -Recurse -Include "*.php" | 
    Where-Object { $_.FullName -notmatch "\\vendor\\|\\tests\\|\\scripts\\|\\config\\|\\tools\\|\\index\.php$|\\simple\.php$" }

Write-Host "ARCHIVOS EN SCOPE: $($paths.Count)" -ForegroundColor Green

# PLANTILLA PRIVADA COMPLETA (según especificación)
$privateTemplate = @'
require_once __DIR__ . '/{BOOTSTRAP_PATH}/bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}
'@

# CATEGORIZAR ARCHIVOS
$publicEndpoints = @()
$privateEndpoints = @()
$testFiles = @()
$legacyFiles = @()

foreach ($file in $paths) {
    $content = Get-Content $file.FullName -Raw
    $fileName = $file.Name
    
    # PÚBLICOS: OAuth/SSO flows o CSRF token-only
    if ($fileName -match "social-login|social-callback|OAuthHandler|validate-csrf|csrf-token" -or 
        $content -match "@public") {
        $publicEndpoints += $file
    }
    # ARCHIVOS TEST: mover o deprecar
    elseif ($fileName -match "test_|debug_|_test\.php$") {
        $testFiles += $file
    }
    # LEGACY: jobs_old, cv-schema-test, etc
    elseif ($fileName -match "jobs_old|cv-schema-test|simple\.php|index\.php") {
        $legacyFiles += $file
    }
    # PRIVADOS: todo lo demás necesita JWT + CSRF
    else {
        $privateEndpoints += $file
    }
}

Write-Host "PÚBLICOS: $($publicEndpoints.Count)" -ForegroundColor Green
Write-Host "PRIVADOS: $($privateEndpoints.Count)" -ForegroundColor Yellow  
Write-Host "TEST FILES: $($testFiles.Count)" -ForegroundColor Red
Write-Host "LEGACY: $($legacyFiles.Count)" -ForegroundColor Magenta
Write-Host ""

# APLICAR PLANTILLA A ENDPOINTS PRIVADOS
Write-Host "=== APLICANDO PLANTILLA PRIVADA ===" -ForegroundColor Cyan

$processed = 0
foreach ($file in $privateEndpoints) {
    try {
        $content = Get-Content $file.FullName -Raw
        
        # Saltar si ya tiene la implementación completa
        if ($content -match "JWTMiddleware::requireAuth" -and 
            $content -match "CsrfMiddleware::protect" -and
            $content -match "HTTP_AUTHORIZATION.*production") {
            Write-Host "SKIP: $($file.Name) - ya implementado" -ForegroundColor Green
            continue
        }
        
        # Determinar bootstrap path relativo
        $relativePath = $file.DirectoryName.Replace((Get-Location).Path + "\backend\public\api", "")
        $bootstrapPath = "../" * ($relativePath.Split('\').Count - 1)
        if ($bootstrapPath -eq "") { $bootstrapPath = "./" }
        
        # Aplicar plantilla
        $template = $privateTemplate.Replace('{BOOTSTRAP_PATH}', $bootstrapPath.TrimEnd('/'))
        
        # Encontrar después de <?php y declare
        if ($content -match "(?s)^(<\?php\s*(?:declare\(strict_types=1\);\s*)?)(.*)") {
            $phpHeader = $matches[1]
            $restContent = $matches[2]
            
            # Quitar requires duplicados
            $restContent = $restContent -replace "require_once.*bootstrap\.php.*?;\s*", ""
            $restContent = $restContent -replace "JWTMiddleware::requireAuth\(\).*?;\s*", ""
            $restContent = $restContent -replace "CsrfMiddleware::protect\(\).*?;\s*", ""
            
            $newContent = $phpHeader + "`r`n`r`n" + $template + "`r`n`r`n" + $restContent.TrimStart()
            
            # Backup
            $backupPath = $file.FullName + ".backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
            Copy-Item $file.FullName $backupPath
            
            # Aplicar
            Set-Content $file.FullName $newContent -Encoding UTF8
            Write-Host "✅ APLICADO: $($file.Name)" -ForegroundColor Green
            $processed++
        }
    }
    catch {
        Write-Host "❌ ERROR: $($file.Name) - $($_.Exception.Message)" -ForegroundColor Red
    }
}

# MARCAR ARCHIVOS PÚBLICOS
Write-Host "`n=== MARCANDO ENDPOINTS PÚBLICOS ===" -ForegroundColor Cyan
foreach ($file in $publicEndpoints) {
    try {
        $content = Get-Content $file.FullName -Raw
        if (-not ($content -match "// @public")) {
            $lines = Get-Content $file.FullName
            $lines[0] = $lines[0] + "`r`n// @public"
            Set-Content $file.FullName $lines -Encoding UTF8
            Write-Host "✅ MARCADO: $($file.Name)" -ForegroundColor Green
        }
    }
    catch {
        Write-Host "❌ ERROR: $($file.Name) - $($_.Exception.Message)" -ForegroundColor Red
    }
}

# DEPRECAR ARCHIVOS TEST
Write-Host "`n=== DEPRECANDO ARCHIVOS TEST ===" -ForegroundColor Cyan
foreach ($file in $testFiles) {
    try {
        $content = Get-Content $file.FullName -Raw
        if (-not ($content -match "@deprecated")) {
            $newContent = "<?php`r`n// @deprecated - archivo de test, deshabilitar en producción`r`nif ((\$_ENV['APP_ENV'] ?? 'production') === 'production') {`r`n    http_response_code(404);`r`n    exit('Not found');`r`n}`r`n`r`n" + $content.Substring(5)
            Set-Content $file.FullName $newContent -Encoding UTF8
            Write-Host "✅ DEPRECATED: $($file.Name)" -ForegroundColor Yellow
        }
    }
    catch {
        Write-Host "❌ ERROR: $($file.Name) - $($_.Exception.Message)" -ForegroundColor Red
    }
}

Write-Host "`n=== RESUMEN ===" -ForegroundColor Cyan
Write-Host "Endpoints privados procesados: $processed" -ForegroundColor Green
Write-Host "Endpoints públicos marcados: $($publicEndpoints.Count)" -ForegroundColor Green  
Write-Host "Archivos test deprecados: $($testFiles.Count)" -ForegroundColor Yellow
Write-Host "Archivos legacy identificados: $($legacyFiles.Count)" -ForegroundColor Magenta

Write-Host "`n✅ ENDURECIMIENTO AUTOMÁTICO COMPLETADO" -ForegroundColor Green
