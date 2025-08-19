# 🛠️ Script de Instalación OAuth - Windows PowerShell

# Este script instala automáticamente el sistema OAuth en tu proyecto
# Ejecutar desde la raíz del proyecto: .\oauth_implementation\install_oauth.ps1

param(
  [string]$SourcePath = ".\oauth_implementation",
  [string]$BackendPath = ".\backend",
  [string]$FrontendPath = ".\frontend\src\pages\auth",
  [switch]$Force = $false
)

Write-Host "🚀 INSTALADOR OAUTH - BUBBLE OF TALENTS" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan

# Verificar que estamos en el directorio correcto
if (-not (Test-Path "package.json")) {
  Write-Error "❌ Error: Ejecuta este script desde la raíz del proyecto (donde está package.json)"
  exit 1
}

# Verificar que existe la carpeta oauth_implementation
if (-not (Test-Path $SourcePath)) {
  Write-Error "❌ Error: No se encuentra la carpeta oauth_implementation"
  exit 1
}

Write-Host "📁 Verificando estructura de archivos..." -ForegroundColor Yellow

# Crear directorios si no existen
$dirs = @(
  "$BackendPath\auth",
  "$BackendPath\auth\oauth", 
  "$BackendPath\auth\google",
  "$BackendPath\auth\linkedin"
)

foreach ($dir in $dirs) {
  if (-not (Test-Path $dir)) {
    Write-Host "   Creando directorio: $dir" -ForegroundColor Gray
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
  }
}

Write-Host "✅ Directorios verificados/creados" -ForegroundColor Green

# Función para copiar archivo con confirmación
function Copy-WithConfirmation {
  param($Source, $Destination, $Description)
    
  if (Test-Path $Destination -and -not $Force) {
    $response = Read-Host "⚠️  $Description ya existe. ¿Sobrescribir? (y/N)"
    if ($response -ne 'y' -and $response -ne 'Y') {
      Write-Host "   Saltando: $Description" -ForegroundColor Gray
      return
    }
  }
    
  try {
    Copy-Item $Source $Destination -Force
    Write-Host "✅ Copiado: $Description" -ForegroundColor Green
  }
  catch {
    Write-Error "❌ Error copiando $Description : $_"
  }
}

Write-Host "📋 Instalando archivos backend..." -ForegroundColor Yellow

# Copiar archivos backend
Copy-WithConfirmation "$SourcePath\backend\auth\OAuthHandler.php" "$BackendPath\auth\OAuthHandler.php" "OAuthHandler.php"
Copy-WithConfirmation "$SourcePath\backend\auth\oauth\start.php" "$BackendPath\auth\oauth\start.php" "OAuth start endpoint"
Copy-WithConfirmation "$SourcePath\backend\auth\google\callback.php" "$BackendPath\auth\google\callback.php" "Google callback"
Copy-WithConfirmation "$SourcePath\backend\auth\linkedin\callback.php" "$BackendPath\auth\linkedin\callback.php" "LinkedIn callback"

Write-Host "🎨 Instalando archivos frontend..." -ForegroundColor Yellow

# Copiar archivo frontend
Copy-WithConfirmation "$SourcePath\frontend\CandidateAuthPage.tsx" "$FrontendPath\CandidateAuthPage.tsx" "CandidateAuthPage.tsx con OAuth"

Write-Host "⚙️  Instalando configuración..." -ForegroundColor Yellow

# Copiar configuración si no existe
if (-not (Test-Path "$BackendPath\.env.oauth")) {
  Copy-WithConfirmation "$SourcePath\config\.env.oauth.example" "$BackendPath\.env.oauth.example" "Template configuración OAuth"
  Write-Host "📝 Copia .env.oauth.example a .env.oauth y completa las credenciales" -ForegroundColor Cyan
}
else {
  Write-Host "✅ Archivo .env.oauth ya existe" -ForegroundColor Green
}

# Copiar herramienta de debug
Copy-WithConfirmation "$SourcePath\..\debug_oauth.php" "$BackendPath\debug_oauth.php" "Herramienta de debug"

Write-Host ""
Write-Host "🎉 INSTALACIÓN COMPLETADA!" -ForegroundColor Green
Write-Host "=========================" -ForegroundColor Green
Write-Host ""
Write-Host "📋 PRÓXIMOS PASOS:" -ForegroundColor Cyan
Write-Host "1. 📖 Lee la guía: .\oauth_implementation\docs\OAUTH_SETUP_GUIDE.md" -ForegroundColor White
Write-Host "2. ⚙️  Configura Google Cloud Console y LinkedIn Developers" -ForegroundColor White
Write-Host "3. 📝 Completa el archivo: .\backend\.env.oauth" -ForegroundColor White
Write-Host "4. 🧪 Verifica la instalación: http://localhost:8000/debug_oauth.php" -ForegroundColor White
Write-Host "5. 🎮 Prueba los botones OAuth en: http://localhost:3002/auth/register" -ForegroundColor White
Write-Host ""
Write-Host "⏱️  Tiempo estimado de configuración: 30 minutos" -ForegroundColor Yellow
Write-Host ""
Write-Host "🆘 ¿Necesitas ayuda? Revisa: .\oauth_implementation\docs\OAUTH_TECHNICAL_CHECKLIST.md" -ForegroundColor Magenta

# Mostrar archivos instalados
Write-Host ""
Write-Host "📦 ARCHIVOS INSTALADOS:" -ForegroundColor Cyan
Get-ChildItem "$BackendPath\auth" -Recurse | Where-Object { $_.Name -like "*.php" } | ForEach-Object {
  Write-Host "   $($_.FullName.Replace((Get-Location).Path, '.'))" -ForegroundColor Gray
}
Write-Host "   $FrontendPath\CandidateAuthPage.tsx" -ForegroundColor Gray
