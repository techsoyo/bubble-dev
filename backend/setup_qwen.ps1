# Script de configuración para QwenApiService (PowerShell)
# Configura las variables de entorno necesarias para usar Qwen API

Write-Host "=== CONFIGURACIÓN QWEN API SERVICE ===" -ForegroundColor Cyan
Write-Host ""

# Verificar si ya existe configuración
if ($env:QWEN_API_KEY) {
  $maskedKey = $env:QWEN_API_KEY.Substring(0, [Math]::Min(8, $env:QWEN_API_KEY.Length)) + "..."
  Write-Host "✅ QWEN_API_KEY ya configurada: $maskedKey" -ForegroundColor Green
}
else {
  Write-Host "❌ QWEN_API_KEY no configurada" -ForegroundColor Red
  Write-Host ""
  Write-Host "Para obtener tu API Key:"
  Write-Host "1. Regístrate en https://www.alibabacloud.com/"
  Write-Host "2. Ve a Model Studio: https://bailian.console.aliyun.com/"
  Write-Host "3. Crea una API Key en configuración"
  Write-Host "4. Copia la API Key (sk-xxxxx...)"
  Write-Host ""
    
  $apiKey = Read-Host "Introduce tu QWEN_API_KEY"
    
  if ([string]::IsNullOrEmpty($apiKey)) {
    Write-Host "❌ API Key requerida" -ForegroundColor Red
    exit 1
  }
    
  $env:QWEN_API_KEY = $apiKey
  Write-Host "✅ QWEN_API_KEY configurada" -ForegroundColor Green
}

# Configurar otras variables
$env:QWEN_BASE_URL = "https://dashscope.aliyuncs.com/compatible-mode/v1"
$env:QWEN_MODEL = "qwen-plus"
$env:QWEN_TIMEOUT_SECONDS = "180"
$env:AI_MAX_RETRIES = "3"

Write-Host ""
Write-Host "📋 Configuración aplicada:" -ForegroundColor Yellow
$maskedKey = $env:QWEN_API_KEY.Substring(0, [Math]::Min(8, $env:QWEN_API_KEY.Length)) + "..."
Write-Host "• QWEN_API_KEY: $maskedKey"
Write-Host "• QWEN_BASE_URL: $($env:QWEN_BASE_URL)"
Write-Host "• QWEN_MODEL: $($env:QWEN_MODEL)"
Write-Host "• QWEN_TIMEOUT_SECONDS: $($env:QWEN_TIMEOUT_SECONDS)"
Write-Host "• AI_MAX_RETRIES: $($env:AI_MAX_RETRIES)"

Write-Host ""
Write-Host "🚀 Ejecutando verificación..." -ForegroundColor Cyan
php verify_qwen_setup.php

Write-Host ""
Write-Host "💡 Para hacer permanente esta configuración:" -ForegroundColor Yellow
Write-Host "Agrega las siguientes líneas a tu perfil de PowerShell:"
Write-Host ""
Write-Host "`$env:QWEN_API_KEY = `"$($env:QWEN_API_KEY)`"" -ForegroundColor Gray
Write-Host "`$env:QWEN_BASE_URL = `"$($env:QWEN_BASE_URL)`"" -ForegroundColor Gray
Write-Host "`$env:QWEN_MODEL = `"$($env:QWEN_MODEL)`"" -ForegroundColor Gray

Write-Host ""
Write-Host "📍 Ubicación del perfil: $PROFILE" -ForegroundColor Gray
