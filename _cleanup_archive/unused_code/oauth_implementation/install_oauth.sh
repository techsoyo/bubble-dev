#!/bin/bash

# 🛠️ Script de Instalación OAuth - Bash (Linux/Mac)
# Ejecutar desde la raíz del proyecto: ./oauth_implementation/install_oauth.sh

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Parámetros
SOURCE_PATH="./oauth_implementation"
BACKEND_PATH="./backend"
FRONTEND_PATH="./frontend/src/pages/auth"
FORCE=false

# Procesar argumentos
while [[ $# -gt 0 ]]; do
    case $1 in
        --force)
            FORCE=true
            shift
            ;;
        *)
            echo -e "${RED}❌ Argumento desconocido: $1${NC}"
            exit 1
            ;;
    esac
done

echo -e "${CYAN}🚀 INSTALADOR OAUTH - BUBBLE OF TALENTS${NC}"
echo -e "${CYAN}=====================================${NC}"

# Verificar que estamos en el directorio correcto
if [ ! -f "package.json" ]; then
    echo -e "${RED}❌ Error: Ejecuta este script desde la raíz del proyecto (donde está package.json)${NC}"
    exit 1
fi

# Verificar que existe la carpeta oauth_implementation
if [ ! -d "$SOURCE_PATH" ]; then
    echo -e "${RED}❌ Error: No se encuentra la carpeta oauth_implementation${NC}"
    exit 1
fi

echo -e "${YELLOW}📁 Verificando estructura de archivos...${NC}"

# Crear directorios si no existen
dirs=(
    "$BACKEND_PATH/auth"
    "$BACKEND_PATH/auth/oauth"
    "$BACKEND_PATH/auth/google"
    "$BACKEND_PATH/auth/linkedin"
)

for dir in "${dirs[@]}"; do
    if [ ! -d "$dir" ]; then
        echo -e "   Creando directorio: $dir"
        mkdir -p "$dir"
    fi
done

echo -e "${GREEN}✅ Directorios verificados/creados${NC}"

# Función para copiar archivo con confirmación
copy_with_confirmation() {
    local source="$1"
    local destination="$2"
    local description="$3"
    
    if [ -f "$destination" ] && [ "$FORCE" = false ]; then
        echo -e "${YELLOW}⚠️  $description ya existe. ¿Sobrescribir? (y/N)${NC}"
        read -r response
        if [[ ! "$response" =~ ^[Yy]$ ]]; then
            echo -e "   Saltando: $description"
            return
        fi
    fi
    
    if cp "$source" "$destination" 2>/dev/null; then
        echo -e "${GREEN}✅ Copiado: $description${NC}"
    else
        echo -e "${RED}❌ Error copiando $description${NC}"
    fi
}

echo -e "${YELLOW}📋 Instalando archivos backend...${NC}"

# Copiar archivos backend
copy_with_confirmation "$SOURCE_PATH/backend/auth/OAuthHandler.php" "$BACKEND_PATH/auth/OAuthHandler.php" "OAuthHandler.php"
copy_with_confirmation "$SOURCE_PATH/backend/auth/oauth/start.php" "$BACKEND_PATH/auth/oauth/start.php" "OAuth start endpoint"
copy_with_confirmation "$SOURCE_PATH/backend/auth/google/callback.php" "$BACKEND_PATH/auth/google/callback.php" "Google callback"
copy_with_confirmation "$SOURCE_PATH/backend/auth/linkedin/callback.php" "$BACKEND_PATH/auth/linkedin/callback.php" "LinkedIn callback"

echo -e "${YELLOW}🎨 Instalando archivos frontend...${NC}"

# Copiar archivo frontend
copy_with_confirmation "$SOURCE_PATH/frontend/CandidateAuthPage.tsx" "$FRONTEND_PATH/CandidateAuthPage.tsx" "CandidateAuthPage.tsx con OAuth"

echo -e "${YELLOW}⚙️  Instalando configuración...${NC}"

# Copiar configuración si no existe
if [ ! -f "$BACKEND_PATH/.env.oauth" ]; then
    copy_with_confirmation "$SOURCE_PATH/config/.env.oauth.example" "$BACKEND_PATH/.env.oauth.example" "Template configuración OAuth"
    echo -e "${CYAN}📝 Copia .env.oauth.example a .env.oauth y completa las credenciales${NC}"
else
    echo -e "${GREEN}✅ Archivo .env.oauth ya existe${NC}"
fi

# Copiar herramienta de debug
copy_with_confirmation "$SOURCE_PATH/../debug_oauth.php" "$BACKEND_PATH/debug_oauth.php" "Herramienta de debug"

echo ""
echo -e "${GREEN}🎉 INSTALACIÓN COMPLETADA!${NC}"
echo -e "${GREEN}=========================${NC}"
echo ""
echo -e "${CYAN}📋 PRÓXIMOS PASOS:${NC}"
echo -e "1. 📖 Lee la guía: ./oauth_implementation/docs/OAUTH_SETUP_GUIDE.md"
echo -e "2. ⚙️  Configura Google Cloud Console y LinkedIn Developers"
echo -e "3. 📝 Completa el archivo: ./backend/.env.oauth"
echo -e "4. 🧪 Verifica la instalación: http://localhost:8000/debug_oauth.php"
echo -e "5. 🎮 Prueba los botones OAuth en: http://localhost:3002/auth/register"
echo ""
echo -e "${YELLOW}⏱️  Tiempo estimado de configuración: 30 minutos${NC}"
echo ""
echo -e "🆘 ¿Necesitas ayuda? Revisa: ./oauth_implementation/docs/OAUTH_TECHNICAL_CHECKLIST.md"

# Mostrar archivos instalados
echo ""
echo -e "${CYAN}📦 ARCHIVOS INSTALADOS:${NC}"
find "$BACKEND_PATH/auth" -name "*.php" 2>/dev/null | sed 's|^\./|   ./|'
echo "   $FRONTEND_PATH/CandidateAuthPage.tsx"

# Hacer el script ejecutable
chmod +x "$0" 2>/dev/null || true
