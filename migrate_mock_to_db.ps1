# =============================================
# Script para migrar job_requirements y job_skills de mock a BD
# Fecha: 25 de agosto de 2025
# Propósito: Reemplazar datos mock con consultas reales a BD
# =============================================

Write-Host "🔄 MIGRANDO DATOS MOCK A BASE DE DATOS..." -ForegroundColor Cyan
Write-Host ""

$backupDir = "backend\public\api\_mock_backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"

try {
    # 1. Crear backup de archivos originales
    Write-Host "📦 Creando backup de archivos originales..." -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    
    Copy-Item "backend\public\api\job_requirements.php" "$backupDir\job_requirements_original.php"
    Copy-Item "backend\public\api\job_skills.php" "$backupDir\job_skills_original.php"
    
    Write-Host "✅ Backup creado en: $backupDir" -ForegroundColor Green
    
    # 2. Aplicar semilla a base de datos
    Write-Host ""
    Write-Host "🌱 Aplicando semilla a base de datos..." -ForegroundColor Yellow
    .\apply_job_seed.ps1
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ Error aplicando semilla. Abortando migración." -ForegroundColor Red
        exit 1
    }
    
    # 3. Reemplazar archivos con versiones actualizadas
    Write-Host ""
    Write-Host "🔄 Reemplazando archivos con versiones de BD..." -ForegroundColor Yellow
    
    Copy-Item "backend\public\api\job_requirements_updated.php" "backend\public\api\job_requirements.php" -Force
    Copy-Item "backend\public\api\job_skills_updated.php" "backend\public\api\job_skills.php" -Force
    
    Write-Host "✅ Archivos actualizados correctamente" -ForegroundColor Green
    
    # 4. Verificar sintaxis PHP
    Write-Host ""
    Write-Host "🔍 Verificando sintaxis PHP..." -ForegroundColor Yellow
    
    $syntaxCheck1 = php -l "backend\public\api\job_requirements.php"
    $syntaxCheck2 = php -l "backend\public\api\job_skills.php"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Sintaxis PHP correcta" -ForegroundColor Green
    } else {
        Write-Host "❌ Error de sintaxis detectado" -ForegroundColor Red
        Write-Host "🔄 Restaurando archivos originales..." -ForegroundColor Yellow
        
        Copy-Item "$backupDir\job_requirements_original.php" "backend\public\api\job_requirements.php" -Force
        Copy-Item "$backupDir\job_skills_original.php" "backend\public\api\job_skills.php" -Force
        
        Write-Host "✅ Archivos restaurados" -ForegroundColor Green
        exit 1
    }
    
    # 5. Limpiar archivos temporales
    Write-Host ""
    Write-Host "🧹 Limpiando archivos temporales..." -ForegroundColor Yellow
    Remove-Item "backend\public\api\job_requirements_updated.php" -ErrorAction SilentlyContinue
    Remove-Item "backend\public\api\job_skills_updated.php" -ErrorAction SilentlyContinue
    
    Write-Host ""
    Write-Host "🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE!" -ForegroundColor Green
    Write-Host ""
    Write-Host "📋 RESUMEN:" -ForegroundColor Cyan
    Write-Host "✅ Datos de semilla insertados en BD" -ForegroundColor White
    Write-Host "✅ job_requirements.php ahora usa datos de bt_job_requirements" -ForegroundColor White
    Write-Host "✅ job_skills.php ahora usa datos de bt_job_skills" -ForegroundColor White
    Write-Host "✅ Backup disponible en: $backupDir" -ForegroundColor White
    Write-Host ""
    Write-Host "🚀 PRÓXIMOS PASOS:" -ForegroundColor Cyan
    Write-Host "1. Probar endpoints: /api/job_requirements.php y /api/job_skills.php" -ForegroundColor White
    Write-Host "2. Verificar que devuelven datos reales de BD" -ForegroundColor White
    Write-Host "3. Actualizar AuthController para remover JWT tokens mock" -ForegroundColor White
    
} catch {
    Write-Host "❌ ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "🔄 Intentando restaurar archivos originales..." -ForegroundColor Yellow
    
    if (Test-Path $backupDir) {
        Copy-Item "$backupDir\job_requirements_original.php" "backend\public\api\job_requirements.php" -Force -ErrorAction SilentlyContinue
        Copy-Item "$backupDir\job_skills_original.php" "backend\public\api\job_skills.php" -Force -ErrorAction SilentlyContinue
        Write-Host "✅ Archivos restaurados" -ForegroundColor Green
    }
    
    exit 1
}
