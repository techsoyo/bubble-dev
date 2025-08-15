# Script de limpieza CORS simplificado
$backendPath = "C:\laragon\www\bubble_of_talents_1.0\backend"

Write-Host "Iniciando limpieza CORS..." -ForegroundColor Green

# Buscar archivos que usan bootstrap para CORS
$corsFiles = Select-String -Path "$backendPath\**\*.php" -Pattern "preflightHandle|sendCorsHeaders" -Recurse

Write-Host "Archivos encontrados con CORS inconsistente: $($corsFiles.Count)" -ForegroundColor Yellow

foreach ($match in $corsFiles) {
  Write-Host "- $($match.Filename):$($match.LineNumber) - $($match.Line.Trim())" -ForegroundColor Cyan
}

Write-Host "Limpieza manual requerida para estos archivos" -ForegroundColor Red
