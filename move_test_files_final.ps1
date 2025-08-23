# Script final para mover todos los archivos test* a la carpeta tests de la raíz
param(
    [switch]$DryRun = $false
)

$projectRoot = "c:\laragon\www\bubble_of_talents_1.0"
$testsRoot = "$projectRoot\tests"

Write-Host "=== REORGANIZACIÓN DE ARCHIVOS TEST* ===" -ForegroundColor Cyan
if ($DryRun) {
    Write-Host "MODO DE PRUEBA - No se moverán archivos realmente" -ForegroundColor Yellow
}

Write-Host "Buscando archivos test* en todo el proyecto..." -ForegroundColor White

# Buscar todos los archivos que empiecen con "test" excluyendo los que ya están en tests/
try {
    $allTestFiles = Get-ChildItem -Path $projectRoot -Recurse -File | 
        Where-Object { 
            $_.Name -like "test*" -and
            -not $_.FullName.StartsWith($testsRoot) -and
            $_.FullName -notlike "*\node_modules\*" -and
            $_.FullName -notlike "*\vendor\*" -and
            $_.FullName -notlike "*\.git\*" -and
            $_.FullName -notlike "*\playwright-report\*" -and
            $_.FullName -notlike "*\test-results\*"
        }
}
catch {
    Write-Error "Error buscando archivos: $($_.Exception.Message)"
    exit 1
}

Write-Host "Encontrados $($allTestFiles.Count) archivos test* para mover`n" -ForegroundColor Yellow

if ($allTestFiles.Count -eq 0) {
    Write-Host "No se encontraron archivos test* para mover." -ForegroundColor Green
    exit 0
}

# Mostrar lista de archivos a mover
Write-Host "Archivos a mover:" -ForegroundColor White
foreach ($file in $allTestFiles) {
    $relativePath = $file.FullName.Replace($projectRoot, "").TrimStart('\')
    Write-Host "  - $relativePath" -ForegroundColor Gray
}

if (-not $DryRun) {
    Write-Host "`n¿Continuar con el movimiento? (S/N): " -ForegroundColor Yellow -NoNewline
    $confirm = Read-Host
    if ($confirm -notmatch '^[SsYy]') {
        Write-Host "Operación cancelada." -ForegroundColor Yellow
        exit 0
    }
}

$movedCount = 0
$skippedCount = 0
$errorCount = 0

foreach ($file in $allTestFiles) {
    # Calcular ruta relativa y destino
    $relativePath = $file.FullName.Replace($projectRoot, "").TrimStart('\')
    $targetPath = Join-Path $testsRoot $relativePath
    
    Write-Host "Procesando: $relativePath" -ForegroundColor Gray
    
    # Verificar si el archivo destino ya existe
    if (Test-Path $targetPath) {
        $baseName = [System.IO.Path]::GetFileNameWithoutExtension($file.Name)
        $extension = [System.IO.Path]::GetExtension($file.Name)
        $parentDir = Split-Path $targetPath -Parent
        $counter = 1
        
        do {
            $newName = "${baseName}_backup_$counter$extension"
            $targetPath = Join-Path $parentDir $newName
            $counter++
        } while (Test-Path $targetPath)
        
        Write-Warning "  Archivo duplicado, renombrado como: $newName"
    }
    
    if ($DryRun) {
        Write-Host "  [SIMULADO] Movería a: tests\$relativePath" -ForegroundColor Cyan
        $movedCount++
        continue
    }
    
    try {
        # Crear directorio padre si no existe
        $parentDir = Split-Path $targetPath -Parent
        if (-not (Test-Path $parentDir)) {
            New-Item -ItemType Directory -Path $parentDir -Force | Out-Null
            $relativeParent = $parentDir.Replace($testsRoot, "").TrimStart('\')
            if ($relativeParent) {
                Write-Host "  Creado directorio: tests\$relativeParent" -ForegroundColor Blue
            }
        }
        
        # Mover archivo
        Move-Item -Path $file.FullName -Destination $targetPath -Force
        Write-Host "  ✓ Movido exitosamente" -ForegroundColor Green
        $movedCount++
    }
    catch {
        Write-Error "  ✗ Error: $($_.Exception.Message)"
        $errorCount++
    }
}

if (-not $DryRun) {
    # Limpiar directorios vacíos
    Write-Host "`nLimpiando directorios vacíos..." -ForegroundColor Yellow
    $cleanedDirs = 0
    
    # Obtener directorios ordenados de más profundo a menos profundo
    $allDirs = @()
    try {
        $allDirs = Get-ChildItem -Path $projectRoot -Recurse -Directory | 
            Where-Object { 
                $_.FullName -notlike "*\tests\*" -and
                $_.FullName -notlike "*\node_modules\*" -and
                $_.FullName -notlike "*\vendor\*" -and
                $_.FullName -notlike "*\.git\*" -and
                $_.FullName -notlike "*\frontend\*" -and
                $_.Name -ne "tests"
            } | Sort-Object @{Expression={$_.FullName.Split('\').Count}; Descending=$true}
    }
    catch {
        Write-Warning "No se pudieron obtener directorios para limpieza"
    }
    
    foreach ($dir in $allDirs) {
        try {
            $items = Get-ChildItem $dir.FullName -Force -ErrorAction SilentlyContinue
            if ($items.Count -eq 0) {
                Remove-Item $dir.FullName -Force
                $relativeDirPath = $dir.FullName.Replace($projectRoot, "").TrimStart('\')
                Write-Host "  Eliminado directorio vacío: $relativeDirPath" -ForegroundColor Yellow
                $cleanedDirs++
            }
        }
        catch {
            # Ignorar errores de acceso
        }
    }
    
    Write-Host "Directorios limpiados: $cleanedDirs" -ForegroundColor Yellow
}

# Mostrar resumen final
Write-Host "`n=== RESUMEN FINAL ===" -ForegroundColor Cyan
Write-Host "Archivos procesados: $($allTestFiles.Count)" -ForegroundColor White
Write-Host "Movidos exitosamente: $movedCount" -ForegroundColor Green
if ($errorCount -gt 0) {
    Write-Host "Errores: $errorCount" -ForegroundColor Red
}

if ($DryRun) {
    Write-Host "`n💡 Para ejecutar realmente, ejecuta sin el parámetro -DryRun" -ForegroundColor Cyan
} elseif ($movedCount -gt 0) {
    Write-Host "`n✅ Reorganización completada exitosamente" -ForegroundColor Green
    Write-Host "Los archivos test* ahora están organizados en la carpeta tests/" -ForegroundColor Green
} else {
    Write-Host "`n⚠️ No se movieron archivos" -ForegroundColor Yellow
}
