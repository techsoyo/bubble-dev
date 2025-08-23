# Script PowerShell para ejecutar la suite de testing automatizado
# Basado en los 324 casos de prueba identificados en la auditoría

param(
    [string]$Suite = "all",
    [switch]$Verbose = $false,
    [switch]$Coverage = $false
)

# Configuración
$ScriptDir = $PSScriptRoot
$TestDir = $ScriptDir
$BackendDir = "$ScriptDir\..\backend"
$ReportsDir = "$TestDir\reports"
$LogFile = "$ReportsDir\test_execution.log"
$Timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"

# Crear directorio de reportes
if (!(Test-Path $ReportsDir)) {
    New-Item -ItemType Directory -Path $ReportsDir -Force | Out-Null
}

# Función para logging
function Write-Log {
    param([string]$Message)
    $TimestampLog = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $LogEntry = "[$TimestampLog] $Message"
    Write-Host $LogEntry
    Add-Content -Path $LogFile -Value $LogEntry
}

# Función para mostrar banner
function Show-Banner {
    Write-Host @"
╔══════════════════════════════════════════════════════════════════╗
║                    BUBBLE TALENTS - TEST SUITE                  ║
║                     Automated Testing Framework                 ║
╠══════════════════════════════════════════════════════════════════╣
║ Suite: $($Suite.ToUpper().PadRight(55)) ║
║ Timestamp: $($Timestamp.PadRight(50)) ║
╚══════════════════════════════════════════════════════════════════╝
"@ -ForegroundColor Cyan
}

# Función para verificar prerrequisitos
function Test-Prerequisites {
    Write-Log "Verificando prerrequisitos..."
    
    # Verificar PHP
    try {
        $phpVersion = php --version 2>$null
        if ($LASTEXITCODE -ne 0) {
            throw "PHP no encontrado"
        }
        Write-Log "✓ PHP disponible: $($phpVersion.Split("`n")[0])"
    } catch {
        Write-Host "✗ Error: PHP no está disponible en PATH" -ForegroundColor Red
        return $false
    }
    
    # Verificar PHPUnit
    if (!(Test-Path "$BackendDir\vendor\bin\phpunit.bat") -and !(Get-Command phpunit -ErrorAction SilentlyContinue)) {
        Write-Host "✗ Error: PHPUnit no encontrado en backend" -ForegroundColor Red
        Write-Host "Instala con: cd backend && composer install" -ForegroundColor Yellow
        return $false
    }
    Write-Log "✓ PHPUnit disponible en backend"
    
    # Verificar configuración de test
    if (!(Test-Path "$TestDir\tests\test_config.php")) {
        Write-Host "✗ Error: test_config.php no encontrado" -ForegroundColor Red
        return $false
    }
    Write-Log "✓ Configuración de test disponible"
    
    # Verificar servidor backend
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000/api/health.php" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
        Write-Log "✓ Servidor backend disponible en puerto 8000"
    } catch {
        Write-Host "⚠ Advertencia: Servidor backend no responde en puerto 8000" -ForegroundColor Yellow
        Write-Host "Inicia el servidor con: pnpm run dev:be" -ForegroundColor Yellow
    }
    
    return $true
}

# Función para ejecutar suite específica
function Invoke-TestSuite {
    param([string]$SuiteName)
    
    Write-Log "Ejecutando suite: $SuiteName"
    
    $phpunitCmd = if (Test-Path "$BackendDir\vendor\bin\phpunit.bat") { 
        "$BackendDir\vendor\bin\phpunit.bat" 
    } else { 
        "phpunit" 
    }
    
    $configFile = "$TestDir\phpunit.xml"
    $reportFile = "$ReportsDir\$SuiteName-$Timestamp.xml"
    
    $arguments = @(
        "--configuration", $configFile
        "--log-junit", $reportFile
        "--colors=always"
    )
    
    if ($Verbose) {
        $arguments += "--verbose"
    }
    
    if ($Coverage) {
        $coverageFile = "$ReportsDir\coverage-$SuiteName-$Timestamp.xml"
        $arguments += @("--coverage-clover", $coverageFile)
    }
    
    # Añadir testsuite específico si no es 'all'
    if ($SuiteName -ne "all") {
        $arguments += @("--testsuite", $SuiteName)
    }
    
    Write-Log "Comando: $phpunitCmd $($arguments -join ' ')"
    
    $startTime = Get-Date
    & $phpunitCmd @arguments
    $exitCode = $LASTEXITCODE
    $endTime = Get-Date
    $duration = $endTime - $startTime
    
    Write-Log "Suite '$SuiteName' completada en $($duration.TotalSeconds) segundos (Exit Code: $exitCode)"
    
    return $exitCode
}

# Main execution
try {
    Show-Banner
    Write-Log "Iniciando ejecución de tests..."
    
    if (!(Test-Prerequisites)) {
        Write-Host "✗ Verificación de prerrequisitos falló" -ForegroundColor Red
        exit 1
    }
    
    $overallStartTime = Get-Date
    $failedSuites = @()
    
    switch ($Suite.ToLower()) {
        "smoke" {
            $exitCode = Invoke-TestSuite "Smoke Tests"
            if ($exitCode -ne 0) { $failedSuites += "Smoke Tests" }
        }
        "security" {
            $exitCode = Invoke-TestSuite "Security Tests"
            if ($exitCode -ne 0) { $failedSuites += "Security Tests" }
        }
        "unit" {
            $exitCode = Invoke-TestSuite "Unit Tests"
            if ($exitCode -ne 0) { $failedSuites += "Unit Tests" }
        }
        "integration" {
            $exitCode = Invoke-TestSuite "Integration Tests"
            if ($exitCode -ne 0) { $failedSuites += "Integration Tests" }
        }
        "all" {
            $suites = @("Smoke Tests", "Security Tests", "Unit Tests", "Integration Tests")
            foreach ($suiteName in $suites) {
                $exitCode = Invoke-TestSuite $suiteName
                if ($exitCode -ne 0) { $failedSuites += $suiteName }
            }
        }
        default {
            Write-Host "Suite desconocida: $Suite" -ForegroundColor Red
            Write-Host "Opciones válidas: smoke, security, unit, integration, all" -ForegroundColor Yellow
            exit 1
        }
    }
    
    $overallEndTime = Get-Date
    $totalDuration = $overallEndTime - $overallStartTime
    
    # Resumen final
    Write-Host "`n" + "="*70 -ForegroundColor Cyan
    Write-Host "RESUMEN DE EJECUCIÓN" -ForegroundColor Cyan
    Write-Host "="*70 -ForegroundColor Cyan
    Write-Host "Duración total: $($totalDuration.TotalMinutes.ToString('F2')) minutos"
    Write-Host "Reportes generados en: $ReportsDir"
    
    if ($failedSuites.Count -eq 0) {
        Write-Host "✓ Todas las suites pasaron exitosamente" -ForegroundColor Green
        exit 0
    } else {
        Write-Host "✗ Suites con fallos: $($failedSuites -join ', ')" -ForegroundColor Red
        exit 1
    }
    
} catch {
    Write-Log "Error durante la ejecución: $($_.Exception.Message)"
    Write-Host "✗ Error crítico: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
