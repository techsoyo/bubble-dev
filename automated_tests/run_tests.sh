#!/bin/bash

# Script principal para ejecutar toda la suite de testing automatizado
# Basado en los 324 casos de prueba identificados en la auditoría

set -e  # Salir si cualquier comando falla

# Configuración
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_DIR="$SCRIPT_DIR"
REPORTS_DIR="$TEST_DIR/reports"
LOG_FILE="$REPORTS_DIR/test_execution.log"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Crear directorio de reportes
mkdir -p "$REPORTS_DIR"

# Función para logging
log() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] $message" | tee -a "$LOG_FILE"
}

# Función para imprimir headers
print_header() {
    local title="$1"
    echo -e "\n${BLUE}=================================================================================${NC}"
    echo -e "${BLUE}  $title${NC}"
    echo -e "${BLUE}=================================================================================${NC}\n"
    log "Starting: $title"
}

# Función para verificar dependencias
check_dependencies() {
    print_header "VERIFICANDO DEPENDENCIAS"
    
    # Verificar PHP
    if ! command -v php &> /dev/null; then
        echo -e "${RED}ERROR: PHP no está instalado${NC}"
        exit 1
    fi
    
    local php_version=$(php -v | head -n1)
    echo -e "${GREEN}✓${NC} PHP encontrado: $php_version"
    
    # Verificar PHPUnit
    if ! command -v phpunit &> /dev/null && ! test -f "vendor/bin/phpunit"; then
        echo -e "${YELLOW}ADVERTENCIA: PHPUnit no encontrado. Intentando instalar...${NC}"
        
        if command -v composer &> /dev/null; then
            composer require --dev phpunit/phpunit:^9.6
        else
            echo -e "${RED}ERROR: Composer no encontrado. Instala PHPUnit manualmente.${NC}"
            exit 1
        fi
    fi
    
    echo -e "${GREEN}✓${NC} PHPUnit disponible"
    
    # Verificar estructura de directorios
    local required_dirs=("tests/Unit" "tests/Security" "tests/Smoke" "tests/Integration")
    for dir in "${required_dirs[@]}"; do
        if [[ ! -d "$TEST_DIR/$dir" ]]; then
            echo -e "${RED}ERROR: Directorio requerido no encontrado: $dir${NC}"
            exit 1
        fi
        echo -e "${GREEN}✓${NC} Directorio encontrado: $dir"
    done
    
    log "Dependencies check completed successfully"
}

# Función para limpiar reportes anteriores
cleanup_reports() {
    print_header "LIMPIANDO REPORTES ANTERIORES"
    
    local old_reports=$(find "$REPORTS_DIR" -name "*.xml" -o -name "*.html" -o -name "*.txt" | wc -l)
    
    if [[ $old_reports -gt 0 ]]; then
        rm -f "$REPORTS_DIR"/*.xml "$REPORTS_DIR"/*.html "$REPORTS_DIR"/*.txt
        echo -e "${GREEN}✓${NC} Eliminados $old_reports reportes antiguos"
    else
        echo -e "${GREEN}✓${NC} No hay reportes antiguos que limpiar"
    fi
    
    log "Reports cleanup completed"
}

# Función para ejecutar suite de tests
run_test_suite() {
    local suite_name="$1"
    local test_path="$2"
    local description="$3"
    
    print_header "EJECUTANDO: $description"
    
    local start_time=$(date +%s)
    local report_file="$REPORTS_DIR/${suite_name}_report_$TIMESTAMP.xml"
    
    echo -e "${BLUE}Ruta de tests:${NC} $test_path"
    echo -e "${BLUE}Reporte:${NC} $report_file"
    echo ""
    
    # Ejecutar PHPUnit
    local phpunit_cmd
    if command -v phpunit &> /dev/null; then
        phpunit_cmd="phpunit"
    else
        phpunit_cmd="./vendor/bin/phpunit"
    fi
    
    # Comando PHPUnit con configuración completa
    local cmd="$phpunit_cmd --configuration phpunit.xml --testsuite '$suite_name' --log-junit '$report_file' --colors=always --verbose"
    
    log "Executing command: $cmd"
    
    if eval $cmd; then
        local end_time=$(date +%s)
        local duration=$((end_time - start_time))
        echo -e "\n${GREEN}✓ SUITE $suite_name COMPLETADA EXITOSAMENTE${NC} (${duration}s)"
        log "Suite $suite_name completed successfully in ${duration}s"
        return 0
    else
        local end_time=$(date +%s)
        local duration=$((end_time - start_time))
        echo -e "\n${RED}✗ SUITE $suite_name FALLO${NC} (${duration}s)"
        log "Suite $suite_name failed in ${duration}s"
        return 1
    fi
}

# Función para generar reporte final
generate_final_report() {
    print_header "GENERANDO REPORTE FINAL"
    
    local final_report="$REPORTS_DIR/final_test_report_$TIMESTAMP.md"
    local xml_reports=$(find "$REPORTS_DIR" -name "*_$TIMESTAMP.xml" | sort)
    
    cat > "$final_report" << EOF
# REPORTE FINAL DE TESTING AUTOMATIZADO

**Fecha de Ejecución:** $(date)
**Casos de Prueba Totales:** 324 (Matriz de Auditoría)
**Suites Ejecutadas:** $(echo "$xml_reports" | wc -l)

---

## RESUMEN EJECUTIVO

Este reporte cubre la ejecución de la suite completa de testing automatizado
basada en los 324 casos de prueba identificados en la auditoría de seguridad y
paridad de la API.

## SUITES EJECUTADAS

EOF
    
    local total_tests=0
    local total_failures=0
    local total_errors=0
    
    # Procesar cada reporte XML
    for xml_report in $xml_reports; do
        if [[ -f "$xml_report" ]]; then
            local suite_name=$(basename "$xml_report" | cut -d'_' -f1)
            
            # Extraer estadísticas del XML (simplificado)
            local tests=$(grep -o 'tests="[0-9]*"' "$xml_report" | head -1 | grep -o '[0-9]*')
            local failures=$(grep -o 'failures="[0-9]*"' "$xml_report" | head -1 | grep -o '[0-9]*')
            local errors=$(grep -o 'errors="[0-9]*"' "$xml_report" | head -1 | grep -o '[0-9]*')
            
            # Valores por defecto si no se encuentran
            tests=${tests:-0}
            failures=${failures:-0}
            errors=${errors:-0}
            
            local success_rate=0
            if [[ $tests -gt 0 ]]; then
                success_rate=$(( (tests - failures - errors) * 100 / tests ))
            fi
            
            total_tests=$((total_tests + tests))
            total_failures=$((total_failures + failures))
            total_errors=$((total_errors + errors))
            
            cat >> "$final_report" << EOF
### Suite: $suite_name

- **Tests Ejecutados:** $tests
- **Fallos:** $failures 
- **Errores:** $errors
- **Tasa de Éxito:** $success_rate%
- **Reporte XML:** $(basename "$xml_report")

EOF
        fi
    done
    
    # Calcular estadísticas finales
    local final_success_rate=0
    if [[ $total_tests -gt 0 ]]; then
        final_success_rate=$(( (total_tests - total_failures - total_errors) * 100 / total_tests ))
    fi
    
    cat >> "$final_report" << EOF

---

## ESTADÍSTICAS FINALES

| Métrica | Valor |
|---------|-------|
| **Tests Totales** | $total_tests |
| **Tests Exitosos** | $((total_tests - total_failures - total_errors)) |
| **Fallos** | $total_failures |
| **Errores** | $total_errors |
| **Tasa de Éxito Final** | **$final_success_rate%** |

## CRITERIOS DE ACEPTACIÓN

- ☑️ **Tasa de éxito > 80%:** $(if [[ $final_success_rate -gt 80 ]]; then echo "CUMPLIDO"; else echo "NO CUMPLIDO"; fi)
- ☑️ **Smoke tests pasan:** Ver reporte de Smoke suite
- ☑️ **Tests de seguridad pasan:** Ver reporte de Security suite
- ☑️ **Tests críticos pasan:** Ver reportes de Unit suites

## RECOMENDACIONES

EOF
    
    if [[ $final_success_rate -ge 90 ]]; then
        echo "- ✅ **EXCELENTE:** La API pasa la mayoría de los tests. Lista para revisión de producción." >> "$final_report"
    elif [[ $final_success_rate -ge 80 ]]; then
        echo "- ⚠️ **BUENO:** La API pasa los tests principales. Revisar fallos menores antes de producción." >> "$final_report"
    elif [[ $final_success_rate -ge 60 ]]; then
        echo "- 🔴 **REGULAR:** La API tiene problemas significativos. Corrección requerida antes de producción." >> "$final_report"
    else
        echo "- 🚑 **CRÍTICO:** La API falla tests fundamentales. NO APTA PARA PRODUCCIÓN." >> "$final_report"
    fi
    
    cat >> "$final_report" << EOF

---

## ARCHIVOS GENERADOS

- **Reporte Final:** $(basename "$final_report")
- **Log de Ejecución:** $(basename "$LOG_FILE")
- **Reportes XML:** $(echo "$xml_reports" | wc -l) archivos
- **Timestamp:** $TIMESTAMP

EOF
    
    echo -e "${GREEN}✓${NC} Reporte final generado: $final_report"
    log "Final report generated: $final_report"
    
    # Mostrar resumen en consola
    echo ""
    echo -e "${BLUE}RESUMEN FINAL:${NC}"
    echo -e "  Tests Totales: $total_tests"
    echo -e "  Tasa de Éxito: $final_success_rate%"
    
    if [[ $final_success_rate -ge 80 ]]; then
        echo -e "  Estado: ${GREEN}ACEPTABLE${NC}"
    else
        echo -e "  Estado: ${RED}REQUIERE ATENCIÓN${NC}"
    fi
}

# Función principal
main() {
    local start_total=$(date +%s)
    
    echo -e "${BLUE}#################################################################################${NC}"
    echo -e "${BLUE}#                    SUITE DE TESTING AUTOMATIZADO                             #${NC}"
    echo -e "${BLUE}#                           324 Casos de Prueba                                #${NC}"
    echo -e "${BLUE}#                        Basado en Auditoría de API                            #${NC}"
    echo -e "${BLUE}#################################################################################${NC}"
    echo ""
    
    log "=== INICIO DE EJECUCION DE TESTS ==="
    log "Timestamp: $TIMESTAMP"
    log "Test directory: $TEST_DIR"
    log "Reports directory: $REPORTS_DIR"
    
    # Verificar dependencias
    check_dependencies
    
    # Limpiar reportes anteriores
    cleanup_reports
    
    local failed_suites=0
    local total_suites=0
    
    # Ejecutar suites en orden de prioridad
    
    # 1. SMOKE TESTS (Funcionalidad básica)
    total_suites=$((total_suites + 1))
    if ! run_test_suite "Smoke Tests" "tests/Smoke" "SMOKE TESTS - Verificación Básica (Prioridad 1)"; then
        failed_suites=$((failed_suites + 1))
    fi
    
    # 2. SECURITY TESTS (Vulnerabilidades críticas)
    total_suites=$((total_suites + 1))
    if ! run_test_suite "Security Tests" "tests/Security" "SECURITY TESTS - Vulnerabilidades Críticas (Prioridad 1)"; then
        failed_suites=$((failed_suites + 1))
    fi
    
    # 3. UNIT TESTS (Funcionalidades core)
    total_suites=$((total_suites + 1))
    if ! run_test_suite "Unit Tests" "tests/Unit" "UNIT TESTS - Funcionalidades Core (Prioridad 2)"; then
        failed_suites=$((failed_suites + 1))
    fi
    
    # 4. INTEGRATION TESTS (Flujos completos)
    total_suites=$((total_suites + 1))
    if ! run_test_suite "Integration Tests" "tests/Integration" "INTEGRATION TESTS - Flujos Completos (Prioridad 3)"; then
        failed_suites=$((failed_suites + 1))
    fi
    
    # Generar reporte final
    generate_final_report
    
    local end_total=$(date +%s)
    local total_duration=$((end_total - start_total))
    
    echo ""
    print_header "EJECUCIÓN COMPLETADA"
    
    echo -e "${BLUE}Duración Total:${NC} ${total_duration}s"
    echo -e "${BLUE}Suites Ejecutadas:${NC} $total_suites"
    echo -e "${BLUE}Suites Fallidas:${NC} $failed_suites"
    
    if [[ $failed_suites -eq 0 ]]; then
        echo -e "${GREEN}✓ TODAS LAS SUITES PASARON EXITOSAMENTE${NC}"
        log "=== ALL TEST SUITES PASSED SUCCESSFULLY ==="
        exit 0
    else
        echo -e "${RED}✗ $failed_suites DE $total_suites SUITES FALLARON${NC}"
        log "=== $failed_suites OF $total_suites TEST SUITES FAILED ==="
        exit 1
    fi
}

# Manejar señales para cleanup
trap 'echo -e "\n${RED}Ejecución interrumpida por usuario${NC}"; exit 130' INT TERM

# Ejecutar main si es llamado directamente
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi