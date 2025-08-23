# Script mejorado para mover todos los archivos test* a la carpeta tests de la raíz
$projectRoot = "c:\laragon\www\bubble_of_talents_1.0"
$testsRoot = "$projectRoot\tests"

Write-Host "Buscando archivos test* en todo el proyecto..." -ForegroundColor Cyan

# Buscar todos los archivos que empiecen con "test" excluyendo los que ya están en tests/
$allTestFiles = Get-ChildItem -Path $projectRoot -Recurse -File -Filter "test*" | 
    Where-Object { 
        -not $_.FullName.StartsWith("$testsRoot\") -and
        $_.FullName -notlike "*\node_modules\*" -and
        $_.FullName -notlike "*\vendor\*" -and
        $_.FullName -notlike "*\.git\*" -and
        $_.FullName -notlike "*\playwright-report\*"
    }

Write-Host "Encontrados $($allTestFiles.Count) archivos test* para mover" -ForegroundColor Yellow

$movedCount = 0
$skippedCount = 0

foreach ($file in $allTestFiles) {
    # Calcular ruta relativa desde el proyecto
    $relativePath = $file.FullName.Substring($projectRoot.Length + 1)
    $targetPath = Join-Path $testsRoot $relativePath
    
    Write-Host "Procesando: $relativePath" -ForegroundColor Gray
    
    # Si el archivo ya existe en destino, agregar sufijo
    if (Test-Path $targetPath) {
        $baseName = [System.IO.Path]::GetFileNameWithoutExtension($file.Name)
        $extension = [System.IO.Path]::GetExtension($file.Name)
        $parentDir = Split-Path $targetPath -Parent
        $counter = 1
        
        do {
            $newName = "$baseName`_$counter$extension"
            $targetPath = Join-Path $parentDir $newName
            $counter++
        } while (Test-Path $targetPath)
        
        Write-Warning "Archivo duplicado, renombrado como: $newName"
    }
    
    try {
        # Crear directorio padre si no existe
        $parentDir = Split-Path $targetPath -Parent
        if (-not (Test-Path $parentDir)) {
            New-Item -ItemType Directory -Path $parentDir -Force | Out-Null
            Write-Host "Creado directorio: $($parentDir.Substring($testsRoot.Length + 1))" -ForegroundColor Blue
        }
        
        # Mover archivo
        Move-Item -Path $file.FullName -Destination $targetPath -Force
        Write-Host "✓ Movido: $relativePath" -ForegroundColor Green
        $movedCount++
    }
    catch {
        Write-Error "✗ Error moviendo $relativePath`: $($_.Exception.Message)"
        $skippedCount++
    }
}

# Limpiar directorios vacíos
Write-Host "`nLimpiando directorios vacíos..." -ForegroundColor Yellow
$cleanedDirs = 0

# Obtener todos los directorios que podrían estar vacíos
$allDirs = Get-ChildItem -Path $projectRoot -Recurse -Directory | 
    Where-Object { 
        $_.FullName -notlike "*\tests\*" -and
        $_.FullName -notlike "*\node_modules\*" -and
        $_.FullName -notlike "*\vendor\*" -and
        $_.FullName -notlike "*\.git\*"
    } | Sort-Object FullName -Descending

foreach ($dir in $allDirs) {
    try {
        $items = Get-ChildItem $dir.FullName -Force
        if ($items.Count -eq 0) {
            Remove-Item $dir.FullName -Force
            $relativeDirPath = $dir.FullName.Substring($projectRoot.Length + 1)
            Write-Host "Eliminado directorio vacío: $relativeDirPath" -ForegroundColor Yellow
            $cleanedDirs++
        }
    }
    catch {
        # Ignorar errores de acceso
    }
}

Write-Host "`n=== RESUMEN ===" -ForegroundColor Cyan
Write-Host "Archivos movidos: $movedCount" -ForegroundColor Green
Write-Host "Archivos con error: $skippedCount" -ForegroundColor Red
Write-Host "Directorios limpiados: $cleanedDirs" -ForegroundColor Yellow

if ($movedCount -gt 0) {
    Write-Host "`n✅ Archivos test* reorganizados exitosamente en tests/" -ForegroundColor Green
} else {
    Write-Host "`n⚠️  No se pudieron mover archivos" -ForegroundColor Yellow
}
