# Script de inicio para desarrollo - Bubble of Talents (PowerShell)

Write-Host "🚀 Iniciando Bubble of Talents en modo desarrollo..." -ForegroundColor Green

# Verificar que estamos en el directorio correcto
if (!(Test-Path "package.json")) {
    Write-Host "❌ Error: Este script debe ejecutarse desde la raíz del proyecto" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "📋 Verificando estado del proyecto..." -ForegroundColor Yellow

# Verificar backend
if (Test-Path "backend/vendor") {
    Write-Host "✅ Backend: Dependencias instaladas" -ForegroundColor Green
} else {
    Write-Host "❌ Backend: Ejecuta 'composer install' en /backend" -ForegroundColor Red
    exit 1
}

# Verificar frontend
if (Test-Path "frontend/node_modules") {
    Write-Host "✅ Frontend: Dependencias instaladas" -ForegroundColor Green
} else {
    Write-Host "❌ Frontend: Ejecuta 'pnpm install' en /frontend" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🌐 Iniciando servidores..." -ForegroundColor Cyan
Write-Host ""

# Iniciar backend en segundo plano
Write-Host "🔧 Iniciando backend en http://localhost:8000..." -ForegroundColor Blue
$backendJob = Start-Job -ScriptBlock {
    Set-Location $using:PWD\backend
    php -S localhost:8000 router.php
}

# Esperar un momento para que el backend inicie
Start-Sleep -Seconds 3

# Verificar que el backend esté funcionando
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/api/jobs.php" -TimeoutSec 5 -ErrorAction Stop
    Write-Host "✅ Backend iniciado correctamente" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Backend iniciado pero puede tener problemas" -ForegroundColor Yellow
}

# Iniciar frontend
Write-Host ""
Write-Host "⚛️  Iniciando frontend en http://localhost:3002..." -ForegroundColor Blue
$frontendJob = Start-Job -ScriptBlock {
    Set-Location $using:PWD\frontend
    pnpm dev
}

Write-Host ""
Write-Host "🎉 ¡Servidores iniciados!" -ForegroundColor Green
Write-Host ""
Write-Host "📱 URLs disponibles:" -ForegroundColor Cyan
Write-Host "   Frontend: http://localhost:3002" -ForegroundColor White
Write-Host "   Backend:  http://localhost:8000" -ForegroundColor White
Write-Host "   API Test: http://localhost:8000/api/jobs.php" -ForegroundColor White
Write-Host ""
Write-Host "⏹️  Para detener los servidores, presiona Ctrl+C" -ForegroundColor Yellow
Write-Host ""

# Función para limpiar procesos al salir
try {
    # Mantener el script corriendo hasta que se presione Ctrl+C
    while ($true) {
        Start-Sleep -Seconds 1
        
        # Verificar si los jobs siguen corriendo
        if ($backendJob.State -ne "Running") {
            Write-Host "⚠️  Backend se detuvo inesperadamente" -ForegroundColor Yellow
        }
        if ($frontendJob.State -ne "Running") {
            Write-Host "⚠️  Frontend se detuvo inesperadamente" -ForegroundColor Yellow
        }
    }
} finally {
    Write-Host ""
    Write-Host "🛑 Deteniendo servidores..." -ForegroundColor Yellow
    
    Stop-Job $backendJob -ErrorAction SilentlyContinue
    Remove-Job $backendJob -ErrorAction SilentlyContinue
    
    Stop-Job $frontendJob -ErrorAction SilentlyContinue
    Remove-Job $frontendJob -ErrorAction SilentlyContinue
    
    Write-Host "✅ Servidores detenidos" -ForegroundColor Green
}
