@echo off
REM Cypress E2E Testing Runner - Bubble of Talents
REM Script para ejecutar pruebas E2E en Windows

setlocal enabledelayedexpansion

REM Colores para output (aproximados en Windows)
set "RED=[91m"
set "GREEN=[92m"
set "YELLOW=[93m"
set "BLUE=[94m"
set "NC=[0m"

REM Función para imprimir mensajes coloreados
:print_message
echo [INFO] %~1
goto :eof

:print_success
echo [SUCCESS] %~1
goto :eof

:print_warning
echo [WARNING] %~1
goto :eof

:print_error
echo [ERROR] %~1
goto :eof

REM Función para verificar dependencias
:check_dependencies
call :print_message "Verificando dependencias..."

REM Verificar Node.js
node --version >nul 2>&1
if %errorlevel% neq 0 (
    call :print_error "Node.js no está instalado. Instálalo desde https://nodejs.org/"
    exit /b 1
)

REM Verificar pnpm
pnpm --version >nul 2>&1
if %errorlevel% neq 0 (
    call :print_error "pnpm no está instalado. Instálalo con: npm install -g pnpm"
    exit /b 1
)

REM Verificar MySQL
mysql -h 192.168.1.40 -u admin -padmin123 --version >nul 2>&1
if %errorlevel% neq 0 (
    call :print_error "MySQL no está accesible con las credenciales configuradas"
    call :print_message "Verifica que MySQL esté corriendo y las credenciales sean correctas"
    exit /b 1
)

call :print_success "Dependencias verificadas correctamente"
goto :eof

REM Función para instalar dependencias
:install_dependencies
call :print_message "Instalando dependencias del proyecto..."

if not exist "node_modules" (
    pnpm install
    call :print_success "Dependencias instaladas"
) else (
    call :print_message "Dependencias ya instaladas, saltando..."
)
goto :eof

REM Función para configurar base de datos
:setup_database
call :print_message "Configurando base de datos de pruebas..."

REM Verificar si el archivo de seed existe
if not exist "..\cypress_e2e_seed_final_real.sql" (
    call :print_error "Archivo de seed no encontrado: ..\cypress_e2e_seed_final_real.sql"
    exit /b 1
)

REM Crear base de datos si no existe
mysql -h 192.168.1.40 -u admin -padmin123 -e "CREATE DATABASE IF NOT EXISTS bubbeTalents_DB;" 2>nul
if %errorlevel% neq 0 (
    call :print_warning "No se pudo crear la BD automáticamente. Asegúrate de que MySQL esté corriendo."
)

REM Ejecutar seed
mysql -h 192.168.1.40 -u admin -padmin123 bubbeTalents_DB < ..\cypress_e2e_seed_final_real.sql
call :print_success "Base de datos configurada con datos de prueba"
goto :eof

REM Función para verificar configuración
:check_configuration
call :print_message "Verificando configuración..."

REM Verificar archivo de configuración de Cypress
if not exist "cypress.config.ts" (
    call :print_error "Archivo cypress.config.ts no encontrado"
    exit /b 1
)

REM Verificar archivos de prueba
if not exist "cypress\e2e" (
    call :print_error "Directorio cypress\e2e no encontrado"
    exit /b 1
)

REM Verificar variables de entorno
if not exist ".env-cypress.local" (
    call :print_warning "Archivo .env-cypress.local no encontrado. Creando desde ejemplo..."
    if exist ".env-cypress.example" (
        copy .env-cypress.example .env-cypress.local
        call :print_message "Edita .env-cypress.local con tus credenciales reales"
    )
)

call :print_success "Configuración verificada"
goto :eof

REM Función para ejecutar pruebas específicas
:run_specific_test
set "test_file=%~1"
set "test_name=%~2"

call :print_message "Ejecutando %test_name%..."

if not exist "cypress\e2e\%test_file%" (
    call :print_error "Archivo de prueba no encontrado: cypress\e2e\%test_file%"
    exit /b 1
)

pnpm exec cypress run --spec "cypress/e2e/%test_file%"
goto :eof

REM Función para ejecutar todas las pruebas
:run_all_tests
call :print_message "Ejecutando todas las pruebas E2E..."

pnpm exec cypress run --spec "cypress/e2e/**/*.cy.ts"
goto :eof

REM Función para ejecutar en modo interactivo
:run_interactive
call :print_message "Abriendo Cypress en modo interactivo..."

pnpm exec cypress open
goto :eof

REM Función para mostrar menú de opciones
:show_menu
echo.
echo ========================================
echo   Cypress E2E Testing - Bubble of Talents
echo ========================================
echo.
echo Selecciona una opción:
echo 1^) Ejecutar JORNADA COMPLETA ^(recomendado - homepage hasta dashboards^)
echo 2^) Ejecutar todas las pruebas
echo 3^) Ejecutar solo autenticación
echo 4^) Ejecutar solo gestión de CVs
echo 5^) Ejecutar solo búsqueda de empleos
echo 6^) Ejecutar solo dashboards
echo 7^) Ejecutar solo funcionalidades avanzadas
echo 8^) Modo interactivo ^(recomendado^)
echo 9^) Configurar base de datos
echo 10^) Verificar configuración
echo 0^) Salir
echo.
set /p option="Opción: "
goto :eof

REM Función principal
:main
REM Verificar que estemos en el directorio correcto
if not exist "package.json" (
    call :print_error "Ejecuta este script desde el directorio frontend/"
    call :print_message "Ejemplo: cd frontend && ..\run-cypress-tests.bat"
    exit /b 1
)

if not exist "cypress" (
    call :print_error "Directorio cypress no encontrado"
    exit /b 1
)

call :print_message "🚀 Iniciando Cypress E2E Testing Suite"
echo.

REM Verificar dependencias
call :check_dependencies
if %errorlevel% neq 0 exit /b 1

REM Instalar dependencias
call :install_dependencies

REM Verificar configuración
call :check_configuration

REM Procesar argumentos o mostrar menú
if "%~1"=="" (
    :menu_loop
    call :show_menu

    if "%option%"=="1" (
        call :setup_database
        call :run_specific_test "00-complete-user-journey.cy.ts" "JORNADA COMPLETA de usuario"
        goto :end
    ) else if "%option%"=="2" (
        call :setup_database
        call :run_all_tests
        goto :end
    ) else if "%option%"=="3" (
        call :setup_database
        call :run_specific_test "01-authentication.cy.ts" "pruebas de autenticación"
        goto :end
    ) else if "%option%"=="4" (
        call :setup_database
        call :run_specific_test "02-cv-management.cy.ts" "pruebas de gestión de CVs"
        goto :end
    ) else if "%option%"=="5" (
        call :setup_database
        call :run_specific_test "03-job-search-application.cy.ts" "pruebas de búsqueda de empleos"
        goto :end
    ) else if "%option%"=="6" (
        call :setup_database
        call :run_specific_test "04-dashboards.cy.ts" "pruebas de dashboards"
        goto :end
    ) else if "%option%"=="7" (
        call :setup_database
        call :run_specific_test "05-advanced-features.cy.ts" "pruebas de funcionalidades avanzadas"
        goto :end
    ) else if "%option%"=="8" (
        call :run_interactive
        goto :end
    ) else if "%option%"=="9" (
        call :setup_database
        call :print_success "Base de datos configurada"
        goto :menu_loop
    ) else if "%option%"=="10" (
        call :check_configuration
        goto :menu_loop
    ) else if "%option%"=="0" (
        call :print_message "¡Hasta luego!"
        goto :end
    ) else (
        call :print_error "Opción inválida"
        goto :menu_loop
    )
) else (
    REM Procesar argumentos de línea de comandos
    if "%~1"=="all" (
        call :setup_database
        call :run_all_tests
    ) else if "%~1"=="auth" (
        call :setup_database
        call :run_specific_test "01-authentication.cy.ts" "pruebas de autenticación"
    ) else if "%~1"=="cv" (
        call :setup_database
        call :run_specific_test "02-cv-management.cy.ts" "pruebas de gestión de CVs"
    ) else if "%~1"=="jobs" (
        call :setup_database
        call :run_specific_test "03-job-search-application.cy.ts" "pruebas de búsqueda de empleos"
    ) else if "%~1"=="dashboards" (
        call :setup_database
        call :run_specific_test "04-dashboards.cy.ts" "pruebas de dashboards"
    ) else if "%~1"=="advanced" (
        call :setup_database
        call :run_specific_test "05-advanced-features.cy.ts" "pruebas de funcionalidades avanzadas"
    ) else if "%~1"=="interactive" (
        call :run_interactive
    ) else if "%~1"=="setup-db" (
        call :setup_database
    ) else if "%~1"=="check" (
        call :check_configuration
    ) else (
        call :print_error "Argumento inválido. Usa: all, auth, cv, jobs, dashboards, advanced, interactive, setup-db, check"
        exit /b 1
    )
)

:end
call :print_success "✅ Ejecución completada"
goto :eof

REM Ejecutar función principal
call :main %*
