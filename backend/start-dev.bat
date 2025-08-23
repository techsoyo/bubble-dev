@echo off
REM Script para iniciar el servidor PHP de desarrollo con configuración CORS

echo ====================================
echo   BUBBLE TALENTS - Backend Server
echo ====================================
echo.

REM Verificar que existe el archivo .env
if not exist ".env" (
    echo [WARNING] Archivo .env no encontrado
    if exist ".env.development" (
        echo Copiando .env.development como .env...
        copy ".env.development" ".env" >nul
        echo ✅ Archivo .env creado desde .env.development
    ) else (
        echo ❌ No se encontró .env ni .env.development
        echo Por favor crea un archivo .env basado en .env.example
        pause
        exit /b 1
    )
) else (
    echo ✅ Archivo .env encontrado
)
echo.

REM Mostrar configuración CORS actual
echo [INFO] Configuración CORS actual:
if exist ".env" (
    findstr "CORS_ALLOWED_ORIGINS" .env 2>nul || echo   CORS_ALLOWED_ORIGINS: [no configurado]
    findstr "CORS_ALLOW_CREDENTIALS" .env 2>nul || echo   CORS_ALLOW_CREDENTIALS: [no configurado]
    findstr "APP_ENV" .env 2>nul || echo   APP_ENV: [no configurado]
)
echo.

REM Verificar puerto disponible
set PORT=8000
echo [INFO] Iniciando servidor PHP en puerto %PORT%...
echo [INFO] URL Backend: http://localhost:%PORT%
echo [INFO] Endpoints disponibles:
echo   - GET  /api/health
echo   - POST /api/candidates
echo   - GET  /api/jobs
echo.

REM Iniciar servidor
echo [INFO] Presiona Ctrl+C para detener el servidor
echo [INFO] Logs CORS visibles en development mode
echo.

php -S localhost:%PORT% -t . router.php
