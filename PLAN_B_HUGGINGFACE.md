"""
Alternative: Hugging Face Spaces para testing IA
Si Codespaces no está disponible o no tiene GPU
"""

# 1. Crear Space en HuggingFace
# https://huggingface.co/spaces
# Seleccionar: Gradio + GPU

# 2. Subir ai_server_codespaces.py como app.py
# 3. Configurar requirements.txt:
torch
transformers
accelerate
gradio
requests

# 4. En backend/.env:
CODESPACES_AI_URL=https://tu-username-space-name.hf.space
AI_PROVIDER=codespaces
