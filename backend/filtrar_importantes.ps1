# filtrar_importantes.ps1

$inputFile = "resultados_criticos.csv"
$outputFile = "CRITICO_a_revisar.csv"

# Si no existe el archivo de entrada, error
if (-not (Test-Path $inputFile)) {
    Write-Host "❌ No se encontró $inputFile. Ejecuta primero buscar_criticos.ps1" -ForegroundColor Red
    exit
}

# Cargar el CSV
$datos = Import-Csv -Path $inputFile

# Patrones de archivos que SÍ son importantes (ajusta según tu proyecto)
$incluir = @(
    "auth.*\.php",
    "candidates\.php",
    "users\.php",
    "jobs\.php",
    "applications\.php",
    "api_.*\.php",
    ".*controller.*\.php",
    ".*model.*\.php",
    ".*class.*\.php",
    "db\.php",
    "config\.php"
)

# Patrones de archivos que NO son importantes (los ignoramos)
$excluir = @(
    "test_.*\.php",
    "debug_.*\.php",
    ".*-cv.*\.php",
    ".*extract.*\.php",
    ".*analyze.*\.php",
    ".*resume.*\.php",
    ".*parse.*\.php",
    ".*service.*\.php",
    ".*api.*\.php",
    ".*integration.*\.php",
    ".*login.*\.php",
    "callback\.php",
    "social-login\.php"
)

# Filtrar
$resultados = @()
foreach ($fila in $datos) {
    $archivo = $fila.Archivo

    $excluirArchivo = $false
    foreach ($patron in $excluir) {
        if ($archivo -imatch $patron) {
            $excluirArchivo = $true
            break
        }
    }
    if ($excluirArchivo) { continue }

    $incluirArchivo = $false
    foreach ($patron in $incluir) {
        if ($archivo -imatch $patron) {
            $incluirArchivo = $true
            break
        }
    }
    if (-not $incluirArchivo) { continue }

    # Si pasa los filtros, añadir
    $resultados += $fila
}

# Guardar
if ($resultados.Count -gt 0) {
    $resultados | Export-Csv -Path $outputFile -Encoding UTF8 -NoTypeInformation
    Write-Host "✅ Filtrado completado. Archivos críticos: $($resultados.Count)" -ForegroundColor Green
    Write-Host "📄 Resultados en: $outputFile" -ForegroundColor Green
} else {
    Write-Host "❌ No se encontraron archivos críticos después del filtro." -ForegroundColor Yellow
    Write-Host "💡 Puede que necesites ajustar los patrones en `$incluir`." -ForegroundColor White
}