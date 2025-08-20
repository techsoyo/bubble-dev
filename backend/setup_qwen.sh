#!/bin/bash

# Script de configuración para QwenApiService
# Configura las variables de entorno necesarias para usar Qwen API

echo "=== CONFIGURACIÓN QWEN API SERVICE ==="
echo ""

# Verificar si ya existe configuración
if [ ! -z "$QWEN_API_KEY" ]; then
    echo "✅ QWEN_API_KEY ya configurada: ${QWEN_API_KEY:0:8}..."
else
    echo "❌ QWEN_API_KEY no configurada"
    echo ""
    echo "Para obtener tu API Key:"
    echo "1. Regístrate en https://www.alibabacloud.com/"
    echo "2. Ve a Model Studio: https://bailian.console.aliyun.com/"
    echo "3. Crea una API Key en configuración"
    echo "4. Copia la API Key (sk-xxxxx...)"
    echo ""
    read -p "Introduce tu QWEN_API_KEY: " api_key
    
    if [ -z "$api_key" ]; then
        echo "❌ API Key requerida"
        exit 1
    fi
    
    export QWEN_API_KEY="$api_key"
    echo "✅ QWEN_API_KEY configurada"
fi

# Configurar otras variables
export QWEN_BASE_URL="https://dashscope.aliyuncs.com/compatible-mode/v1"
export QWEN_MODEL="qwen-plus"
export QWEN_TIMEOUT_SECONDS="180"
export AI_MAX_RETRIES="3"

echo ""
echo "📋 Configuración aplicada:"
echo "• QWEN_API_KEY: ${QWEN_API_KEY:0:8}..."
echo "• QWEN_BASE_URL: $QWEN_BASE_URL"
echo "• QWEN_MODEL: $QWEN_MODEL"
echo "• QWEN_TIMEOUT_SECONDS: $QWEN_TIMEOUT_SECONDS"
echo "• AI_MAX_RETRIES: $AI_MAX_RETRIES"

echo ""
echo "🚀 Ejecutando verificación..."
php verify_qwen_setup.php

echo ""
echo "💡 Para hacer permanente esta configuración:"
echo "Agrega las siguientes líneas a tu ~/.bashrc o ~/.zshrc:"
echo ""
echo "export QWEN_API_KEY=\"$QWEN_API_KEY\""
echo "export QWEN_BASE_URL=\"$QWEN_BASE_URL\""
echo "export QWEN_MODEL=\"$QWEN_MODEL\""
