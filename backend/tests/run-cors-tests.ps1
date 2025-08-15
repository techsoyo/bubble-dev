#!/usr/bin/env powershell

<#
.SYNOPSIS
    Script para ejecutar tests de CORS de manera automatizada
    
.DESCRIPTION
    Este script ejecuta los tests de funcionalidad y rendimiento
    del sistema CORS granular e inicia el servidor de desarrollo
    si es necesario.
    
.PARAMETER TestType
    Tipo de test a ejecutar: 'functional', 'performance', 'all'
    
.PARAMETER StartServer
    Si debe iniciar el servidor de desarrollo automáticamente
    
.EXAMPLE
    .\run-cors-tests.ps1 -TestType all -StartServer
#>

param(
    [ValidateSet('functional', 'performance', 'all')]
    [string]$TestType = 'all',
    
    [switch]$StartServer = $false,
    
    [int]$ServerPort = 8000
)

# Configuración
$BackendPath = "C:\laragon\www\bubble_of_talents_1.0\backend"
$TestsPath = "$BackendPath\tests"
$PhpExecutable = "php"

# Colores para output
$Green = [System.ConsoleColor]::Green
$Red = [System.ConsoleColor]::Red
$Yellow = [System.ConsoleColor]::Yellow
$Blue = [System.ConsoleColor]::Blue
$Cyan = [System.ConsoleColor]::Cyan

function Write-ColorOutput {
    param(
        [string]$Message,
        [System.ConsoleColor]$Color = [System.ConsoleColor]::White
    )
    
    $originalColor = $Host.UI.RawUI.ForegroundColor
    $Host.UI.RawUI.ForegroundColor = $Color
    Write-Output $Message
    $Host.UI.RawUI.ForegroundColor = $originalColor
}

function Test-ServerRunning {
    param([int]$Port)
    
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:$Port" -Method HEAD -TimeoutSec 2 -ErrorAction Stop
        return $true
    }
    catch {
        return $false
    }
}

function Start-DevServer {
    param([int]$Port)
    
    Write-ColorOutput "🚀 Iniciando servidor de desarrollo en puerto $Port..." $Blue
    
    Push-Location $BackendPath
    try {
        $serverJob = Start-Job -ScriptBlock {
            param($Path, $Port)
            Set-Location $Path
            php -S "localhost:$Port"
        } -ArgumentList $BackendPath, $Port
        
        # Esperar a que el servidor se inicie
        $timeout = 30
        $elapsed = 0
        while ($elapsed -lt $timeout) {
            if (Test-ServerRunning -Port $Port) {
                Write-ColorOutput "✅ Servidor iniciado correctamente" $Green
                return $serverJob
            }
            Start-Sleep -Seconds 1
            $elapsed++
        }
        
        Write-ColorOutput "❌ Error: El servidor no se inició en $timeout segundos" $Red
        Stop-Job $serverJob -Force
        Remove-Job $serverJob -Force
        return $null
    }
    finally {
        Pop-Location
    }
}

function Stop-DevServer {
    param($ServerJob)
    
    if ($ServerJob) {
        Write-ColorOutput "🛑 Deteniendo servidor de desarrollo..." $Yellow
        Stop-Job $ServerJob -Force
        Remove-Job $ServerJob -Force
        Write-ColorOutput "✅ Servidor detenido" $Green
    }
}

function Run-FunctionalTests {
    Write-ColorOutput "`n🧪 EJECUTANDO TESTS FUNCIONALES" $Cyan
    Write-ColorOutput "================================" $Cyan
    
    $testFile = "$TestsPath\CorsGranularTests.php"
    
    if (-not (Test-Path $testFile)) {
        Write-ColorOutput "❌ Error: No se encuentra el archivo de tests funcionales" $Red
        return $false
    }
    
    try {
        Push-Location $BackendPath
        $result = & $PhpExecutable $testFile
        
        if ($LASTEXITCODE -eq 0) {
            Write-ColorOutput $result $Green
            Write-ColorOutput "`n✅ Tests funcionales completados exitosamente" $Green
            return $true
        } else {
            Write-ColorOutput $result $Red
            Write-ColorOutput "`n❌ Algunos tests funcionales fallaron" $Red
            return $false
        }
    }
    catch {
        Write-ColorOutput "❌ Error ejecutando tests funcionales: $($_.Exception.Message)" $Red
        return $false
    }
    finally {
        Pop-Location
    }
}

function Run-PerformanceTests {
    Write-ColorOutput "`n⚡ EJECUTANDO TESTS DE RENDIMIENTO" $Cyan
    Write-ColorOutput "=================================" $Cyan
    
    $testFile = "$TestsPath\CorsPerformanceTests.php"
    
    if (-not (Test-Path $testFile)) {
        Write-ColorOutput "❌ Error: No se encuentra el archivo de tests de rendimiento" $Red
        return $false
    }
    
    try {
        Push-Location $BackendPath
        $result = & $PhpExecutable $testFile
        Write-ColorOutput $result $Yellow
        Write-ColorOutput "`n✅ Tests de rendimiento completados" $Green
        return $true
    }
    catch {
        Write-ColorOutput "❌ Error ejecutando tests de rendimiento: $($_.Exception.Message)" $Red
        return $false
    }
    finally {
        Pop-Location
    }
}

function Test-Prerequisites {
    Write-ColorOutput "🔍 Verificando prerequisitos..." $Blue
    
    # Verificar PHP
    try {
        $phpVersion = & $PhpExecutable --version 2>&1 | Select-Object -First 1
        Write-ColorOutput "✅ PHP encontrado: $phpVersion" $Green
    }
    catch {
        Write-ColorOutput "❌ Error: PHP no encontrado en PATH" $Red
        return $false
    }
    
    # Verificar archivos de configuración CORS
    $corsFiles = @(
        "$BackendPath\cors.php",
        "$BackendPath\cors-granular.php", 
        "$BackendPath\cors-utils.php"
    )
    
    foreach ($file in $corsFiles) {
        if (Test-Path $file) {
            Write-ColorOutput "✅ Encontrado: $(Split-Path $file -Leaf)" $Green
        } else {
            Write-ColorOutput "❌ Falta: $(Split-Path $file -Leaf)" $Red
            return $false
        }
    }
    
    # Verificar directorio de tests
    if (-not (Test-Path $TestsPath)) {
        Write-ColorOutput "❌ Error: Directorio de tests no encontrado: $TestsPath" $Red
        return $false
    }
    
    Write-ColorOutput "✅ Todos los prerequisitos verificados" $Green
    return $true
}

function Main {
    Write-ColorOutput "🎯 BUBBLE OF TALENTS - CORS TEST RUNNER" $Cyan
    Write-ColorOutput "=======================================" $Cyan
    Write-ColorOutput "Tipo de test: $TestType" $Blue
    Write-ColorOutput "Iniciar servidor: $StartServer" $Blue
    Write-ColorOutput ""
    
    # Verificar prerequisitos
    if (-not (Test-Prerequisites)) {
        Write-ColorOutput "❌ Los prerequisitos no se cumplen. Abortando." $Red
        exit 1
    }
    
    $serverJob = $null
    $allTestsPassed = $true
    
    try {
        # Iniciar servidor si es necesario
        if ($StartServer) {
            if (-not (Test-ServerRunning -Port $ServerPort)) {
                $serverJob = Start-DevServer -Port $ServerPort
                if (-not $serverJob) {
                    Write-ColorOutput "❌ Error: No se pudo iniciar el servidor" $Red
                    exit 1
                }
            } else {
                Write-ColorOutput "✅ Servidor ya está ejecutándose en puerto $ServerPort" $Green
            }
        } else {
            if (-not (Test-ServerRunning -Port $ServerPort)) {
                Write-ColorOutput "⚠️ Advertencia: No hay servidor ejecutándose en puerto $ServerPort" $Yellow
                Write-ColorOutput "   Los tests HTTP pueden fallar. Use -StartServer para iniciar automáticamente." $Yellow
            }
        }
        
        # Ejecutar tests según el tipo especificado
        switch ($TestType) {
            'functional' {
                $allTestsPassed = Run-FunctionalTests
            }
            'performance' {
                $allTestsPassed = Run-PerformanceTests
            }
            'all' {
                $functionalPassed = Run-FunctionalTests
                Start-Sleep -Seconds 2
                $performancePassed = Run-PerformanceTests
                $allTestsPassed = $functionalPassed -and $performancePassed
            }
        }
        
        # Resumen final
        Write-ColorOutput "`n📊 RESUMEN FINAL" $Cyan
        Write-ColorOutput "===============" $Cyan
        
        if ($allTestsPassed) {
            Write-ColorOutput "✅ Todos los tests completados exitosamente" $Green
            Write-ColorOutput "🎉 Sistema CORS granular funcionando correctamente" $Green
        } else {
            Write-ColorOutput "❌ Algunos tests fallaron" $Red
            Write-ColorOutput "🔧 Revise los logs para más detalles" $Yellow
        }
        
        # Mostrar archivos de logs generados
        $logFiles = Get-ChildItem "$TestsPath\*results*.json" -ErrorAction SilentlyContinue
        if ($logFiles) {
            Write-ColorOutput "`n📁 Archivos de resultados generados:" $Blue
            foreach ($file in $logFiles) {
                Write-ColorOutput "   • $($file.Name)" $Blue
            }
        }
        
    }
    finally {
        # Limpiar servidor
        if ($serverJob) {
            Stop-DevServer -ServerJob $serverJob
        }
    }
    
    # Salir con código apropiado
    if ($allTestsPassed) {
        exit 0
    } else {
        exit 1
    }
}

# Ejecutar script principal
Main
