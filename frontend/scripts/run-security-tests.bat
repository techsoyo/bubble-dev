@echo off
echo 🚀 Running Security Tests Suite...
echo.

cd /d "%~dp0"

if exist "node_modules\.bin\node" (
    node src/tests/security.test.ts
) else (
    echo ❌ Node modules not found. Please run 'pnpm install' first.
    exit /b 1
)

echo.
echo ✅ Security tests completed!
pause
