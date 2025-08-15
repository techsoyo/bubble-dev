# Script de limpieza CORS - Unificar todas las referencias a cors.php
# Este script reemplaza todos los patrones de CORS inconsistentes

$backendPath = "C:\laragon\www\bubble_of_talents_1.0\backend"

# Patrones a buscar y reemplazar
$patterns = @{
  # Patrón 1: bootstrap.php con preflightHandle y sendCorsHeaders
  "require_once __DIR__ . '/../api/bootstrap.php';\s*preflightHandle\(\);\s*sendCorsHeaders\(\);" = "require_once __DIR__ . '/../cors.php';"
  "require_once __DIR__ . '/bootstrap.php';\s*preflightHandle\(\);\s*sendCorsHeaders\(\);"        = "require_once __DIR__ . '/../cors.php';"
  "require_once __DIR__ . '/../bootstrap.php';\s*preflightHandle\(\);\s*sendCorsHeaders\(\);"     = "require_once __DIR__ . '/../../cors.php';"
    
  # Patrón 2: solo preflightHandle y sendCorsHeaders (mantener otros requires)
  "preflightHandle\(\);\s*sendCorsHeaders\(\);"                                                   = "// CORS ya manejado por cors.php"
}

Write-Host "🧹 Iniciando limpieza de archivos CORS..." -ForegroundColor Green

# Encontrar todos los archivos PHP
$phpFiles = Get-ChildItem -Path $backendPath -Recurse -Filter "*.php" | Where-Object { 
  $_.FullName -notmatch "cors\.php$" -and 
  $_.FullName -notmatch "vendor" -and
  $_.FullName -notmatch "config[\\/]cors" 
}

$filesModified = 0

foreach ($file in $phpFiles) {
  $content = Get-Content $file.FullName -Raw
  $originalContent = $content
  $modified = $false
    
  # Aplicar cada patrón
  foreach ($pattern in $patterns.Keys) {
    if ($content -match $pattern) {
      $content = $content -replace $pattern, $patterns[$pattern]
      $modified = $true
      Write-Host "  📝 Modificado: $($file.Name)" -ForegroundColor Yellow
    }
  }
    
  # Guardar si hubo cambios
  if ($modified) {
    Set-Content -Path $file.FullName -Value $content -NoNewline
    $filesModified++
        
    # Verificar que la sintaxis sigue siendo válida
    $checkResult = php -l $file.FullName 2>&1
    if ($checkResult -notmatch "No syntax errors") {
      Write-Host "  ⚠️ Error de sintaxis en $($file.Name): $checkResult" -ForegroundColor Red
      # Restaurar archivo original si hay error
      Set-Content -Path $file.FullName -Value $originalContent -NoNewline
      $filesModified--
    }
  }
}

Write-Host "✅ Limpieza completada: $filesModified archivos modificados" -ForegroundColor Green

# Verificar que cors.php está siendo usado correctamente
Write-Host "`n🔍 Verificando referencias a cors.php..." -ForegroundColor Cyan

$corsReferences = Select-String -Path "$backendPath\**\*.php" -Pattern "cors\.php" -Recurse
Write-Host "📊 Total de referencias a cors.php: $($corsReferences.Count)" -ForegroundColor Blue

# Buscar archivos que todavía usan bootstrap para CORS
$remainingBootstrap = Select-String -Path "$backendPath\**\*.php" -Pattern "preflightHandle|sendCorsHeaders" -Recurse
if ($remainingBootstrap.Count -gt 0) {
  Write-Host "⚠️ Archivos que aún usan bootstrap para CORS:" -ForegroundColor Yellow
  foreach ($ref in $remainingBootstrap) {
    Write-Host "  - $($ref.Filename):$($ref.LineNumber)" -ForegroundColor Yellow
  }
}
else {
  Write-Host "✅ Todos los archivos ahora usan cors.php unificado" -ForegroundColor Green
}
