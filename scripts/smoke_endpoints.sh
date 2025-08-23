#!/bin/bash

set -euo pipefail

# =================================================================================
# SMOKE TEST - BUBBLE OF TALENTS v1.0 - TODOS LOS ENDPOINTS
# =================================================================================
# OBJETIVO: Verificar que TODOS los endpoints respondan correctamente por método
# FECHA: 22 de agosto de 2025
# AUDITOR: Sistema de análisis exhaustivo
# ALCANCE: Router principal + endpoints legacy + validaciones completas
# =================================================================================

# === CONFIGURACIÓN ===
BASE_URL="${BASE_URL:-http://localhost:8000}"
AUTH_COOKIE="${AUTH_COOKIE:-}"
CSRF_TOKEN="${CSRF_TOKEN:-}"
TIMEOUT="${TIMEOUT:-15}"
VERBOSE="${VERBOSE:-0}"

# === CONTADORES ===
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
WARNINGS=0

# === COLORES ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === UTILIDAD ===
log() { echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"; }
success() { echo -e "${GREEN}✓ PASS${NC} $1"; ((PASSED_TESTS++)); }
fail() { echo -e "${RED}✗ FAIL${NC} $1"; ((FAILED_TESTS++)); }
warn() { echo -e "${YELLOW}⚠ WARN${NC} $1"; ((WARNINGS++)); }

# === FUNCIÓN PRINCIPAL DE TESTING ===
hit() {
    local method="$1"
    local endpoint="$2"
    local expected_code="$3"
    local description="$4"
    local payload="${5:-}"
    local extra_headers="${6:-}"

    ((TOTAL_TESTS++))

    local url="${BASE_URL}${endpoint}"
    local start_time=$(date +%s.%3N)
    
    # Construcción del comando curl
    local curl_cmd="curl -s -w '%{http_code}|%{time_total}' --max-time $TIMEOUT -X $method"
    
    # Headers básicos
    curl_cmd+=" -H 'Content-Type: application/json'"
    curl_cmd+=" -H 'Accept: application/json'"
    curl_cmd+=" -H 'X-Requested-With: XMLHttpRequest'"
    
    # Autenticación si está disponible
    [[ -n "$AUTH_COOKIE" ]] && curl_cmd+=" -H 'Cookie: auth_token=$AUTH_COOKIE'"
    [[ -n "$CSRF_TOKEN" ]] && curl_cmd+=" -H 'X-CSRF-Token: $CSRF_TOKEN'"
    
    # Headers extras
    [[ -n "$extra_headers" ]] && curl_cmd+=" $extra_headers"
    
    # Payload para POST/PUT/PATCH
    if [[ -n "$payload" && "$method" =~ ^(POST|PUT|PATCH)$ ]]; then
        curl_cmd+=" -d '$payload'"
    fi
    
    curl_cmd+=" '$url'"
    
    # Ejecutar el comando
    local response
    if ! response=$(eval "$curl_cmd" 2>/dev/null); then
        fail "$method $endpoint - Connection failed: $description"
        return 1
    fi
    
    # Parsear respuesta
    local http_code=$(echo "$response" | tail -1 | cut -d'|' -f1)
    local time_total=$(echo "$response" | tail -1 | cut -d'|' -f2)
    local body=$(echo "$response" | sed '$d')
    
    local end_time=$(date +%s.%3N)
    local duration=$(echo "$end_time - $start_time" | bc -l 2>/dev/null || echo "0.000")
    
    # Validación de código de estado
    if [[ "$http_code" == "$expected_code" ]]; then
        success "$method $endpoint [$http_code] (${duration}s) - $description"
        [[ "$VERBOSE" == "1" ]] && echo "    Response: ${body:0:100}..."
    else
        fail "$method $endpoint - Expected $expected_code but got $http_code: $description"
        [[ "$VERBOSE" == "1" ]] && echo "    Response: ${body:0:200}..."
    fi
    
    # Advertencias por rendimiento
    if (( $(echo "$duration > 2.0" | bc -l 2>/dev/null || echo 0) )); then
        warn "Slow response time: ${duration}s for $method $endpoint"
    fi
}

# =================================================================================
# SECCIÓN 1: ENDPOINTS DEL APProuter.PHP (RUTAS PRINCIPALES)
# =================================================================================

test_main_router_endpoints() {
    log "Iniciando tests de endpoints del router principal..."
    
    # === AI CONTROLLER ===
    hit "POST" "/ai/chat" "200" "Chat con IA" '{"message":"Hello"}'
    hit "POST" "/ai/analyze-cv" "200" "Análisis CV IA" '{"cv_text":"Sample CV"}'
    hit "POST" "/ai/match-job" "200" "Match trabajo IA" '{"cv_id":1,"job_id":1}'
    hit "POST" "/ai/generate-questions" "200" "Generar preguntas IA" '{"job_id":1}'
    
    # === AUTH CONTROLLER ===
    hit "POST" "/auth/login" "200" "Login usuario" '{"email":"test@test.com","password":"password"}'
    hit "POST" "/auth/register" "201" "Registro usuario" '{"email":"new@test.com","password":"password"}'
    hit "POST" "/auth/logout" "200" "Logout usuario"
    hit "POST" "/auth/forgot-password" "200" "Recuperar contraseña" '{"email":"test@test.com"}'
    hit "POST" "/auth/reset-password" "200" "Resetear contraseña" '{"token":"123","password":"newpass"}'
    hit "GET" "/auth/verify/{token}" "200" "Verificar email"
    
    # === JOBS CONTROLLER ===
    hit "GET" "/jobs" "200" "Listar trabajos"
    hit "GET" "/jobs/1" "200" "Obtener trabajo específico"
    hit "POST" "/jobs" "201" "Crear trabajo" '{"title":"Test Job","description":"Test"}'
    hit "PUT" "/jobs/1" "200" "Actualizar trabajo" '{"title":"Updated Job"}'
    hit "DELETE" "/jobs/1" "200" "Eliminar trabajo"
    hit "GET" "/jobs/search" "200" "Buscar trabajos"
    hit "GET" "/jobs/category/{category}" "200" "Trabajos por categoría"
    
    # === CV CONTROLLER ===
    hit "GET" "/cvs" "200" "Listar CVs"
    hit "GET" "/cvs/1" "200" "Obtener CV específico"
    hit "POST" "/cvs" "201" "Crear CV" '{"name":"Test CV","content":"CV content"}'
    hit "PUT" "/cvs/1" "200" "Actualizar CV" '{"name":"Updated CV"}'
    hit "DELETE" "/cvs/1" "200" "Eliminar CV"
    hit "POST" "/cvs/upload" "201" "Subir CV archivo"
    
    # === APPLICATIONS CONTROLLER ===
    hit "GET" "/applications" "200" "Listar aplicaciones"
    hit "GET" "/applications/1" "200" "Obtener aplicación específica"
    hit "POST" "/applications" "201" "Crear aplicación" '{"job_id":1,"cv_id":1}'
    hit "PUT" "/applications/1" "200" "Actualizar aplicación" '{"status":"reviewed"}'
    hit "DELETE" "/applications/1" "200" "Eliminar aplicación"
    
    # === CANDIDATES CONTROLLER ===
    hit "GET" "/candidates" "200" "Listar candidatos"
    hit "GET" "/candidates/1" "200" "Obtener candidato específico"
    hit "POST" "/candidates" "201" "Crear candidato" '{"name":"Test Candidate"}'
    hit "PUT" "/candidates/1" "200" "Actualizar candidato" '{"name":"Updated Candidate"}'
    hit "DELETE" "/candidates/1" "200" "Eliminar candidato"
    
    # === COMPANIES CONTROLLER ===
    hit "GET" "/companies" "200" "Listar empresas"
    hit "GET" "/companies/1" "200" "Obtener empresa específica"
    hit "POST" "/companies" "201" "Crear empresa" '{"name":"Test Company"}'
    hit "PUT" "/companies/1" "200" "Actualizar empresa" '{"name":"Updated Company"}'
    hit "DELETE" "/companies/1" "200" "Eliminar empresa"
    
    # === DEPARTMENTS CONTROLLER ===
    hit "GET" "/departments" "200" "Listar departamentos"
    hit "GET" "/departments/1" "200" "Obtener departamento específico"
    hit "POST" "/departments" "201" "Crear departamento" '{"name":"Test Dept"}'
    hit "PUT" "/departments/1" "200" "Actualizar departamento" '{"name":"Updated Dept"}'
    hit "DELETE" "/departments/1" "200" "Eliminar departamento"
    
    # === USERS CONTROLLER ===
    hit "GET" "/users" "200" "Listar usuarios"
    hit "GET" "/users/1" "200" "Obtener usuario específico"
    hit "POST" "/users" "201" "Crear usuario" '{"email":"test@test.com"}'
    hit "PUT" "/users/1" "200" "Actualizar usuario" '{"name":"Updated User"}'
    hit "DELETE" "/users/1" "200" "Eliminar usuario"
    hit "GET" "/users/profile" "200" "Perfil usuario actual"
    hit "PUT" "/users/profile" "200" "Actualizar perfil" '{"name":"New Name"}'
    
    # === NOTIFICATIONS CONTROLLER ===
    hit "GET" "/notifications" "200" "Listar notificaciones"
    hit "GET" "/notifications/1" "200" "Obtener notificación específica"
    hit "POST" "/notifications" "201" "Crear notificación" '{"message":"Test notification"}'
    hit "PUT" "/notifications/1/read" "200" "Marcar como leída"
    hit "DELETE" "/notifications/1" "200" "Eliminar notificación"
    
    # === DASHBOARD CONTROLLER ===
    hit "GET" "/dashboard" "200" "Dashboard principal"
    hit "GET" "/dashboard/stats" "200" "Estadísticas dashboard"
    hit "GET" "/dashboard/recent-activities" "200" "Actividades recientes"
    hit "GET" "/dashboard/analytics" "200" "Analytics dashboard"
}

# =================================================================================
# SECCIÓN 2: ENDPOINTS LEGACY (ARCHIVOS PHP DIRECTOS)
# =================================================================================

test_legacy_endpoints() {
    log "Iniciando tests de endpoints legacy PHP..."
    
    # === BACKEND LEGACY ===
    hit "GET" "/backend/router.php" "200" "Router backend legacy"
    hit "GET" "/backend/autoload.php" "200" "Autoloader backend"
    hit "POST" "/backend/check_candidates.php" "200" "Verificar candidatos legacy"
    hit "GET" "/backend/check_db_schema.php" "200" "Verificar esquema DB legacy"
    hit "GET" "/backend/hr_dashboard_summary.php" "200" "Dashboard HR legacy"
    hit "POST" "/backend/verification_test.php" "200" "Test verificación legacy"
    hit "GET" "/backend/verify-setup.php" "200" "Verificar setup legacy"
    
    # === CONFIG LEGACY ===
    hit "GET" "/config/database.php" "200" "Configuración DB legacy"
    hit "GET" "/config/cors.php" "200" "Configuración CORS legacy"
    
    # === PUBLIC LEGACY ===
    hit "GET" "/public/index.php" "200" "Index público legacy"
    hit "GET" "/public/api.php" "200" "API pública legacy"
    
    # === SCRIPTS LEGACY ===
    hit "POST" "/scripts/migrate.php" "200" "Script migración"
    hit "GET" "/scripts/setup.php" "200" "Script configuración"
}

# =================================================================================
# SECCIÓN 3: ENDPOINTS FALTANTES (INFERIDOS DE LA ESTRUCTURA)
# =================================================================================

test_missing_endpoints() {
    log "Iniciando tests de endpoints potencialmente faltantes..."
    
    # === ANALYTICS ENDPOINTS ===
    hit "GET" "/analytics/reports" "404" "Reportes analytics (faltante)"
    hit "GET" "/analytics/metrics" "404" "Métricas analytics (faltante)"
    hit "POST" "/analytics/track" "404" "Tracking analytics (faltante)"
    
    # === ADMIN ENDPOINTS ===
    hit "GET" "/admin/settings" "404" "Configuración admin (faltante)"
    hit "GET" "/admin/users" "404" "Gestión usuarios admin (faltante)"
    hit "POST" "/admin/backup" "404" "Backup admin (faltante)"
    
    # === INTEGRATION ENDPOINTS ===
    hit "GET" "/integrations/linkedin" "404" "Integración LinkedIn (faltante)"
    hit "GET" "/integrations/indeed" "404" "Integración Indeed (faltante)"
    hit "POST" "/integrations/webhook" "404" "Webhook integraciones (faltante)"
    
    # === REPORTING ENDPOINTS ===
    hit "GET" "/reports/applications" "404" "Reporte aplicaciones (faltante)"
    hit "GET" "/reports/performance" "404" "Reporte rendimiento (faltante)"
    hit "POST" "/reports/generate" "404" "Generar reporte (faltante)"
    
    # === SEARCH ENDPOINTS ===
    hit "GET" "/search/global" "404" "Búsqueda global (faltante)"
    hit "POST" "/search/advanced" "404" "Búsqueda avanzada (faltante)"
    hit "GET" "/search/suggestions" "404" "Sugerencias búsqueda (faltante)"
}

# =================================================================================
# SECCIÓN 4: ARCHIVOS DE SEGURIDAD (NO DEBERÍAN SER ACCESIBLES)
# =================================================================================

test_security_endpoints() {
    log "Iniciando tests de seguridad (archivos que NO deben ser accesibles)..."
    
    # === ARCHIVOS SENSIBLES ===
    hit "GET" "/.env" "404" "Archivo .env (debe estar protegido)"
    hit "GET" "/composer.json" "404" "Composer.json (debe estar protegido)"
    hit "GET" "/composer.lock" "404" "Composer.lock (debe estar protegido)"
    hit "GET" "/config.php" "404" "Config.php (debe estar protegido)"
    hit "GET" "/database.sql" "404" "Database.sql (debe estar protegido)"
    hit "GET" "/backup.sql" "404" "Backup.sql (debe estar protegido)"
    
    # === DIRECTORIOS SENSIBLES ===
    hit "GET" "/vendor/" "403" "Directorio vendor (debe estar protegido)"
    hit "GET" "/logs/" "403" "Directorio logs (debe estar protegido)"
    hit "GET" "/storage/" "403" "Directorio storage (debe estar protegido)"
    hit "GET" "/config/" "403" "Directorio config (debe estar protegido)"
    
    # === ARCHIVOS ADMINISTRATIVOS ===
    hit "GET" "/phpinfo.php" "404" "PHPInfo (debe estar protegido)"
    hit "GET" "/test.php" "404" "Test.php (debe estar protegido)"
    hit "GET" "/debug.php" "404" "Debug.php (debe estar protegido)"
    hit "GET" "/setup.php" "404" "Setup.php (debe estar protegido)"
}

# =================================================================================
# SECCIÓN 5: ENDPOINTS DE SALUD DEL SISTEMA
# =================================================================================

test_system_health() {
    log "Iniciando tests de salud del sistema..."
    
    # === HEALTH CHECKS ===
    hit "GET" "/health" "200" "Health check principal"
    hit "GET" "/health/database" "200" "Health check database"
    hit "GET" "/health/storage" "200" "Health check storage"
    hit "GET" "/health/cache" "200" "Health check cache"
    
    # === STATUS ENDPOINTS ===
    hit "GET" "/status" "200" "Status del sistema"
    hit "GET" "/version" "200" "Versión del sistema"
    hit "GET" "/ping" "200" "Ping del sistema"
    
    # === INFO ENDPOINTS ===
    hit "GET" "/info/server" "200" "Información del servidor"
    hit "GET" "/info/php" "200" "Información PHP"
    hit "GET" "/info/database" "200" "Información database"
}

# =================================================================================
# SECCIÓN 6: CORS Y PREFLIGHT
# =================================================================================

test_cors_preflight() {
    log "Iniciando tests CORS y preflight..."
    
    # === PREFLIGHT REQUESTS ===
    hit "OPTIONS" "/ai/chat" "200" "Preflight AI chat" "" "-H 'Origin: http://localhost:3000' -H 'Access-Control-Request-Method: POST'"
    hit "OPTIONS" "/auth/login" "200" "Preflight auth login" "" "-H 'Origin: http://localhost:3000' -H 'Access-Control-Request-Method: POST'"
    hit "OPTIONS" "/jobs" "200" "Preflight jobs" "" "-H 'Origin: http://localhost:3000' -H 'Access-Control-Request-Method: GET'"
    hit "OPTIONS" "/cvs" "200" "Preflight CVs" "" "-H 'Origin: http://localhost:3000' -H 'Access-Control-Request-Method: GET'"
    
    # === CORS HEADERS VALIDATION ===
    log "Verificando headers CORS..."
    local cors_response=$(curl -s -I -X GET "$BASE_URL/ai/chat" -H "Origin: http://localhost:3000")
    if echo "$cors_response" | grep -q "Access-Control-Allow-Origin"; then
        success "CORS Headers presentes en respuesta"
    else
        fail "CORS Headers faltantes en respuesta"
    fi
}

# =================================================================================
# FUNCIÓN PRINCIPAL
# =================================================================================

main() {
    log "🚀 INICIANDO SMOKE TEST COMPLETO - BUBBLE OF TALENTS v1.0"
    log "🌐 Base URL: $BASE_URL"
    log "⏱️  Timeout: ${TIMEOUT}s"
    log "🔍 Modo verbose: $([[ "$VERBOSE" == "1" ]] && echo "Activado" || echo "Desactivado")"
    echo

    # === VERIFICACIÓN INICIAL ===
    log "Verificando conectividad con el servidor..."
    if ! curl -sf --max-time 5 "$BASE_URL" > /dev/null 2>&1; then
        fail "❌ No se puede conectar con $BASE_URL"
        exit 1
    fi
    success "✅ Servidor accesible en $BASE_URL"
    echo

    # === EJECUCIÓN DE TESTS ===
    local start_time=$(date +%s)
    
    test_main_router_endpoints
    echo
    test_legacy_endpoints
    echo
    test_missing_endpoints
    echo
    test_security_endpoints
    echo
    test_system_health
    echo
    test_cors_preflight
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    # === RESUMEN FINAL ===
    echo
    log "📊 RESUMEN DE RESULTADOS"
    echo "=================================="
    echo -e "✅ ${GREEN}Pruebas exitosas:${NC} $PASSED_TESTS"
    echo -e "❌ ${RED}Pruebas fallidas:${NC} $FAILED_TESTS"
    echo -e "⚠️  ${YELLOW}Advertencias:${NC} $WARNINGS"
    echo -e "📈 ${BLUE}Total de pruebas:${NC} $TOTAL_TESTS"
    echo -e "⏱️  ${BLUE}Tiempo total:${NC} ${duration}s"
    echo "=================================="
    
    # === CÁLCULO DE TASA DE ÉXITO ===
    if [[ $TOTAL_TESTS -gt 0 ]]; then
        local success_rate=$((PASSED_TESTS * 100 / TOTAL_TESTS))
        echo -e "🎯 ${BLUE}Tasa de éxito:${NC} ${success_rate}%"
        
        if [[ $success_rate -ge 90 ]]; then
            echo -e "${GREEN}🎉 EXCELENTE: Sistema funcionando correctamente${NC}"
        elif [[ $success_rate -ge 70 ]]; then
            echo -e "${YELLOW}⚠️  ACEPTABLE: Sistema funcionando con advertencias${NC}"
        else
            echo -e "${RED}🚨 CRÍTICO: Sistema requiere atención inmediata${NC}"
        fi
    fi
    
    echo
    log "🏁 Smoke test completado"
    
    # === CÓDIGO DE SALIDA ===
    if [[ $FAILED_TESTS -gt 0 ]]; then
        exit 1
    else
        exit 0
    fi
}

# === MANEJO DE ARGUMENTOS ===
show_help() {
    echo "Uso: $0 [opciones]"
    echo ""
    echo "Opciones:"
    echo "  -u, --url URL          Base URL del servidor (default: http://localhost:8000)"
    echo "  -a, --auth COOKIE      Cookie de autenticación"
    echo "  -c, --csrf TOKEN       Token CSRF"
    echo "  -t, --timeout SECONDS  Timeout para requests (default: 15)"
    echo "  -v, --verbose          Modo verbose (mostrar respuestas)"
    echo "  -h, --help             Mostrar esta ayuda"
    echo ""
    echo "Ejemplos:"
    echo "  $0                                    # Test básico"
    echo "  $0 -u http://localhost:8080 -v       # Test con URL personalizada y verbose"
    echo "  $0 -a 'auth123' -c 'csrf456'         # Test con autenticación"
}

# Procesar argumentos
while [[ $# -gt 0 ]]; do
    case $1 in
        -u|--url)
            BASE_URL="$2"
            shift 2
            ;;
        -a|--auth)
            AUTH_COOKIE="$2"
            shift 2
            ;;
        -c|--csrf)
            CSRF_TOKEN="$2"
            shift 2
            ;;
        -t|--timeout)
            TIMEOUT="$2"
            shift 2
            ;;
        -v|--verbose)
            VERBOSE=1
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            echo "Opción desconocida: $1"
            show_help
            exit 1
            ;;
    esac
done

# === EJECUCIÓN ===
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
