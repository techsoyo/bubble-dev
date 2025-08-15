#!/bin/bash

# Setup script para Codespaces - Bubble of Talents IA Testing
echo "🚀 Configurando entorno IA en Codespaces..."

# Verificar GPU
echo "🔍 Verificando GPU disponible..."
if command -v nvidia-smi &> /dev/null; then
    nvidia-smi
    echo "✅ GPU detectada"
else
    echo "⚠️ GPU no detectada - continuando con CPU"
fi

# Crear directorios necesarios
mkdir -p /workspaces/bubble_of_talents_1.0/ai_models
mkdir -p /workspaces/bubble_of_talents_1.0/backend/uploads
mkdir -p /workspaces/bubble_of_talents_1.0/logs

# Configurar permisos
chmod 755 /workspaces/bubble_of_talents_1.0/backend/uploads
chmod 755 /workspaces/bubble_of_talents_1.0/logs

# Variables de entorno para Codespaces
echo "📝 Configurando variables de entorno..."
echo "export CODESPACE_AI_ENABLED=true" >> ~/.bashrc
echo "export PYTORCH_CUDA_ALLOC_CONF=max_split_size_mb:512" >> ~/.bashrc
echo "export TRANSFORMERS_CACHE=/workspaces/bubble_of_talents_1.0/ai_models" >> ~/.bashrc

echo "✅ Setup completado - reiniciar terminal o ejecutar: source ~/.bashrc"
