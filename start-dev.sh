#!/bin/bash
# Script de inicio para desarrollo - Bubble of Talents

echo "🚀 Iniciando Bubble of Talents en modo desarrollo..."

# Verificar que estamos en el directorio correcto
if [ ! -f "package.json" ]; then
    echo "❌ Error: Este script debe ejecutarse desde la raíz del proyecto"
    exit 1
fi

echo ""
echo "📋 Verificando estado del proyecto..."

# Verificar backend
if [ -d "backend/vendor" ]; then
    echo "✅ Backend: Dependencias instaladas"
else
    echo "❌ Backend: Ejecuta 'composer install' en /backend"
    exit 1
fi

# Verificar frontend
if [ -d "frontend/node_modules" ]; then
    echo "✅ Frontend: Dependencias instaladas"
else
    echo "❌ Frontend: Ejecuta 'pnpm install' en /frontend"
    exit 1
fi

echo ""
echo "🌐 Iniciando servidores..."
echo ""

# Iniciar backend en segundo plano
echo "🔧 Iniciando backend en http://localhost:8000..."
cd backend
php -S localhost:8000 public/index.php &
BACKEND_PID=$!
cd ..

# Esperar un momento para que el backend inicie
sleep 2

# Verificar que el backend esté funcionando
if curl -s http://localhost:8000/api/test.php > /dev/null; then
    echo "✅ Backend iniciado correctamente"
else
    echo "⚠️  Backend iniciado pero puede tener problemas"
fi

# Iniciar frontend
echo ""
echo "⚛️  Iniciando frontend en http://localhost:3002..."
cd frontend
pnpm dev &
FRONTEND_PID=$!
cd ..

echo ""
echo "🎉 ¡Servidores iniciados!"
echo ""
echo "📱 URLs disponibles:"
echo "   Frontend: http://localhost:3002"
echo "   Backend:  http://localhost:8000"
echo "   API Test: http://localhost:8000/api/test.php"
echo ""
echo "⏹️  Para detener los servidores, presiona Ctrl+C"
echo ""

# Función para limpiar procesos al salir
cleanup() {
    echo ""
    echo "🛑 Deteniendo servidores..."
    kill $BACKEND_PID 2>/dev/null
    kill $FRONTEND_PID 2>/dev/null
    echo "✅ Servidores detenidos"
    exit 0
}

# Capturar Ctrl+C
trap cleanup INT

# Mantener el script corriendo
wait
