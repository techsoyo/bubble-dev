# Script para mover todos los archivos test* a la carpeta tests de la raíz
$projectRoot = "c:\laragon\www\bubble_of_talents_1.0"
$testsRoot = "$projectRoot\tests"

Write-Host "Buscando todos los archivos test* en el proyecto..." -ForegroundColor Cyan

# Buscar todos los archivos que empiecen con "test" en todo el proyecto
$allTestFiles = Get-ChildItem -Path $projectRoot -Recurse -File -Filter "test*" | 
    Where-Object { 
        # Excluir archivos que ya están en la carpeta tests de la raíz
        $_.FullName -notlike "*\tests\*" -and 
        # Excluir archivos de node_modules, vendor, etc.
        $_.FullName -notlike "*\node_modules\*" -and
        $_.FullName -notlike "*\vendor\*" -and
        $_.FullName -notlike "*\.git\*"
    }

Write-Host "Encontrados $($allTestFiles.Count) archivos test* para mover" -ForegroundColor Yellow

# Crear estructura de directorios basada en los archivos encontrados
$dirsToCreate = @()
foreach ($file in $allTestFiles) {
    $relativePath = $file.FullName.Replace("$projectRoot\", "")
    $relativeDir = Split-Path $relativePath -Parent
    if ($relativeDir -and $relativeDir -ne "") {
        $targetDir = "$testsRoot\$relativeDir"
        if ($dirsToCreate -notcontains $targetDir) {
            $dirsToCreate += $targetDir
        }
    }
}

# Crear directorios necesarios
foreach ($dir in $dirsToCreate) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "Creado directorio: $dir" -ForegroundColor Blue
    }
}

# Mover todos los archivos encontrados
$movedCount = 0
$skippedCount = 0

foreach ($file in $allTestFiles) {
    $relativePath = $file.FullName.Replace("$projectRoot\", "")
    $targetPath = "$testsRoot\$relativePath"
    
    # Si el archivo ya existe en el destino, agregar sufijo
    if (Test-Path $targetPath) {
        $baseName = [System.IO.Path]::GetFileNameWithoutExtension($file.Name)
        $extension = [System.IO.Path]::GetExtension($file.Name)
        $parentDir = Split-Path $targetPath -Parent
        $counter = 1
        
        do {
            $newName = "$baseName`_$counter$extension"
            $targetPath = "$parentDir\$newName"
            $counter++
        } while (Test-Path $targetPath)
        
        Write-Warning "Archivo duplicado, renombrado como: $newName"
    }
    
    try {
        # Crear directorio padre si no existe
        $parentDir = Split-Path $targetPath -Parent
        if (-not (Test-Path $parentDir)) {
            New-Item -ItemType Directory -Path $parentDir -Force | Out-Null
        }
        
        Move-Item -Path $file.FullName -Destination $targetPath -Force
        Write-Host "Movido: $relativePath -> tests\$relativePath" -ForegroundColor Green
        $movedCount++
    }
    catch {
        Write-Error "Error moviendo $($file.FullName): $($_.Exception.Message)"
        $skippedCount++
    }
}

# Limpiar directorios vacíos después del movimiento
Write-Host "`nLimpiando directorios vacíos..." -ForegroundColor Yellow
$emptyDirs = Get-ChildItem -Path $projectRoot -Recurse -Directory | 
    Where-Object { 
        $_.FullName -notlike "*\tests\*" -and
        $_.FullName -notlike "*\node_modules\*" -and
        $_.FullName -notlike "*\vendor\*" -and
        $_.FullName -notlike "*\.git\*" -and
        (Get-ChildItem $_.FullName -Force | Measure-Object).Count -eq 0
    }

foreach ($emptyDir in $emptyDirs) {
    try {
        Remove-Item $emptyDir.FullName -Force
        $relativePath = $emptyDir.FullName.Replace("$projectRoot\", "")
        Write-Host "Eliminado directorio vacío: $relativePath" -ForegroundColor Yellow
    }
    catch {
        Write-Warning "No se pudo eliminar directorio: $($emptyDir.FullName)"
    }
}

Write-Host "`n=== RESUMEN ===" -ForegroundColor Cyan
Write-Host "Archivos movidos: $movedCount" -ForegroundColor Green
Write-Host "Archivos omitidos: $skippedCount" -ForegroundColor Yellow

Write-Host "`nArchivos test* organizados en la carpeta tests/" -ForegroundColor Green
