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

    # Construcción de headers
    local curl_cmd="curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time $TIMEOUT -X $method"
    curl_cmd="$curl_cmd -H 'Content-Type: application/json'"

    # Headers extra
    if [[ -n "$extra_headers" ]]; then
        # Permite múltiples líneas separadas por \n
        while IFS= read -r line; do
            [[ -n "$line" ]] && curl_cmd="$curl_cmd -H '$line'"
        done <<< "$extra_headers"
    fi

    # Cookie de autenticación
    if [[ -n "${AUTH_COOKIE}" ]]; then
        curl_cmd="$curl_cmd -H 'Cookie: ${AUTH_COOKIE}'"
    fi

    # CSRF
    if [[ -n "${CSRF_TOKEN}" ]]; then
        curl_cmd="$curl_cmd -H 'X-CSRF-Token: ${CSRF_TOKEN}'"
    fi

    # Payload para métodos con cuerpo
    if [[ -n "$payload" && "$method" != "GET" && "$method" != "DELETE" && "$method" != "HEAD" && "$method" != "OPTIONS" ]]; then
        curl_cmd="$curl_cmd -d '$payload'"
    fi

    curl_cmd="$curl_cmd '$url'"

    [[ "$VERBOSE" == "1" ]] && log "Ejecutando: $curl_cmd"

    # Ejecutar y capturar "HTTP_CODE TIME_TOTAL"
    local response
    response=$(eval "$curl_cmd" 2>/dev/null || true)

    # Parsear HTTP code y tiempo total
    # response esperado: "200 0.123"
    local http_code
    local time_total
    http_code=$(awk '{print $1}' <<< "$response")
    time_total=$(awk '{print $2}' <<< "$response")

    # Si curl falló duro, http_code puede estar vacío
    if [[ -z "${http_code:-}" ]]; then
        http_code="000"
        time_total="999.999"
    fi

    # Validación
    if [[ "$http_code" == "$expected_code" ]]; then
        # Warning si tarda > 0.8s
        if awk "BEGIN {exit !($time_total > 0.8)}"; then
            warn "$method $endpoint - $description (${http_code}, ${time_total}s)"
        else
            success "$method $endpoint - $description (${http_code}, ${time_total}s)"
        fi
    else
        fail "$method $endpoint - $description (esperado: $expected_code, obtenido: $http_code, tiempo: ${time_total}s)"
    fi
}

# === CASOS DE PRUEBA ESPECÍFICOS ===
case_happy() { hit "$1" "$2" "200" "$3 - Happy path" "${4:-}"; }
case_created() { hit "$1" "$2" "201" "$3 - Created" "${4:-}"; }
case_unauth() {
    local method="$1"; local endpoint="$2"; local description="$3"
    local temp_cookie="${AUTH_COOKIE:-}"; AUTH_COOKIE=""
    hit "$method" "$endpoint" "401" "$description - Unauthorized"
    AUTH_COOKIE="$temp_cookie"
}
case_forbidden() { hit "$1" "$2" "403" "$3 - Forbidden"; }
case_badreq() { hit "$1" "$2" "400" "$3 - Bad Request" "${4:-{\"invalid\":\"data\"}}"; }
case_unprocessable() { hit "$1" "$2" "422" "$3 - Unprocessable Entity" "${4:-{\"email\":\"invalid-email\"}}"; }
case_notfound() { hit "$1" "$2" "404" "$3 - Not Found"; }
case_method_not_allowed() { hit "$1" "$2" "405" "$3 - Method Not Allowed"; }

# =================================================================================
# MATRIZ COMPLETA DE ENDPOINTS DETECTADOS
# =================================================================================
main() {
    log "🚀 INICIANDO SMOKE TEST COMPLETO - BUBBLE OF TALENTS v1.0"
    log "📍 BASE_URL: $BASE_URL"
    log "🍪 AUTH_COOKIE: $([ -n "${AUTH_COOKIE:-}" ] && echo "SET" || echo "NOT SET")"
    log "🔐 CSRF_TOKEN: $([ -n "${CSRF_TOKEN:-}" ] && echo "SET" || echo "NOT SET")"
    log "⏱️  TIMEOUT: ${TIMEOUT}s"
    echo ""

    # === APLICACIONES ===
    log "📋 TESTING: APLICACIONES"
    case_happy "GET" "/api/applications" "Lista aplicaciones"
    case_created "POST" "/api/applications" "Crear aplicación" '{"candidate_id":"cnd-123","job_id":1,"cv_url":"test.pdf"}'
    case_unauth "POST" "/api/applications" "Crear aplicación sin auth"
    case_badreq "POST" "/api/applications" "Crear aplicación datos inválidos" '{"invalid":"data"}'

    case_happy "GET" "/api/applications/1" "Ver aplicación específica"
    case_notfound "GET" "/api/applications/99999" "Ver aplicación inexistente"

    case_happy "PUT" "/api/applications/1" "Actualizar aplicación" '{"status":"in_progress"}'
    case_unauth "PUT" "/api/applications/1" "Actualizar aplicación sin auth"
    case_notfound "PUT" "/api/applications/99999" "Actualizar aplicación inexistente" '{"status":"in_progress"}'

    case_happy "DELETE" "/api/applications/1" "Eliminar aplicación"
    case_unauth "DELETE" "/api/applications/1" "Eliminar aplicación sin auth"
    case_notfound "DELETE" "/api/applications/99999" "Eliminar aplicación inexistente"

    case_happy "PATCH" "/api/applications/123/status" "Cambiar status aplicación" '{"status":"interview_scheduled"}'
    case_unauth "PATCH" "/api/applications/123/status" "Cambiar status sin auth"
    case_badreq "PATCH" "/api/applications/123/status" "Status inválido" '{"status":"invalid_status"}'
    case_notfound "PATCH" "/api/applications/99999/status" "Cambiar status aplicación inexistente" '{"status":"pending"}'

    case_happy "GET" "/api/applications/table-data" "Datos tabla aplicaciones"
    case_unauth "GET" "/api/applications/table-data" "Datos tabla sin auth"

    case_created "POST" "/api/applications/save-partial" "Guardar aplicación parcial" '{"candidate_id":"cnd-123","step":1,"data":{}}'
    case_unauth "POST" "/api/applications/save-partial" "Guardar parcial sin auth"
    case_badreq "POST" "/api/applications/save-partial" "Guardar parcial datos inválidos"

    # === AUTENTICACIÓN ===
    log "🔐 TESTING: AUTENTICACIÓN"
    case_happy "POST" "/api/auth/login" "Login usuario" '{"email":"test@example.com","password":"password123"}'
    case_badreq "POST" "/api/auth/login" "Login datos faltantes" '{"email":"","password":""}'
    case_unprocessable "POST" "/api/auth/login" "Login email inválido" '{"email":"invalid-email","password":"test"}'
    case_happy "POST" "/api/auth/login" "Login credenciales incorrectas" '{"email":"wrong@example.com","password":"wrongpass"}' # puede devolver 401 según tu back

    case_created "POST" "/api/auth/candidate-login" "Login candidato" '{"email":"candidate@example.com","password":"password123"}'
    case_created "POST" "/api/auth/staff-login" "Login staff" '{"email":"staff@example.com","password":"password123"}'
    case_created "POST" "/api/auth/social-login" "Login social" '{"provider":"google","token":"fake-token"}'

    case_happy "GET" "/api/auth/me" "Perfil usuario autenticado"
    case_unauth "GET" "/api/auth/me" "Perfil sin autenticación"

    case_happy "POST" "/api/auth/refresh" "Refresh token" '{"refresh_token":"fake-refresh-token"}'
    case_badreq "POST" "/api/auth/refresh" "Refresh token inválido" '{"refresh_token":""}'

    case_happy "POST" "/api/auth/set-cookie" "Configurar cookie" '{"token":"fake-jwt-token"}'
    case_happy "POST" "/api/auth/remove-cookie" "Remover cookie"
    case_happy "GET" "/api/auth/check-session" "Verificar sesión"
    case_happy "GET" "/api/auth/verify-session" "Verificar sesión GET"
    case_happy "POST" "/api/auth/verify-session" "Verificar sesión POST" '{"token":"fake-token"}'

    # Legacy auth endpoint
    case_happy "POST" "/auth.php" "Login legacy" '{"email":"test@example.com","password":"password123"}'

    # === CANDIDATOS ===
    log "👥 TESTING: CANDIDATOS"
    case_happy "GET" "/api/candidates" "Lista candidatos"
    case_unauth "GET" "/api/candidates" "Lista candidatos sin auth"
    case_badreq "GET" "/api/candidates" "Lista candidatos parámetros inválidos"

    case_created "POST" "/api/candidates/register" "Registrar candidato" '{"email":"new@example.com","password":"password123","first_name":"John","last_name":"Doe"}'
    case_badreq "POST" "/api/candidates/register" "Registrar candidato email duplicado" '{"email":"existing@example.com","password":"test","first_name":"John","last_name":"Doe"}'
    case_unprocessable "POST" "/api/candidates/register" "Registrar candidato datos inválidos" '{"email":"invalid-email","password":"123"}'

    case_happy "GET" "/api/candidates/1" "Ver candidato específico"
    case_unauth "GET" "/api/candidates/1" "Ver candidato sin auth"
    case_notfound "GET" "/api/candidates/99999" "Ver candidato inexistente"

    case_happy "PUT" "/api/candidates/1" "Actualizar candidato" '{"first_name":"John Updated","email":"updated@example.com"}'
    case_unauth "PUT" "/api/candidates/1" "Actualizar candidato sin auth"
    case_notfound "PUT" "/api/candidates/99999" "Actualizar candidato inexistente" '{"first_name":"Test"}'
    case_badreq "PUT" "/api/candidates/1" "Actualizar candidato datos inválidos" '{"email":"invalid-email"}'

    case_happy "DELETE" "/api/candidates/1" "Eliminar candidato"
    case_unauth "DELETE" "/api/candidates/1" "Eliminar candidato sin auth"
    case_notfound "DELETE" "/api/candidates/99999" "Eliminar candidato inexistente"

    case_created "POST" "/api/candidates/upload-cv" "Subir CV candidato"
    case_unauth "POST" "/api/candidates/upload-cv" "Subir CV sin auth"
    case_badreq "POST" "/api/candidates/upload-cv" "Subir CV archivo inválido"

    case_happy "GET" "/api/candidates/profile/1" "Perfil candidato"
    case_unauth "GET" "/api/candidates/profile/1" "Perfil candidato sin auth"
    case_notfound "GET" "/api/candidates/profile/99999" "Perfil candidato inexistente"

    case_happy "PATCH" "/api/candidates/1/status" "Cambiar status candidato" '{"status":"active"}'
    case_unauth "PATCH" "/api/candidates/1/status" "Cambiar status sin auth"
    case_badreq "PATCH" "/api/candidates/1/status" "Status candidato inválido" '{"status":"invalid_status"}'

    # === RECRUITERS ===
    log "🎯 TESTING: RECRUITERS"
    case_happy "GET" "/api/recruiters/assigned-candidates" "Candidatos asignados a recruiter"
    case_unauth "GET" "/api/recruiters/assigned-candidates" "Candidatos asignados sin auth"

    # === TRABAJOS ===
    log "💼 TESTING: TRABAJOS"
    case_happy "GET" "/api/jobs" "Lista trabajos públicos"
    case_happy "GET" "/api/jobs?department=1&location=Madrid" "Lista trabajos con filtros"

    case_created "POST" "/api/jobs" "Crear trabajo" '{"title":"Software Developer","description":"Great job","department_id":1,"salary_min":30000,"salary_max":50000}'
    case_unauth "POST" "/api/jobs" "Crear trabajo sin auth"
    case_badreq "POST" "/api/jobs" "Crear trabajo datos inválidos" '{"title":"","description":""}'

    case_happy "GET" "/api/jobs/1" "Ver trabajo específico"
    case_notfound "GET" "/api/jobs/99999" "Ver trabajo inexistente"

    case_happy "PUT" "/api/jobs/1" "Actualizar trabajo" '{"title":"Updated Job Title","description":"Updated description"}'
    case_unauth "PUT" "/api/jobs/1" "Actualizar trabajo sin auth"
    case_notfound "PUT" "/api/jobs/99999" "Actualizar trabajo inexistente" '{"title":"Test"}'

    case_happy "DELETE" "/api/jobs/1" "Eliminar trabajo"
    case_unauth "DELETE" "/api/jobs/1" "Eliminar trabajo sin auth"
    case_notfound "DELETE" "/api/jobs/99999" "Eliminar trabajo inexistente"

    case_happy "GET" "/api/jobs/available" "Trabajos disponibles"
    case_happy "POST" "/api/jobs/search" "Buscar trabajos" '{"query":"developer","location":"Madrid","skills":["javascript","php"]}'
    case_badreq "POST" "/api/jobs/search" "Buscar trabajos query inválida" '{"query":""}'

    case_happy "GET" "/api/jobs/by-department/1" "Trabajos por departamento"
    case_notfound "GET" "/api/jobs/by-department/99999" "Trabajos por departamento inexistente"

    # === CATEGORÍAS DE TRABAJO ===
    log "📂 TESTING: CATEGORÍAS DE TRABAJO"
    case_happy "GET" "/api/job-categories" "Lista categorías trabajo"
    case_created "POST" "/api/job-categories" "Crear categoría" '{"name":"Technology","description":"Tech jobs"}'
    case_unauth "POST" "/api/job-categories" "Crear categoría sin auth"
    case_badreq "POST" "/api/job-categories" "Crear categoría datos inválidos" '{"name":""}'

    case_happy "GET" "/api/job-categories/1" "Ver categoría específica"
    case_notfound "GET" "/api/job-categories/99999" "Ver categoría inexistente"

    case_happy "PUT" "/api/job-categories/1" "Actualizar categoría" '{"name":"Updated Category","description":"Updated description"}'
    case_unauth "PUT" "/api/job-categories/1" "Actualizar categoría sin auth"
    case_notfound "PUT" "/api/job-categories/99999" "Actualizar categoría inexistente" '{"name":"Test"}'

    case_happy "DELETE" "/api/job-categories/1" "Eliminar categoría"
    case_unauth "DELETE" "/api/job-categories/1" "Eliminar categoría sin auth"
    case_notfound "DELETE" "/api/job-categories/99999" "Eliminar categoría inexistente"

    # === CHATBOT ===
    log "🤖 TESTING: CHATBOT"
    case_happy "GET" "/api/chatbot/data" "Datos chatbot"
    case_happy "GET" "/api/chatbot/node" "Nodo chatbot"
    case_happy "POST" "/api/chatbot/node" "Nodo chatbot POST" '{"node_id":"welcome","context":{}}'
    case_happy "POST" "/api/chatbot/interaction" "Interacción chatbot" '{"message":"Hello","user_id":"user-123","session_id":"session-456"}'
    case_badreq "POST" "/api/chatbot/interaction" "Interacción chatbot datos inválidos" '{"message":""}'
    case_happy "GET" "/api/chatbot/analytics" "Analytics chatbot"

    # === ANÁLISIS DE CV ===
    log "📄 TESTING: ANÁLISIS DE CV"
    case_created "POST" "/api/cv/analyze-file" "Analizar CV archivo"
    case_unauth "POST" "/api/cv/analyze-file" "Analizar CV sin auth"
    case_badreq "POST" "/api/cv/analyze-file" "Analizar CV archivo inválido"

    case_created "POST" "/api/cv/analyze-text" "Analizar CV texto" '{"text":"John Doe\nSoftware Developer\n5 years experience..."}'
    case_badreq "POST" "/api/cv/analyze-text" "Analizar CV texto vacío" '{"text":""}'

    case_created "POST" "/api/cv/extract-text" "Extraer texto CV"
    case_created "POST" "/api/cv/process" "Procesar CV" '{"cv_text":"CV content here","extract_skills":true,"calculate_matching":true}'

    # === INTELIGENCIA ARTIFICIAL ===
    log "🧠 TESTING: INTELIGENCIA ARTIFICIAL"
    case_created "POST" "/api/ai/parse-cv-file" "IA parsear CV archivo"
    case_unauth "POST" "/api/ai/parse-cv-file" "IA parsear CV sin auth"
    case_badreq "POST" "/api/ai/parse-cv-file" "IA parsear CV archivo inválido"

    case_created "POST" "/api/ai/parse-cv" "IA parsear CV texto" '{"text":"John Doe CV content..."}'
    case_badreq "POST" "/api/ai/parse-cv" "IA parsear CV texto vacío" '{"text":""}'

    case_created "POST" "/api/ai/analyze-pdf" "IA analizar PDF"
    case_created "POST" "/api/ai/calculate-matching" "IA calcular matching" '{"candidate_skills":["javascript","php"],"job_requirements":["javascript","mysql"]}'
    case_badreq "POST" "/api/ai/calculate-matching" "IA matching datos inválidos" '{"candidate_skills":[],"job_requirements":[]}'

    case_happy "POST" "/api/ai/chatbot" "IA chatbot" '{"message":"Hello","context":{"user_type":"candidate"}}'
    case_badreq "POST" "/api/ai/chatbot" "IA chatbot mensaje vacío" '{"message":"","context":{}}'

    case_happy "GET" "/api/ai/health" "IA health check"
    case_created "POST" "/api/ai/analyze-personality" "IA análisis personalidad" '{"responses":[{"question":"q1","answer":"a1"}]}'
    case_created "POST" "/api/ai/predict-performance" "IA predicción rendimiento" '{"candidate_data":{"experience":5,"skills":["js","php"]},"job_requirements":{"experience_min":3}}'

    # === IDIOMAS ===
    log "🌐 TESTING: IDIOMAS"
    case_happy "GET" "/api/language" "Obtener idioma actual"
    case_happy "POST" "/api/language" "Configurar idioma" '{"language":"es","country":"ES"}'
    case_badreq "POST" "/api/language" "Configurar idioma inválido" '{"language":"invalid","country":"XX"}'

    # === PROCESAMIENTO DE PDFs ===
    log "📑 TESTING: PDFs"
    case_created "POST" "/api/pdf/parse" "Parsear PDF"
    case_unauth "POST" "/api/pdf/parse" "Parsear PDF sin auth"
    case_badreq "POST" "/api/pdf/parse" "Parsear PDF archivo inválido"

    # === USUARIOS ===
    log "👤 TESTING: USUARIOS"
    case_happy "GET" "/api/users" "Lista usuarios"
    case_unauth "GET" "/api/users" "Lista usuarios sin auth"

    case_created "POST" "/api/users" "Crear usuario" '{"email":"newuser@example.com","password":"password123","role":"staff","first_name":"New","last_name":"User"}'
    case_unauth "POST" "/api/users" "Crear usuario sin auth"
    case_badreq "POST" "/api/users" "Crear usuario datos inválidos" '{"email":"invalid-email","password":"123"}'

    case_happy "GET" "/api/users/1" "Ver usuario específico"
    case_unauth "GET" "/api/users/1" "Ver usuario sin auth"
    case_notfound "GET" "/api/users/99999" "Ver usuario inexistente"

    case_happy "PUT" "/api/users/1" "Actualizar usuario" '{"first_name":"Updated Name","email":"updated@example.com"}'
    case_unauth "PUT" "/api/users/1" "Actualizar usuario sin auth"
    case_notfound "PUT" "/api/users/99999" "Actualizar usuario inexistente" '{"first_name":"Test"}'

    case_happy "DELETE" "/api/users/1" "Eliminar usuario"
    case_unauth "DELETE" "/api/users/1" "Eliminar usuario sin auth"
    case_notfound "DELETE" "/api/users/99999" "Eliminar usuario inexistente"

    # === NOTIFICACIONES ===
    log "🔔 TESTING: NOTIFICACIONES"
    case_happy "GET" "/api/notifications" "Lista notificaciones"
    case_unauth "GET" "/api/notifications" "Lista notificaciones sin auth"

    case_created "POST" "/api/notifications" "Crear notificación" '{"candidate_id":"cnd-123","message":"Test notification","type":"info"}'
    case_unauth "POST" "/api/notifications" "Crear notificación sin auth"
    case_badreq "POST" "/api/notifications" "Crear notificación datos inválidos" '{"candidate_id":"","message":"","type":""}'

    case_happy "PATCH" "/api/notifications/1/read" "Marcar notificación como leída"
    case_unauth "PATCH" "/api/notifications/1/read" "Marcar como leída sin auth"
    case_notfound "PATCH" "/api/notifications/99999/read" "Marcar inexistente como leída"

    case_happy "DELETE" "/api/notifications/1" "Eliminar notificación"
    case_unauth "DELETE" "/api/notifications/1" "Eliminar notificación sin auth"
    case_notfound "DELETE" "/api/notifications/99999" "Eliminar notificación inexistente"

    # === ADMINISTRACIÓN ===
    log "⚙️ TESTING: ADMINISTRACIÓN"
    case_happy "GET" "/api/admin/dashboard" "Dashboard admin"
    case_unauth "GET" "/api/admin/dashboard" "Dashboard admin sin auth"
    case_forbidden "GET" "/api/admin/dashboard" "Dashboard admin sin permisos"

    case_happy "GET" "/api/admin/stats" "Estadísticas admin"
    case_unauth "GET" "/api/admin/stats" "Estadísticas admin sin auth"

    case_happy "POST" "/api/admin/bulk-actions" "Acciones masivas admin" '{"action":"activate","target_ids":[1,2,3],"target_type":"candidates"}'
    case_unauth "POST" "/api/admin/bulk-actions" "Acciones masivas sin auth"
    case_badreq "POST" "/api/admin/bulk-actions" "Acciones masivas datos inválidos" '{"action":"","target_ids":[]}'

    # === ARCHIVOS Y UPLOADS ===
    log "📁 TESTING: ARCHIVOS"
    case_created "POST" "/api/upload" "Subir archivo"
    case_unauth "POST" "/api/upload" "Subir archivo sin auth"
    case_badreq "POST" "/api/upload" "Subir archivo inválido"

    case_happy "GET" "/api/files/test/document.pdf" "Servir archivo"
    case_notfound "GET" "/api/files/nonexistent/file.pdf" "Servir archivo inexistente"

    case_happy "DELETE" "/api/files/test/document.pdf" "Eliminar archivo"
    case_unauth "DELETE" "/api/files/test/document.pdf" "Eliminar archivo sin auth"
    case_notfound "DELETE" "/api/files/nonexistent/file.pdf" "Eliminar archivo inexistente"

    # === RUTAS DE DESARROLLO (solo si APP_ENV=development) ===
    log "🧪 TESTING: RUTAS DE DESARROLLO"
    case_happy "GET" "/api/health" "Health check"
    case_happy "GET" "/api/test" "Test básico"
    case_happy "GET" "/test.php" "Test legacy"

    # === CORS PREFLIGHT ===
    log "🌐 TESTING: CORS"
    hit "OPTIONS" "/api/applications" "204" "CORS Preflight applications"
    hit "OPTIONS" "/api/auth/login" "204" "CORS Preflight auth"
    hit "OPTIONS" "/test-endpoint" "204" "CORS Preflight general"

    # =================================================================================
    # ENDPOINTS LEGACY - CRÍTICOS PARA MIGRACIÓN
    # =================================================================================
    log "🏚️ TESTING: ENDPOINTS LEGACY"

    case_happy "POST" "/api/candidates/save_v2.php" "Legacy guardar candidato v2" '{"nombre":"John","apellido":"Doe","email":"john@example.com","telefono":"123456789"}'
    case_badreq "POST" "/api/candidates/save_v2.php" "Legacy save v2 datos inválidos" '{"nombre":"","email":"invalid-email"}'

    case_happy "GET" "/api/get-notification-preferences.php" "Legacy obtener preferencias notificaciones"
    case_unauth "GET" "/api/get-notification-preferences.php" "Legacy preferencias sin auth"

    case_happy "POST" "/api/save-notification-preferences.php" "Legacy guardar preferencias" '{"applicationUpdates":true,"newJobs":false,"reminders":true}'
    case_unauth "POST" "/api/save-notification-preferences.php" "Legacy guardar preferencias sin auth"
    case_badreq "POST" "/api/save-notification-preferences.php" "Legacy preferencias datos inválidos" '{"invalid":"data"}'

    case_happy "GET" "/api/candidate-experiences.php?candidate_id=cnd-123" "Legacy experiencias candidato"
    case_unauth "GET" "/api/candidate-experiences.php?candidate_id=cnd-123" "Legacy experiencias sin auth"
    case_badreq "GET" "/api/candidate-experiences.php" "Legacy experiencias sin candidate_id"

    case_happy "GET" "/api/candidate-notifications.php" "Legacy notificaciones candidato"
    case_unauth "GET" "/api/candidate-notifications.php" "Legacy notificaciones sin auth"

    case_happy "POST" "/api/route.php" "Legacy ruteo candidato" '{"candidate_id":"cnd-123","candidate_data":{"skills":["javascript","php"]},"routing_rules":{"prefer_department_by_skills":true},"feature_flags":{"auto_routing_enabled":true}}'
    case_badreq "POST" "/api/route.php" "Legacy ruteo datos inválidos" '{"candidate_id":"","candidate_data":{}}'
    case_method_not_allowed "GET" "/api/route.php" "Legacy ruteo método no permitido"

    # =================================================================================
    # ENDPOINTS CRÍTICOS FALTANTES - DEBEN FALLAR CON 404
    # =================================================================================
    log "❌ TESTING: ENDPOINTS CRÍTICOS FALTANTES (DEBEN FALLAR)"
    case_notfound "GET" "/api/users/check-email?email=test@example.com" "Validación email faltante"
    case_notfound "GET" "/api/users/check-username?username=testuser" "Validación username faltante"
    case_notfound "POST" "/api/web-vitals" "Web vitals endpoint faltante"
    case_notfound "POST" "/api/csp-violation" "CSP violation endpoint faltante"
    case_notfound "GET" "/api/hr/dashboard-stats" "HR dashboard stats faltante"
    case_notfound "GET" "/api/recruiter/123/dashboard-stats" "Recruiter dashboard stats faltante"
    case_notfound "POST" "/api/files/upload-cv" "Upload CV endpoint faltante"
    case_notfound "POST" "/api/calendar/connect" "Calendar connect faltante"
    case_notfound "POST" "/api/calendar/schedule-interview" "Schedule interview faltante"
    case_notfound "POST" "/api/assign-candidate" "Assign candidate faltante"

    # =================================================================================
    # ARCHIVOS DE TEST EXPUESTOS - DEBEN SER PROTEGIDOS O ELIMINADOS
    # =================================================================================
    log "⚠️ TESTING: ARCHIVOS DE TEST EXPUESTOS (RIESGO DE SEGURIDAD)"
    hit "GET" "/test_mistral_simple.php" "404" "Test Mistral simple (DEBE SER ELIMINADO)"
    hit "GET" "/test_mistral_extended.php" "404" "Test Mistral extended (DEBE SER ELIMINADO)"
    hit "GET" "/test_full_cv.php" "404" "Test full CV (DEBE SER ELIMINADO)"
    hit "GET" "/test_paso2.php" "404" "Test paso 2 (DEBE SER ELIMINADO)"
    hit "GET" "/test_final.php" "404" "Test final (DEBE SER ELIMINADO)"
    hit "GET" "/test_complete_system.php" "404" "Test complete system (DEBE SER ELIMINADO)"
    hit "GET" "/debug_prompt.php" "404" "Debug prompt (DEBE SER PROTEGIDO)"
    hit "GET" "/diagnose_speed.php" "404" "Diagnose speed (DEBE SER PROTEGIDO)"
    hit "GET" "/cv-schema-test.php" "404" "CV schema test (DEBE SER ELIMINADO)"
    hit "GET" "/test-debug.php" "404" "Test debug (DEBE SER ELIMINADO)"
    hit "GET" "/test-endpoints.php" "404" "Test endpoints (DEBE SER ELIMINADO)"

    # =================================================================================
    # RESUMEN FINAL
    # =================================================================================
    echo ""
    log "📊 RESUMEN DE SMOKE TEST COMPLETO"
    echo "=================================================="
    echo -e "🎯 ${BLUE}TOTAL TESTS:${NC}    $TOTAL_TESTS"
    echo -e "✅ ${GREEN}PASSED:${NC}        $PASSED_TESTS"
    echo -e "❌ ${RED}FAILED:${NC}        $FAILED_TESTS"
    echo -e "⚠️ ${YELLOW}WARNINGS:${NC}      $WARNINGS (tiempo > 0.8s)"
    echo "=================================================="

    # Cálculos sin 'bc'
    local pass_rate
    local fail_rate
    pass_rate=$(awk "BEGIN {printf \"%.2f\", ($PASSED_TESTS * 100) / $TOTAL_TESTS}")
    fail_rate=$(awk "BEGIN {printf \"%.2f\", ($FAILED_TESTS * 100) / $TOTAL_TESTS}")

    echo -e "📈 ${BLUE}PASS RATE:${NC}     ${pass_rate}%"
    echo -e "📉 ${RED}FAIL RATE:${NC}     ${fail_rate}%"

    if [[ $FAILED_TESTS -eq 0 ]]; then
        echo -e "\n🎉 ${GREEN}TODOS LOS TESTS PASARON!${NC}"
        exit 0
    else
        echo -e "\n💥 ${RED}HAY TESTS FALLIDOS - REVISAR INMEDIATAMENTE${NC}"
        exit 1
    fi
}

# === VALIDACIONES INICIALES ===
if ! command -v curl &> /dev/null; then
    echo -e "${RED}❌ ERROR: curl no está instalado${NC}"
    exit 1
fi
# (Eliminado el chequeo de 'bc')

# === EJECUTAR MAIN ===
main "$@"
