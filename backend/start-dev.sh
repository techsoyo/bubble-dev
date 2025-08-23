#!/bin/bash

# Script para iniciar el servidor PHP de desarrollo con configuración CORS

echo "===================================="
echo "  BUBBLE TALENTS - Backend Server"
echo "===================================="
echo

# Verificar que existe el archivo .env
if [ ! -f ".env" ]; then
    echo "[WARNING] Archivo .env no encontrado"
    if [ -f ".env.development" ]; then
        echo "Copiando .env.development como .env..."
        cp ".env.development" ".env"
        echo "✅ Archivo .env creado desde .env.development"
    else
        echo "❌ No se encontró .env ni .env.development"
        echo "Por favor crea un archivo .env basado en .env.example"
        exit 1
    fi
else
    echo "✅ Archivo .env encontrado"
fi
echo

# Mostrar configuración CORS actual
echo "[INFO] Configuración CORS actual:"
if [ -f ".env" ]; then
    grep "CORS_ALLOWED_ORIGINS" .env 2>/dev/null || echo "  CORS_ALLOWED_ORIGINS: [no configurado]"
    grep "CORS_ALLOW_CREDENTIALS" .env 2>/dev/null || echo "  CORS_ALLOW_CREDENTIALS: [no configurado]"
    grep "APP_ENV" .env 2>/dev/null || echo "  APP_ENV: [no configurado]"
fi
echo

# Verificar puerto disponible
PORT=8000
echo "[INFO] Iniciando servidor PHP en puerto $PORT..."
echo "[INFO] URL Backend: http://localhost:$PORT"
echo "[INFO] Endpoints disponibles:"
echo "  - GET  /api/health"
echo "  - POST /api/candidates"
echo "  - GET  /api/jobs"
echo

# Iniciar servidor
echo "[INFO] Presiona Ctrl+C para detener el servidor"
echo "[INFO] Logs CORS visibles en development mode"
echo

php -S localhost:$PORT -t . router.php
