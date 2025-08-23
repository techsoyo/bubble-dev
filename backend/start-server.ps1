# Script PowerShell para arrancar el servidor de desarrollo
# Uso: .\start-dev.ps1 [puerto]

param(
    [int]$Port = 8000
)

$Host = "localhost"

Write-Host "🚀 Iniciando servidor PHP de desarrollo..." -ForegroundColor Green
Write-Host "📍 Host: $Host" -ForegroundColor Cyan
Write-Host "🔌 Puerto: $Port" -ForegroundColor Cyan  
Write-Host "🎯 Router: router.php" -ForegroundColor Cyan
Write-Host "🌐 URL: http://$Host`:$Port" -ForegroundColor Yellow
Write-Host ""
Write-Host "✅ Para probar la API:" -ForegroundColor Green
Write-Host "   curl http://$Host`:$Port/api/health" -ForegroundColor Gray
Write-Host ""
Write-Host "🛑 Para detener: Ctrl+C" -ForegroundColor Red
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray

# Verificar que existe router.php
if (-not (Test-Path "router.php")) {
    Write-Host "❌ Error: router.php no encontrado" -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
    exit 1
}

# Verificar que existe vendor/autoload.php
if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "❌ Error: vendor\autoload.php no encontrado" -ForegroundColor Red
    Write-Host "💡 Ejecuta: composer install" -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir"
    exit 1
}

# Arrancar servidor
Write-Host "🔄 Arrancando servidor PHP embebido..." -ForegroundColor Blue
try {
    # -t backend/public especifica la carpeta raíz para archivos estáticos
    # router.php maneja las rutas REST desde el directorio backend
    php -S "$Host`:$Port" -t "public" router.php
} catch {
    Write-Host "❌ Error al arrancar el servidor: $($_.Exception.Message)" -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
}
