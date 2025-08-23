#!/bin/bash

# Script para arrancar el servidor de desarrollo PHP
# Uso: ./start-server.sh [puerto]

PORT=${1:-8000}
HOST="localhost"

echo "🚀 Iniciando servidor PHP de desarrollo..."
echo "📍 Host: $HOST"
echo "🔌 Puerto: $PORT"
echo "🎯 Router: router.php"
echo "🌐 URL: http://$HOST:$PORT"
echo ""
echo "✅ Para probar la API:"
echo "   curl http://$HOST:$PORT/api/health"
echo ""
echo "🛑 Para detener: Ctrl+C"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Verificar que existe router.php
if [ ! -f "router.php" ]; then
    echo "❌ Error: router.php no encontrado"
    read -p "Presiona Enter para salir..."
    exit 1
fi

# Verificar que existe vendor/autoload.php
if [ ! -f "vendor/autoload.php" ]; then
    echo "❌ Error: vendor/autoload.php no encontrado"
    echo "💡 Ejecuta: composer install"
    read -p "Presiona Enter para salir..."
    exit 1
fi

# Hacer el script ejecutable
chmod +x start-server.sh

# Arrancar servidor
echo "🔄 Arrancando servidor PHP embebido..."
# -t public especifica la carpeta raíz para archivos estáticos
# router.php maneja las rutas REST desde el directorio backend
php -S "$HOST:$PORT" -t public router.php
