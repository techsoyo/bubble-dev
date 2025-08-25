# =============================================
# Script para aplicar semilla de job_requirements y job_skills
# Fecha: 25 de agosto de 2025
# Propósito: Migrar datos mock hardcodeados a base de datos
# =============================================

param(
    [string]$Host = "localhost",
    [string]$Database = "bubble_talents",
    [string]$Username = "root",
    [string]$Password = ""
)

Write-Host "🌱 Aplicando semilla para job_requirements y job_skills..." -ForegroundColor Green

$seedFile = "backend\database\seeds\job_requirements_skills_seed.sql"

if (-not (Test-Path $seedFile)) {
    Write-Host "❌ Error: No se encuentra el archivo de semilla: $seedFile" -ForegroundColor Red
    exit 1
}

try {
    Write-Host "📊 Conectando a base de datos: $Database@$Host" -ForegroundColor Cyan
    
    # Construir comando MySQL
    $mysqlCmd = "mysql -h$Host -u$Username"
    if ($Password) {
        $mysqlCmd += " -p$Password"
    }
    $mysqlCmd += " $Database"
    
    Write-Host "🔄 Ejecutando semilla..." -ForegroundColor Yellow
    
    # Ejecutar archivo SQL
    Get-Content $seedFile | & cmd /c "$mysqlCmd"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Semilla aplicada exitosamente!" -ForegroundColor Green
        Write-Host ""
        Write-Host "📋 Próximos pasos:" -ForegroundColor Cyan
        Write-Host "1. Actualizar job_requirements.php para usar datos de BD" -ForegroundColor White
        Write-Host "2. Actualizar job_skills.php para usar datos de BD" -ForegroundColor White
        Write-Host "3. Remover arrays mockData hardcodeados" -ForegroundColor White
    } else {
        Write-Host "❌ Error al aplicar la semilla" -ForegroundColor Red
        exit 1
    }
    
} catch {
    Write-Host "❌ Error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎯 Para verificar los datos insertados, ejecuta:" -ForegroundColor Cyan
Write-Host "   SELECT * FROM bt_job_requirements WHERE job_id = 1;" -ForegroundColor White
Write-Host "   SELECT * FROM bt_job_skills WHERE job_id = 1;" -ForegroundColor White
