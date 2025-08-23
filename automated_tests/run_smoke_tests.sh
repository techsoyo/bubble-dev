#!/bin/bash

# Script para ejecutar únicamente los smoke tests
# Tests rápidos de funcionalidad básica

set -e

# Configuración
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPORTS_DIR="$SCRIPT_DIR/reports"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Crear directorio de reportes
mkdir -p "$REPORTS_DIR"

echo -e "${BLUE}################################################################################${NC}"
echo -e "${BLUE}#                              SMOKE TESTS                                    #${NC}"
echo -e "${BLUE}#                    Verificación Rápida de Funcionalidad                    #${NC}"
echo -e "${BLUE}################################################################################${NC}"
echo ""

echo -e "${BLUE}Ejecutando smoke tests...${NC}"
echo -e "${BLUE}Timestamp:${NC} $TIMESTAMP"
echo ""

# Determinar comando PHPUnit
if command -v phpunit &> /dev/null; then
    PHPUNIT_CMD="phpunit"
else
    PHPUNIT_CMD="./vendor/bin/phpunit"
fi

# Ejecutar smoke tests
START_TIME=$(date +%s)
REPORT_FILE="$REPORTS_DIR/smoke_test_report_$TIMESTAMP.xml"

if $PHPUNIT_CMD --configuration phpunit.xml --group smoke --log-junit "$REPORT_FILE" --colors=always --verbose; then
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    echo ""
    echo -e "${GREEN}✓ SMOKE TESTS COMPLETADOS EXITOSAMENTE${NC} (${DURATION}s)"
    echo -e "${GREEN}Reporte generado:${NC} $(basename "$REPORT_FILE")"
    
    # Mostrar resumen rápido si el archivo XML existe
    if [[ -f "$REPORT_FILE" ]]; then
        TESTS=$(grep -o 'tests="[0-9]*"' "$REPORT_FILE" | head -1 | grep -o '[0-9]*' || echo "0")
        FAILURES=$(grep -o 'failures="[0-9]*"' "$REPORT_FILE" | head -1 | grep -o '[0-9]*' || echo "0")
        ERRORS=$(grep -o 'errors="[0-9]*"' "$REPORT_FILE" | head -1 | grep -o '[0-9]*' || echo "0")
        
        echo ""
        echo -e "${BLUE}Resumen:${NC}"
        echo -e "  Tests ejecutados: $TESTS"
        echo -e "  Fallos: $FAILURES"
        echo -e "  Errores: $ERRORS"
        
        if [[ $FAILURES -eq 0 && $ERRORS -eq 0 ]]; then
            echo -e "  Estado: ${GREEN}TODOS LOS SMOKE TESTS PASARON${NC}"
        else
            echo -e "  Estado: ${YELLOW}ALGUNOS SMOKE TESTS FALLARON${NC}"
        fi
    fi
    
    echo ""
    echo -e "${BLUE}Los smoke tests verifican:${NC}"
    echo -e "  ✓ Endpoints críticos responden"
    echo -e "  ✓ Autenticación funciona"
    echo -e "  ✓ Base de datos conecta"
    echo -e "  ✓ No hay endpoints debug expuestos"
    echo -e "  ✓ Tiempos de respuesta aceptables"
    
    exit 0
else
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    echo ""
    echo -e "${RED}✗ SMOKE TESTS FALLARON${NC} (${DURATION}s)"
    echo -e "${RED}La aplicación tiene problemas básicos de funcionalidad${NC}"
    echo -e "${YELLOW}Revisar el reporte:${NC} $(basename "$REPORT_FILE")"
    
    exit 1
fi