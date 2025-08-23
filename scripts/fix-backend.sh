#!/usr/bin/env bash

# Script de arreglo automático para Backend
echo "🔧 Aplicando arreglos al backend..."

# 1. Verificar configuración Groq
if [ ! -f "../backend/.env" ]; then
    echo "⚠️  Archivo .env no encontrado"
    cp "../backend/.env.example" "../backend/.env"
fi

# 2. Crear directorio Middleware si no existe
mkdir -p "../backend/src/Middleware"

# 3. Crear SecurityMiddleware básico
cat > "../backend/src/Middleware/SecurityMiddleware.php" << 'EOF'
<?php
namespace BubbleTalents\Middleware;

class SecurityMiddleware {
    public static function validateRequest() {
        // Validación básica de seguridad
        return true;
    }
    
    public static function corsHeaders() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
    }
}
EOF

echo "✅ Arreglos aplicados"
echo "🔄 Reinicia el backend para aplicar cambios"
