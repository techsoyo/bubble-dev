@echo off
REM Script para arrancar el servidor de desarrollo PHP
REM Uso: start-server.bat [puerto]

set PORT=%1
if "%PORT%"=="" set PORT=8000

set HOST=localhost

echo.
echo 🚀 Iniciando servidor PHP de desarrollo...
echo 📍 Host: %HOST%
echo 🔌 Puerto: %PORT%
echo 🎯 Router: router.php  
echo 🌐 URL: http://%HOST%:%PORT%
echo.
echo ✅ Para probar la API:
echo    curl http://%HOST%:%PORT%/api/health
echo.
echo 🛑 Para detener: Ctrl+C
echo ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo.

REM Verificar que existe router.php
if not exist "router.php" (
    echo ❌ Error: router.php no encontrado
    pause
    exit /b 1
)

REM Verificar que existe vendor/autoload.php  
if not exist "vendor\autoload.php" (
    echo ❌ Error: vendor\autoload.php no encontrado
    echo 💡 Ejecuta: composer install
    pause
    exit /b 1
)

REM Cambiar al directorio del backend
cd /d "C:\laragon\www\bubble_of_talents_1.0\backend"
echo Directorio actual: %cd%

REM Arrancar servidor
echo 🔄 Arrancando servidor PHP embebido...
REM -t public especifica la carpeta raíz para archivos estáticos  
REM router.php maneja las rutas REST desde el directorio backend
php -S %HOST%:%PORT% -t public router.php
