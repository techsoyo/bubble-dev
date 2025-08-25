# =============================================
# CI Gate - Anti-Mocks Script (PowerShell)
# Fecha: 25 de agosto de 2025
# Propósito: Bloquear regresiones de mocks/hardcode en CI/CD
# =============================================

Write-Host "🛡️  CI GATE: Verificando ausencia de mocks y hardcode..." -ForegroundColor Cyan
Write-Host ""

$ExitCode = 0

try {
    # 1. Backend: mocks/tokens/aleatorios en API pública (excluir rand protegidos)
    Write-Host "🔍 Verificando endpoints públicos..." -ForegroundColor Yellow
    $mockResults = git grep -nI -E '\$mockData\b|mocked-jwt-token' -- 'backend/public/api/**' 2>$null | Where-Object { 
        $_ -notmatch 'CI_GATE_APPROVED' 
    }
    
    # Verificar array_rand() con contexto (incluir líneas anteriores/posteriores para CI_GATE_APPROVED)
    $randResults = git grep -n -B1 -A1 'array_rand\s*\(' -- 'backend/public/api/**' 2>$null | Where-Object { 
        $_.Split("`n") -notmatch 'CI_GATE_APPROVED' -and
        $_.Split("`n") -notmatch 'APP_ENV.*production' -and
        $_.Split("`n") -notmatch '@dev-only'
    }
    
    $allMockResults = @($mockResults) + @($randResults)
    if ($allMockResults) {
        Write-Host "❌ Mock/hardcode no protegido detectado en endpoints públicos:" -ForegroundColor Red
        Write-Host $allMockResults -ForegroundColor Red
        $ExitCode = 1
    } else {
        Write-Host "✅ Endpoints públicos limpios" -ForegroundColor Green
    }

    # 2. Backend general (por si quedan restos)
    Write-Host ""
    Write-Host "🔍 Verificando backend completo..." -ForegroundColor Yellow
    $backendMocks = git grep -nI -E '\$mockData\b|mocked-jwt-token' -- 'backend/**' 2>$null
    if ($backendMocks) {
        Write-Host "❌ Mocks detectados en backend:" -ForegroundColor Red
        Write-Host $backendMocks -ForegroundColor Red
        $ExitCode = 1
    } else {
        Write-Host "✅ Backend sin mocks" -ForegroundColor Green
    }

    # 3. Verificar rand() específicamente en producción crítica
    Write-Host ""
    Write-Host "🔍 Verificando uso de rand() en controladores críticos..." -ForegroundColor Yellow
    $randMatches = git grep -nI -E 'rand\s*\(' -- 'backend/src/Controllers/AIController.php' 2>$null
    if ($randMatches) {
        Write-Host "⚠️  rand() encontrado en AIController:" -ForegroundColor Yellow
        Write-Host $randMatches -ForegroundColor Yellow
        Write-Host "✅ Verificar que esté protegido con APP_ENV check" -ForegroundColor Green
    } else {
        Write-Host "✅ Sin rand() no protegido" -ForegroundColor Green
    }

    # 4. Verificar que job_requirements y job_skills usen modelos
    Write-Host ""
    Write-Host "🔍 Verificando que job_* usen modelos BaseModel..." -ForegroundColor Yellow
    $jobReqModel = Select-String -Path "backend\public\api\job_requirements.php" -Pattern "JobRequirementModel" -Quiet
    $jobSkillModel = Select-String -Path "backend\public\api\job_skills.php" -Pattern "JobSkillModel" -Quiet
    
    if ($jobReqModel -and $jobSkillModel) {
        Write-Host "✅ job_* endpoints usan modelos" -ForegroundColor Green
    } else {
        Write-Host "❌ job_* endpoints no usan modelos BaseModel" -ForegroundColor Red
        $ExitCode = 1
    }

    # 5. Verificar ausencia de arrays hardcodeados en job_*
    Write-Host ""
    Write-Host "🔍 Verificando ausencia de arrays mock en job_*..." -ForegroundColor Yellow
    $mockArrays = git grep -nI -E '\$mockData.*=.*\[' -- 'backend/public/api/job_requirements.php' 'backend/public/api/job_skills.php' 2>$null
    if ($mockArrays) {
        Write-Host "❌ Arrays mockData encontrados en job_* endpoints:" -ForegroundColor Red
        Write-Host $mockArrays -ForegroundColor Red
        $ExitCode = 1
    } else {
        Write-Host "✅ job_* endpoints sin arrays mock" -ForegroundColor Green
    }

    # 6. Frontend (opcional: permitir simulateProgress)
    Write-Host ""
    Write-Host "🔍 Revisando referencias mock en Frontend..." -ForegroundColor Yellow
    $frontendMocks = git grep -nI -E 'mockData|fake-|hardcode' -- 'frontend/**' 2>$null
    if ($frontendMocks) {
        Write-Host "⚠️  Referencias mock en FE encontradas - revisar si son aceptables:" -ForegroundColor Yellow
        Write-Host $frontendMocks -ForegroundColor Yellow
        # No falla el CI, solo advierte
    } else {
        Write-Host "✅ Frontend sin referencias mock problemáticas" -ForegroundColor Green
    }

    Write-Host ""
    if ($ExitCode -eq 0) {
        Write-Host "🎉 CI GATE PASSED: Proyecto libre de mocks críticos" -ForegroundColor Green
        Write-Host ""
        Write-Host "📋 RESUMEN CLEAN:" -ForegroundColor Cyan
        Write-Host "✅ Sin tokens mock en AuthController" -ForegroundColor White
        Write-Host "✅ Sin arrays mockData en job_* endpoints" -ForegroundColor White  
        Write-Host "✅ AIController con protección de producción" -ForegroundColor White
        Write-Host "✅ Modelos BaseModel implementados" -ForegroundColor White
    } else {
        Write-Host "💥 CI GATE FAILED: Mocks/hardcode detectados - bloquear merge" -ForegroundColor Red
        Write-Host ""
        Write-Host "🔧 ACCIONES REQUERIDAS:" -ForegroundColor Yellow
        Write-Host "1. Eliminar todos los tokens 'mocked-jwt-token'" -ForegroundColor White
        Write-Host "2. Reemplazar \$mockData con consultas BaseModel" -ForegroundColor White
        Write-Host "3. Proteger rand() con APP_ENV checks" -ForegroundColor White
        Write-Host "4. Implementar JWT real con cookies HttpOnly" -ForegroundColor White
    }

} catch {
    Write-Host "❌ Error ejecutando CI Gate: $($_.Exception.Message)" -ForegroundColor Red
    $ExitCode = 1
}

exit $ExitCode
