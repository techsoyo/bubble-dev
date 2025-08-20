# Script de configuración para GroqApiService (PowerShell)
# Configura las variables de entorno necesarias para usar Groq API

Write-Host "=== CONFIGURACIÓN GROQ API SERVICE (GRATUITO) ===" -ForegroundColor Cyan
Write-Host ""

# Verificar si ya existe configuración
if ($env:GROQ_API_KEY) {
  $maskedKey = $env:GROQ_API_KEY.Substring(0, [Math]::Min(12, $env:GROQ_API_KEY.Length)) + "..."
  Write-Host "✅ GROQ_API_KEY ya configurada: $maskedKey" -ForegroundColor Green
}
else {
  Write-Host "❌ GROQ_API_KEY no configurada" -ForegroundColor Red
  Write-Host ""
  Write-Host "🎁 GROQ API ES COMPLETAMENTE GRATUITA 🎁" -ForegroundColor Yellow
  Write-Host ""
  Write-Host "Para obtener tu API Key GRATUITA:"
  Write-Host "1. Ve a: https://console.groq.com/" -ForegroundColor Cyan
  Write-Host "2. Haz clic en 'Sign Up' (registro gratuito)"
  Write-Host "3. Ve a 'API Keys' en el dashboard"
  Write-Host "4. Crea una nueva API Key"
  Write-Host "5. Copia la API Key (gsk_xxxxx...)"
  Write-Host ""
  Write-Host "💡 Groq ofrece:" -ForegroundColor Yellow
  Write-Host "   • API completamente gratuita"
  Write-Host "   • Velocidad ultra rápida (chips LPU)"
  Write-Host "   • Límites generosos para desarrollo"
  Write-Host "   • Modelos Llama3, Mixtral, Gemma"
  Write-Host ""
    
  $apiKey = Read-Host "Introduce tu GROQ_API_KEY"
    
  if ([string]::IsNullOrEmpty($apiKey)) {
    Write-Host "❌ API Key requerida" -ForegroundColor Red
    exit 1
  }
    
  $env:GROQ_API_KEY = $apiKey
  Write-Host "✅ GROQ_API_KEY configurada" -ForegroundColor Green
}

# Configurar otras variables con valores optimizados para Groq
$env:GROQ_BASE_URL = "https://api.groq.com/openai/v1"
$env:GROQ_MODEL = "llama3-8b-8192"  # Modelo rápido y eficiente
$env:GROQ_TIMEOUT_SECONDS = "120"   # Groq es muy rápido, menos timeout
$env:AI_MAX_RETRIES = "3"

Write-Host ""
Write-Host "📋 Configuración aplicada:" -ForegroundColor Yellow
$maskedKey = $env:GROQ_API_KEY.Substring(0, [Math]::Min(12, $env:GROQ_API_KEY.Length)) + "..."
Write-Host "• GROQ_API_KEY: $maskedKey"
Write-Host "• GROQ_BASE_URL: $($env:GROQ_BASE_URL)"
Write-Host "• GROQ_MODEL: $($env:GROQ_MODEL)"
Write-Host "• GROQ_TIMEOUT_SECONDS: $($env:GROQ_TIMEOUT_SECONDS)"
Write-Host "• AI_MAX_RETRIES: $($env:AI_MAX_RETRIES)"

Write-Host ""
Write-Host "🚀 Ejecutando verificación..." -ForegroundColor Cyan
php verify_groq_setup.php

Write-Host ""
Write-Host "💡 Para hacer permanente esta configuración:" -ForegroundColor Yellow
Write-Host "Agrega las siguientes líneas a tu perfil de PowerShell:"
Write-Host ""
Write-Host "`$env:GROQ_API_KEY = `"$($env:GROQ_API_KEY)`"" -ForegroundColor Gray
Write-Host "`$env:GROQ_BASE_URL = `"$($env:GROQ_BASE_URL)`"" -ForegroundColor Gray
Write-Host "`$env:GROQ_MODEL = `"$($env:GROQ_MODEL)`"" -ForegroundColor Gray

Write-Host ""
Write-Host "🎉 GROQ: Velocidad de IA sin costo 🎉" -ForegroundColor Green
Write-Host "📍 Ubicación del perfil: $PROFILE" -ForegroundColor Gray
