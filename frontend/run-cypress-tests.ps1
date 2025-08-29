# Cypress E2E Testing Runner - Bubble of Talents (PowerShell)
# Compatible con los specs generados en cypress/e2e/generated/*.cy.ts

param(
  [string]$Action = "",
  [switch]$SkipDb = $false,            # Permite saltar la preparación de BD (útil para tests de UI con intercepts)
  [string]$Section = ""                # Nombre de sección (archivo) a ejecutar dentro de generated
)

# === Colores / Mensajería ===
function Write-Message { param([string]$Message) ; Write-Host "[INFO] $Message" -ForegroundColor Blue }
function Write-Success { param([string]$Message); Write-Host "[SUCCESS] $Message" -ForegroundColor Green }
function Write-Warning { param([string]$Message); Write-Host "[WARNING] $Message" -ForegroundColor Yellow }
function Write-ErrMsg { param([string]$Message); Write-Host "[ERROR] $Message" -ForegroundColor Red }

# === Constantes ===
$RootDir = Get-Location
$CypressDir = "cypress"
$SpecsDir = "cypress/e2e"
$GenDir = "cypress/e2e/generated"
$SeedFilePath = "../cypress_e2e_seed_final_real.sql"
$DbHost = "192.168.1.40"
$DbUser = "admin"
$DbPass = "admin123"
$DbName = "bubbeTalents_DB"

# === Dependencias básicas ===
function Test-Dependencies {
  Write-Message "Verificando dependencias..."

  try { $nodeVersion = node --version ; Write-Message "Node.js: $nodeVersion" }
  catch { Write-ErrMsg "Node.js no está instalado. https://nodejs.org/"; exit 1 }

  try { $pnpmVersion = pnpm --version ; Write-Message "pnpm: $pnpmVersion" }
  catch { Write-ErrMsg "pnpm no está instalado. Ejecuta: npm i -g pnpm"; exit 1 }

  # Sólo comprobamos MySQL si NO se pasa -SkipDb
  if (-not $SkipDb) {
    try { $mysqlVersion = mysql --version ; Write-Message "MySQL CLI: $mysqlVersion" }
    catch { Write-ErrMsg "MySQL CLI no disponible en el PATH"; exit 1 }
  }

  Write-Success "Dependencias OK"
}

# === Instalación de paquetes ===
function Install-Dependencies {
  Write-Message "Instalando dependencias (frontend)..."
  if (!(Test-Path "node_modules")) {
    pnpm install
    Write-Success "Dependencias instaladas"
  }
  else {
    Write-Message "node_modules ya existe, saltando instalación"
  }
}

# === Setup BD (semilla) ===
function Setup-Database {
  if ($SkipDb) {
    Write-Warning "Saltando preparación de BD por flag -SkipDb"
    return
  }

  Write-Message "Configurando base de datos de pruebas..."

  if (!(Test-Path $SeedFilePath)) {
    Write-ErrMsg "Seed no encontrado: $SeedFilePath"
    exit 1
  }

  try {
    mysql -h $DbHost -u $DbUser -p$DbPass -e "CREATE DATABASE IF NOT EXISTS $DbName;" 2>$null
  }
  catch {
    Write-Warning "No se pudo crear la BD. Asegura que MySQL está corriendo y credenciales son correctas."
  }

  Get-Content $SeedFilePath | mysql -h $DbHost -u $DbUser -p$DbPass $DbName
  Write-Success "Base de datos preparada con seed"
}

# === Verificación configuración carpeta Cypress ===
function Test-Configuration {
  Write-Message "Verificando estructura de proyecto..."
  if (!(Test-Path "package.json") -or !(Test-Path $CypressDir)) {
    Write-ErrMsg "Ejecuta este script desde el directorio frontend/"
    Write-Message "Ej: cd frontend; .\run-cypress-tests.ps1"
    exit 1
  }

  if (!(Test-Path "cypress.config.ts")) {
    Write-ErrMsg "No existe cypress.config.ts"
    exit 1
  }

  if (!(Test-Path $SpecsDir)) {
    Write-ErrMsg "No existe directorio $SpecsDir"
    exit 1
  }

  # Directorio generated opcional, pero avisamos
  if (!(Test-Path $GenDir)) {
    Write-Warning "No existe $GenDir (no se han copiado los specs generados)"
  }
  else {
    $count = (Get-ChildItem -Path $GenDir -Filter "*.cy.ts" -Recurse | Measure-Object).Count
    Write-Message "Specs generated detectados: $count"
  }

  Write-Success "Estructura validada"
}

# === Helpers para Generated ===
function Get-GeneratedSpecs {
  if (!(Test-Path $GenDir)) { return @() }
  return Get-ChildItem -Path $GenDir -Filter "*.cy.ts" -Recurse | Sort-Object Name
}

function List-GeneratedSpecs {
  $files = Get-GeneratedSpecs
  if ($files.Count -eq 0) {
    Write-Warning "No hay archivos en $GenDir"
    return
  }
  Write-Host ""
  Write-Host "=== GENERATED SPECS ===" -ForegroundColor Cyan
  $i = 1
  foreach ($f in $files) {
    Write-Host ("{0,2}) {1}" -f $i, ($f.FullName.Replace($RootDir, "").TrimStart("\")))
    $i++
  }
  Write-Host "=======================" -ForegroundColor Cyan
}

function Invoke-GeneratedAll {
  $files = Get-GeneratedSpecs
  if ($files.Count -eq 0) {
    Write-Warning "No hay specs en $GenDir"
    return
  }
  # Un único glob ya los incluye, pero permitimos filtro explícito
  Write-Message "Ejecutando TODOS los generated..."
  pnpm exec cypress run --spec "$GenDir/**/*.cy.ts"
}

function Invoke-GeneratedBySection {
  param([string]$SectionName)

  $files = Get-GeneratedSpecs
  if ($files.Count -eq 0) {
    Write-Warning "No hay specs en $GenDir"
    return
  }

  if ([string]::IsNullOrWhiteSpace($SectionName)) {
    List-GeneratedSpecs
    $choice = Read-Host "Elige número de sección a ejecutar"
    if ($choice -match '^\d+$') {
      $idx = [int]$choice
      if ($idx -ge 1 -and $idx -le $files.Count) {
        $target = $files[$idx - 1].FullName
        Write-Message "Ejecutando sección: $target"
        pnpm exec cypress run --spec $target
        return
      }
    }
    Write-ErrMsg "Selección inválida"
    return
  }
  else {
    # Permite pasar parte del nombre (slug) por CLI: -Section dashboards, -Section auth, etc.
    $match = $files | Where-Object { $_.Name -like "*$SectionName*.cy.ts" }
    if ($match.Count -eq 0) {
      Write-Warning "No se encontró sección que contenga: $SectionName"
      List-GeneratedSpecs
      return
    }
    foreach ($m in $match) {
      Write-Message "Ejecutando sección: $($m.FullName)"
      pnpm exec cypress run --spec $m.FullName
    }
  }
}

# === Lanzadores ===
function Invoke-SpecificTest {
  param([string]$TestFile, [string]$TestName)
  Write-Message "Ejecutando $TestName..."
  $path = Join-Path $SpecsDir $TestFile
  if (!(Test-Path $path)) { Write-ErrMsg "No existe: $path"; exit 1 }
  pnpm exec cypress run --spec $path
}

function Invoke-AllTests {
  Write-Message "Ejecutando TODAS las pruebas E2E (incluye generated)..."
  # Este glob ya incluye generated si está dentro de cypress/e2e/
  pnpm exec cypress run --spec "$SpecsDir/**/*.cy.ts"
}

function Invoke-Interactive {
  Write-Message "Abriendo Cypress en modo interactivo..."
  pnpm exec cypress open
}

# === Menú ===
function Show-Menu {
  Write-Host ""
  Write-Host "========================================" -ForegroundColor Cyan
  Write-Host "  Cypress E2E Testing - Bubble of Talents" -ForegroundColor Cyan
  Write-Host "========================================" -ForegroundColor Cyan
  Write-Host ""
  Write-Host "1) Jornada completa (00-complete-user-journey)"
  Write-Host "2) Ejecutar TODAS las pruebas"
  Write-Host "3) Solo autenticación"
  Write-Host "4) Solo gestión de CVs"
  Write-Host "5) Solo búsqueda de empleos"
  Write-Host "6) Solo dashboards"
  Write-Host "7) Solo funcionalidades avanzadas"
  Write-Host "8) Modo interactivo"
  Write-Host "9) Configurar base de datos"
  Write-Host "10) Verificar configuración"
  Write-Host "11) Ejecutar TODOS los generated"
  Write-Host "12) Ejecutar UNA sección de generated"
  Write-Host "0) Salir"
  Write-Host ""
  $script:option = Read-Host "Opción"
}

# === Main ===
function Invoke-Main {
  if (!(Test-Path "package.json") -or !(Test-Path $CypressDir)) {
    Write-ErrMsg "Ejecuta este script desde el directorio frontend/"
    Write-Message "Ejemplo: cd frontend; .\run-cypress-tests.ps1"
    exit 1
  }

  Write-Message "🚀 Iniciando Cypress E2E Suite"
  Test-Dependencies
  Install-Dependencies
  Test-Configuration

  # Preparación BD salvo que pidan saltarla
  if (-not $SkipDb) { Setup-Database } else { Write-Warning "BD: saltada por -SkipDb" }

  if ($Action -eq "") {
    while ($true) {
      Show-Menu
      switch ($option) {
        "1" { Invoke-SpecificTest "00-complete-user-journey.cy.ts" "Jornada completa"; break }
        "2" { Invoke-AllTests; break }
        "3" { Invoke-SpecificTest "01-authentication.cy.ts" "Autenticación"; break }
        "4" { Invoke-SpecificTest "02-cv-management.cy.ts" "Gestión de CVs"; break }
        "5" { Invoke-SpecificTest "03-job-search-application.cy.ts" "Búsqueda de empleos"; break }
        "6" { Invoke-SpecificTest "04-dashboards.cy.ts" "Dashboards"; break }
        "7" { Invoke-SpecificTest "05-advanced-features.cy.ts" "Avanzadas"; break }
        "8" { Invoke-Interactive; break }
        "9" { Setup-Database; Write-Success "BD OK" }
        "10" { Test-Configuration }
        "11" { Invoke-GeneratedAll; break }
        "12" { Invoke-GeneratedBySection ""; break }
        "0" { Write-Message "Bye"; exit 0 }
        default { Write-ErrMsg "Opción inválida" }
      }
    }
  }
  else {
    switch ($Action) {
      "complete" { Invoke-SpecificTest "00-complete-user-journey.cy.ts" "Jornada completa" }
      "all" { Invoke-AllTests }
      "auth" { Invoke-SpecificTest "01-authentication.cy.ts" "Autenticación" }
      "cv" { Invoke-SpecificTest "02-cv-management.cy.ts" "Gestión de CVs" }
      "jobs" { Invoke-SpecificTest "03-job-search-application.cy.ts" "Búsqueda de empleos" }
      "dashboards" { Invoke-SpecificTest "04-dashboards.cy.ts" "Dashboards" }
      "advanced" { Invoke-SpecificTest "05-advanced-features.cy.ts" "Avanzadas" }
      "interactive" { Invoke-Interactive }
      "setup-db" { Setup-Database }
      "check" { Test-Configuration }
      "gen-all" { Invoke-GeneratedAll }
      default {
        # Permite: .\run-cypress-tests.ps1 -Action gen -Section dashboards
        if ($Action -eq "gen") {
          Invoke-GeneratedBySection $Section
        }
        else {
          Write-ErrMsg "Argumento inválido. Usa: complete|all|auth|cv|jobs|dashboards|advanced|interactive|setup-db|check|gen-all|gen"
          exit 1
        }
      }
    }
  }

  Write-Success "✅ Ejecución completada"
}

Invoke-Main
