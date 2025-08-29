#!/usr/bin/env bash

# Cypress E2E Testing Runner - Bubble of Talents
# Script para ejecutar pruebas E2E de manera fácil y organizada

set -e  # Salir en caso de error

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para imprimir mensajes coloreados
print_message() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Función para verificar dependencias
check_dependencies() {
    print_message "Verificando dependencias..."

    # Verificar Node.js
    if ! command -v node &> /dev/null; then
        print_error "Node.js no está instalado. Instálalo desde https://nodejs.org/"
        exit 1
    fi

    # Verificar pnpm
    if ! command -v pnpm &> /dev/null; then
        print_error "pnpm no está instalado. Instálalo con: npm install -g pnpm"
        exit 1
    fi

    # Verificar MySQL
    if ! command -v mysql &> /dev/null; then
        print_error "MySQL no está instalado o no está en PATH"
        exit 1
    fi

    print_success "Dependencias verificadas correctamente"
}

# Función para instalar dependencias
install_dependencies() {
    print_message "Instalando dependencias del proyecto..."

    if [ ! -d "node_modules" ]; then
        pnpm install
        print_success "Dependencias instaladas"
    else
        print_message "Dependencias ya instaladas, saltando..."
    fi
}

# Función para configurar base de datos
setup_database() {
    print_message "Configurando base de datos de pruebas..."

    # Verificar si el archivo de seed existe
    if [ ! -f "../cypress_e2e_seed_final_real.sql" ]; then
        print_error "Archivo de seed no encontrado: ../cypress_e2e_seed_final_real.sql"
        exit 1
    fi

    # Crear base de datos si no existe
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS bubbeTalents_DB;" 2>/dev/null || {
        print_warning "No se pudo crear la BD automáticamente. Asegúrate de que MySQL esté corriendo."
    }

    # Ejecutar seed
    mysql -u root bubbeTalents_DB < ../../cypress_e2e_seed_final_real.sql
    print_success "Base de datos configurada con datos de prueba"
}

# Función para verificar configuración
check_configuration() {
    print_message "Verificando configuración..."

    # Verificar archivo de configuración de Cypress
    if [ ! -f "cypress.config.ts" ]; then
        print_error "Archivo cypress.config.ts no encontrado"
        exit 1
    fi

    # Verificar archivos de prueba
    if [ ! -d "cypress/e2e" ]; then
        print_error "Directorio cypress/e2e no encontrado"
        exit 1
    fi

    # Verificar variables de entorno
    if [ ! -f ".env-cypress.local" ]; then
        print_warning "Archivo .env-cypress.local no encontrado. Creando desde ejemplo..."
        if [ -f ".env-cypress.example" ]; then
            cp .env-cypress.example .env-cypress.local
            print_message "Edita .env-cypress.local con tus credenciales reales"
        fi
    fi

    print_success "Configuración verificada"
}

# Función para ejecutar pruebas específicas
run_specific_test() {
    local test_file=$1
    local test_name=$2

    print_message "Ejecutando $test_name..."

    if [ ! -f "cypress/e2e/$test_file" ]; then
        print_error "Archivo de prueba no encontrado: cypress/e2e/$test_file"
        exit 1
    fi

    pnpm exec cypress run --spec "cypress/e2e/$test_file"
}

# Función para ejecutar todas las pruebas
run_all_tests() {
    print_message "Ejecutando todas las pruebas E2E..."

    pnpm exec cypress run --spec "cypress/e2e/**/*.cy.ts"
}

# Función para ejecutar en modo interactivo
run_interactive() {
    print_message "Abriendo Cypress en modo interactivo..."

    pnpm exec cypress open
}

# Función para mostrar menú de opciones
show_menu() {
    echo
    echo "========================================"
    echo "  Cypress E2E Testing - Bubble of Talents"
    echo "========================================"
    echo
    echo "Selecciona una opción:"
    echo "1) Ejecutar JORNADA COMPLETA (recomendado - homepage hasta dashboards)"
    echo "2) Ejecutar todas las pruebas"
    echo "3) Ejecutar solo autenticación"
    echo "4) Ejecutar solo gestión de CVs"
    echo "5) Ejecutar solo búsqueda de empleos"
    echo "6) Ejecutar solo dashboards"
    echo "7) Ejecutar solo funcionalidades avanzadas"
    echo "8) Modo interactivo (recomendado)"
    echo "9) Configurar base de datos"
    echo "10) Verificar configuración"
    echo "0) Salir"
    echo
    read -p "Opción: " option
}

# Función principal
main() {
    # Verificar que estemos en el directorio correcto
    if [ ! -f "package.json" ] || [ ! -d "cypress" ]; then
        print_error "Ejecuta este script desde el directorio frontend/"
        print_message "Ejemplo: cd frontend && ../run-cypress-tests.sh"
        exit 1
    fi

    print_message "🚀 Iniciando Cypress E2E Testing Suite"
    echo

    # Verificar dependencias
    check_dependencies

    # Instalar dependencias
    install_dependencies

    # Verificar configuración
    check_configuration

    # Mostrar menú si no se pasaron argumentos
    if [ $# -eq 0 ]; then
        while true; do
            show_menu

            case $option in
                1)
                    setup_database
                    run_specific_test "00-complete-user-journey.cy.ts" "JORNADA COMPLETA de usuario"
                    break
                    ;;
                2)
                    setup_database
                    run_all_tests
                    break
                    ;;
                3)
                    setup_database
                    run_specific_test "01-authentication.cy.ts" "pruebas de autenticación"
                    break
                    ;;
                4)
                    setup_database
                    run_specific_test "02-cv-management.cy.ts" "pruebas de gestión de CVs"
                    break
                    ;;
                5)
                    setup_database
                    run_specific_test "03-job-search-application.cy.ts" "pruebas de búsqueda de empleos"
                    break
                    ;;
                6)
                    setup_database
                    run_specific_test "04-dashboards.cy.ts" "pruebas de dashboards"
                    break
                    ;;
                7)
                    setup_database
                    run_specific_test "05-advanced-features.cy.ts" "pruebas de funcionalidades avanzadas"
                    break
                    ;;
                8)
                    run_interactive
                    break
                    ;;
                9)
                    setup_database
                    print_success "Base de datos configurada"
                    ;;
                10)
                    check_configuration
                    ;;
                0)
                    print_message "¡Hasta luego!"
                    exit 0
                    ;;
                *)
                    print_error "Opción inválida"
                    ;;
            esac
        done
    else
        # Procesar argumentos de línea de comandos
        case $1 in
            "complete")
                setup_database
                run_specific_test "00-complete-user-journey.cy.ts" "JORNADA COMPLETA de usuario"
                ;;
            "all")
                setup_database
                run_all_tests
                ;;
            "auth")
                setup_database
                run_specific_test "01-authentication.cy.ts" "pruebas de autenticación"
                ;;
            "cv")
                setup_database
                run_specific_test "02-cv-management.cy.ts" "pruebas de gestión de CVs"
                ;;
            "jobs")
                setup_database
                run_specific_test "03-job-search-application.cy.ts" "pruebas de búsqueda de empleos"
                ;;
            "dashboards")
                setup_database
                run_specific_test "04-dashboards.cy.ts" "pruebas de dashboards"
                ;;
            "advanced")
                setup_database
                run_specific_test "05-advanced-features.cy.ts" "pruebas de funcionalidades avanzadas"
                ;;
            "interactive")
                run_interactive
                ;;
            "setup-db")
                setup_database
                ;;
            "check")
                check_configuration
                ;;
            *)
                print_error "Argumento inválido. Usa: complete, all, auth, cv, jobs, dashboards, advanced, interactive, setup-db, check"
                exit 1
                ;;
        esac
    fi

    print_success "✅ Ejecución completada"
}

# Ejecutar función principal
main "$@"
