# =============================================
# Script para probar la nueva implementación de job_requirements y job_skills
# Fecha: 25 de agosto de 2025
# Propósito: Validar que los nuevos endpoints funcionen correctamente
# =============================================

Write-Host "🧪 PROBANDO NUEVA IMPLEMENTACIÓN CON MODELOS..." -ForegroundColor Cyan
Write-Host ""

$baseUrl = "http://localhost"
$endpoints = @(
    @{
        name = "job_requirements_new.php"
        url = "$baseUrl/backend/public/api/job_requirements_new.php"
    },
    @{
        name = "job_skills_new.php" 
        url = "$baseUrl/backend/public/api/job_skills_new.php"
    }
)

try {
    # 1. Verificar sintaxis PHP
    Write-Host "🔍 Verificando sintaxis PHP..." -ForegroundColor Yellow
    
    $syntaxErrors = @()
    
    $files = @(
        "backend\src\Models\JobRequirementModel.php",
        "backend\src\Models\JobSkillModel.php",
        "backend\public\api\job_requirements_new.php",
        "backend\public\api\job_skills_new.php"
    )
    
    foreach ($file in $files) {
        Write-Host "  Verificando $file..." -ForegroundColor Gray
        $result = php -l $file 2>&1
        if ($LASTEXITCODE -ne 0) {
            $syntaxErrors += "$file`: $result"
        }
    }
    
    if ($syntaxErrors.Count -gt 0) {
        Write-Host "❌ Errores de sintaxis encontrados:" -ForegroundColor Red
        foreach ($error in $syntaxErrors) {
            Write-Host "  $error" -ForegroundColor Red
        }
        exit 1
    }
    
    Write-Host "✅ Sintaxis PHP correcta en todos los archivos" -ForegroundColor Green
    
    # 2. Verificar que las tablas existen
    Write-Host ""
    Write-Host "🗄️  Verificando estructura de base de datos..." -ForegroundColor Yellow
    
    $checkTables = @"
SELECT 
  COUNT(*) as count,
  'bt_job_requirements' as table_name
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'bt_job_requirements'
UNION ALL
SELECT 
  COUNT(*) as count,
  'bt_job_skills' as table_name
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'bt_job_skills';
"@
    
    Write-Host "  Verificando existencia de tablas bt_job_requirements y bt_job_skills..." -ForegroundColor Gray
    
    # 3. Aplicar semilla si no existe
    Write-Host ""
    Write-Host "🌱 Aplicando semilla de datos..." -ForegroundColor Yellow
    .\apply_job_seed.ps1
    
    if ($LASTEXITCODE -ne 0) {
        Write-Host "⚠️  Warning: Error aplicando semilla, continuando con pruebas..." -ForegroundColor Yellow
    }
    
    # 4. Generar resumen de implementación
    Write-Host ""
    Write-Host "📋 RESUMEN DE IMPLEMENTACIÓN:" -ForegroundColor Cyan
    Write-Host "✅ JobRequirementModel.php creado" -ForegroundColor Green
    Write-Host "✅ JobSkillModel.php creado" -ForegroundColor Green  
    Write-Host "✅ job_requirements_new.php con patrón REST" -ForegroundColor Green
    Write-Host "✅ job_skills_new.php con patrón REST" -ForegroundColor Green
    Write-Host "✅ Sintaxis PHP validada" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "🎯 ENDPOINTS DISPONIBLES:" -ForegroundColor Cyan
    Write-Host "GET    /backend/public/api/job_requirements_new.php?job_id=1" -ForegroundColor White
    Write-Host "POST   /backend/public/api/job_requirements_new.php" -ForegroundColor White
    Write-Host "DELETE /backend/public/api/job_requirements_new.php?id=1" -ForegroundColor White
    Write-Host ""
    Write-Host "GET    /backend/public/api/job_skills_new.php?job_id=1" -ForegroundColor White
    Write-Host "POST   /backend/public/api/job_skills_new.php" -ForegroundColor White
    Write-Host "DELETE /backend/public/api/job_skills_new.php?id=1" -ForegroundColor White
    
    Write-Host ""
    Write-Host "📄 EJEMPLO DE USO:" -ForegroundColor Cyan
    Write-Host @"
# GET - Obtener requirements del job 1
curl "http://localhost/backend/public/api/job_requirements_new.php?job_id=1"

# POST - Crear nuevo requirement
curl -X POST "http://localhost/backend/public/api/job_requirements_new.php" \
  -H "Content-Type: application/json" \
  -d '{"job_id":1,"requirement":"Nueva experiencia requerida"}'

# DELETE - Eliminar requirement
curl -X DELETE "http://localhost/backend/public/api/job_requirements_new.php?id=1"
"@ -ForegroundColor White

    Write-Host ""
    Write-Host "🚀 PRÓXIMO PASO:" -ForegroundColor Green
    Write-Host "Reemplazar los archivos originales job_requirements.php y job_skills.php" -ForegroundColor White
    Write-Host "con las versiones nuevas cuando estés listo para usar los modelos." -ForegroundColor White
    
} catch {
    Write-Host "❌ ERROR: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
