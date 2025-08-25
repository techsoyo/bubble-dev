#!/bin/bash
# =============================================
# CI Gate - Anti-Mocks Script
# Fecha: 25 de agosto de 2025
# Propósito: Bloquear regresiones de mocks/hardcode en CI/CD
# =============================================

echo "🛡️  CI GATE: Verificando ausencia de mocks y hardcode..."
echo ""

EXIT_CODE=0

# 1. Backend: mocks/tokens/aleatorios en API pública
echo "🔍 Verificando endpoints públicos..."
if git grep -nI -E '\$mockData\b|mocked-jwt-token|rand\s*\(' -- 'backend/public/api/**' 2>/dev/null; then
    echo "❌ Mock/hardcode detectado en endpoints públicos"
    EXIT_CODE=1
else
    echo "✅ Endpoints públicos limpios"
fi

# 2. Backend general (por si quedan restos)
echo ""
echo "🔍 Verificando backend completo..."
if git grep -nI -E '\$mockData\b|mocked-jwt-token' -- 'backend/**' 2>/dev/null; then
    echo "❌ Mocks detectados en backend"
    EXIT_CODE=1
else
    echo "✅ Backend sin mocks"
fi

# 3. Verificar rand() específicamente en producción crítica
echo ""
echo "🔍 Verificando uso de rand() en controladores críticos..."
RAND_MATCHES=$(git grep -nI -E 'rand\s*\(' -- 'backend/src/Controllers/AIController.php' 2>/dev/null || true)
if [ ! -z "$RAND_MATCHES" ]; then
    echo "⚠️  rand() encontrado en AIController:"
    echo "$RAND_MATCHES"
    echo "✅ Verificar que esté protegido con APP_ENV check"
else
    echo "✅ Sin rand() no protegido"
fi

# 4. Frontend (opcional: permitir simulateProgress)
echo ""
echo "🔍 Revisando referencias mock en Frontend..."
if git grep -nI -E 'mockData|fake-|hardcode' -- 'frontend/**' 2>/dev/null; then
    echo "⚠️  Referencias mock en FE encontradas - revisar si son aceptables"
    # No falla el CI, solo advierte
else
    echo "✅ Frontend sin referencias mock problemáticas"
fi

# 5. Verificar que job_requirements y job_skills usen modelos
echo ""
echo "🔍 Verificando que job_* usen modelos BaseModel..."
if grep -l "JobRequirementModel\|JobSkillModel" backend/public/api/job_requirements.php backend/public/api/job_skills.php >/dev/null 2>&1; then
    echo "✅ job_* endpoints usan modelos"
else
    echo "❌ job_* endpoints no usan modelos BaseModel"
    EXIT_CODE=1
fi

# 6. Verificar ausencia de arrays hardcodeados en job_*
echo ""
echo "🔍 Verificando ausencia de arrays mock en job_*..."
if git grep -nI -E '\$mockData.*=.*\[' -- 'backend/public/api/job_requirements.php' 'backend/public/api/job_skills.php' 2>/dev/null; then
    echo "❌ Arrays mockData encontrados en job_* endpoints"
    EXIT_CODE=1
else
    echo "✅ job_* endpoints sin arrays mock"
fi

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "🎉 CI GATE PASSED: Proyecto libre de mocks críticos"
else
    echo "💥 CI GATE FAILED: Mocks/hardcode detectados - bloquear merge"
fi

exit $EXIT_CODE
