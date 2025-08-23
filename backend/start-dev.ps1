# Script PowerShell para iniciar el servidor PHP de desarrollo con configuración CORS

Write-Host "====================================" -ForegroundColor Cyan
Write-Host "  BUBBLE TALENTS - Backend Server" -ForegroundColor Cyan  
Write-Host "====================================" -ForegroundColor Cyan
Write-Host

# Verificar que existe el archivo .env
if (-not (Test-Path ".env")) {
  Write-Host "[WARNING] Archivo .env no encontrado" -ForegroundColor Yellow
  if (Test-Path ".env.development") {
    Write-Host "Copiando .env.development como .env..." -ForegroundColor Yellow
    Copy-Item ".env.development" ".env"
    Write-Host "✅ Archivo .env creado desde .env.development" -ForegroundColor Green
  }
  else {
    Write-Host "❌ No se encontró .env ni .env.development" -ForegroundColor Red
    Write-Host "Por favor crea un archivo .env basado en .env.example" -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
    exit 1
  }
}
else {
  Write-Host "✅ Archivo .env encontrado" -ForegroundColor Green
}
Write-Host

# Mostrar configuración CORS actual
Write-Host "[INFO] Configuración CORS actual:" -ForegroundColor Blue
if (Test-Path ".env") {
  $corsOrigins = Get-Content .env | Where-Object { $_ -match "^CORS_ALLOWED_ORIGINS" }
  $corsCredentials = Get-Content .env | Where-Object { $_ -match "^CORS_ALLOW_CREDENTIALS" }
  $appEnv = Get-Content .env | Where-Object { $_ -match "^APP_ENV" }
    
  if ($corsOrigins) { Write-Host "  $corsOrigins" } else { Write-Host "  CORS_ALLOWED_ORIGINS: [no configurado]" }
  if ($corsCredentials) { Write-Host "  $corsCredentials" } else { Write-Host "  CORS_ALLOW_CREDENTIALS: [no configurado]" }
  if ($appEnv) { Write-Host "  $appEnv" } else { Write-Host "  APP_ENV: [no configurado]" }
}
Write-Host

# Verificar puerto disponible
$PORT = 8000
Write-Host "[INFO] Iniciando servidor PHP en puerto $PORT..." -ForegroundColor Blue
Write-Host "[INFO] URL Backend: http://localhost:$PORT" -ForegroundColor Green
Write-Host "[INFO] Endpoints disponibles:" -ForegroundColor Blue
Write-Host "  - GET  /api/health"
Write-Host "  - POST /api/candidates"
Write-Host "  - GET  /api/jobs"
Write-Host

# Iniciar servidor
Write-Host "[INFO] Presiona Ctrl+C para detener el servidor" -ForegroundColor Yellow
Write-Host "[INFO] Logs CORS visibles en development mode" -ForegroundColor Yellow
Write-Host

php -S localhost:$PORT -t . router.php
