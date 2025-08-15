# 🚀 Bubble of Talents - Testing IA con GitHub Codespaces

## Descripción

Esta configuración te permite probar el sistema de IA de reclutamiento usando **GitHub Codespaces** con acceso a GPUs potentes, sin necesidad de configuración local compleja.

## ✅ Ventajas de Codespaces vs Colab

- **Integrado con VS Code**: Todo en el mismo entorno
- **GPU automática**: T4, V100 o A100 según disponibilidad  
- **Persistencia**: El entorno se mantiene entre sesiones
- **Sin setup manual**: devcontainer.json configura todo automáticamente
- **Networking directo**: No necesitas ngrok o túneles externos

## 🛠️ Setup Rápido

### 1. Abrir en Codespaces

```bash
# Desde GitHub.com:
# 1. Ir al repositorio
# 2. Click "Code" > "Codespaces" > "Create codespace"
# 3. Esperar ~3-5 minutos para setup inicial

# O desde VS Code:
# 1. Ctrl+Shift+P
# 2. "Codespaces: Create New Codespace"
# 3. Seleccionar el repositorio
```

### 2. Verificar GPU (automático)

El devcontainer incluye CUDA y drivers automáticamente. Verificar:

```bash
# En terminal de Codespaces:
nvidia-smi  # Ver GPU disponible
python -c "import torch; print(f'GPU: {torch.cuda.is_available()}')"
```

### 3. Iniciar Servidor IA

```bash
# Terminal 1: Servidor IA
python ai_server_codespaces.py

# Salida esperada:
# 🚀 Iniciando Bubble of Talents AI Server...
# 🔄 Inicializando modelo IA...
# GPU detectada: Tesla T4 (15.1GB)
# ✅ Modelo cargado exitosamente
# 🌐 Servidor iniciando en http://0.0.0.0:5000
```

### 4. Configurar Backend PHP

```bash
# Terminal 2: Configurar variables
cd backend
echo "AI_PROVIDER=codespaces" >> .env
echo "CODESPACES_AI_URL=http://localhost:5000" >> .env
```

### 5. Ejecutar Tests

```bash
# Terminal 2: Tests completos
php test_codespaces_complete.php

# Salida esperada:
# ✅ Servidor IA accesible (250ms)
# ✅ Matching completado en 1500ms
# 📊 Score general: 87/100
# 💡 Recomendación: HIRE
```

## 📊 Performance Esperado

| Configuración | Tiempo Matching | GPU | Modelo |
|---------------|----------------|-----|---------|
| T4 (15GB)     | 1-3 segundos   | ✅  | DialoGPT-large |
| V100 (16GB)   | 0.5-1 segundo  | ✅  | DialoGPT-large |
| A100 (40GB)   | 0.3-0.8 seg    | ✅  | Modelos más grandes |
| Sin GPU       | 10-30 segundos | ❌  | DialoGPT-small |

## 🔧 Configuración Avanzada

### Cambiar Modelo IA

Editar `ai_server_codespaces.py`:

```python
# Para GPU potente (>15GB):
self.model_name = "microsoft/DialoGPT-large"

# Para GPU moderada (8-15GB):  
self.model_name = "microsoft/DialoGPT-medium"

# Para testing rápido:
self.model_name = "microsoft/DialoGPT-small"
```

### Port Forwarding

Codespaces maneja automáticamente los puertos, pero puedes configurar manualmente:

```bash
# Ver puertos activos
gh codespace ports

# Forward puerto específico (si necesario)
gh codespace ports forward 5000:5000
```

### Variables de Entorno Adicionales

```bash
# En backend/.env - configuración avanzada:
CODESPACES_AI_URL=http://localhost:5000
CODESPACES_TIMEOUT_MS=45000
AI_MAX_RETRIES=3
AI_FALLBACK_ENABLED=true

# Para debugging:
AI_DEBUG_MODE=true
AI_LOG_REQUESTS=true
```

## 🧪 Testing Completo

### Test Individual
```bash
# Test básico de conectividad
curl http://localhost:5000/health

# Test matching single
curl -X POST http://localhost:5000/match \
  -H "Content-Type: application/json" \
  -d '{"candidate": {...}, "job": {...}}'
```

### Test desde PHP
```bash
# Test completo del sistema
php test_codespaces_complete.php

# Test solo conectividad
php -r "
require 'config/bootstrap.php';
use Services\CodespacesService;
\$service = new CodespacesService();
var_dump(\$service->testConnection());
"
```

### Test Performance
```bash
# Benchmark de 10 requests
for i in {1..10}; do
  echo "Test $i:"
  time curl -s -X POST http://localhost:5000/match \
    -H "Content-Type: application/json" \
    -d '{"candidate":{"hard_skills":["PHP","Laravel"]}, "job":{"required_skills":["PHP"]}}'
  echo ""
done
```

## 🚨 Troubleshooting

### Problema: GPU no detectada
```bash
# Verificar:
nvidia-smi
lspci | grep -i nvidia

# Solución: Recrear codespace con GPU
gh codespace delete
# Crear nuevo codespace en máquina con GPU
```

### Problema: Servidor IA no responde
```bash
# Verificar puertos:
netstat -tlnp | grep 5000

# Logs del servidor:
tail -f logs/ai_server.log

# Reiniciar servidor:
pkill -f ai_server_codespaces.py
python ai_server_codespaces.py
```

### Problema: Timeout en requests
```bash
# Aumentar timeout en .env:
CODESPACES_TIMEOUT_MS=60000

# O usar modelo más pequeño en ai_server_codespaces.py:
self.model_name = "microsoft/DialoGPT-small"
```

### Problema: Memoria GPU insuficiente
```bash
# Editar ai_server_codespaces.py para usar cuantización:
quantization = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_compute_dtype=torch.float16
)
```

## 📝 Workflow de Desarrollo

1. **Desarrollo local**: Usar Ollama para cambios rápidos
2. **Testing IA real**: Codespaces para validar con modelo potente  
3. **Entrega cliente**: Configuración OpenAI para producción

```bash
# Desarrollo local:
AI_PROVIDER=ollama

# Testing Codespaces:
AI_PROVIDER=codespaces  

# Producción cliente:
AI_PROVIDER=openai
```

## 💰 Consideraciones de Costo

- **Codespaces gratis**: 60 horas/mes para GitHub Pro
- **GPU usage**: Consumo adicional según tiempo de uso
- **Recomendación**: Usar para testing final, desarrollo en local

## 📋 Checklist de Entrega

- [ ] ✅ Tests en Codespaces con GPU pasados
- [ ] ✅ Performance validado (< 3 segundos por matching)
- [ ] ✅ Fallback algorítmico funcionando
- [ ] ✅ Documentación cliente OpenAI completa
- [ ] ✅ Variables de entorno configuradas
- [ ] ✅ Tests de no-regresión ejecutados

## 🔗 Enlaces Útiles

- [GitHub Codespaces Docs](https://docs.github.com/en/codespaces)
- [GPU en Codespaces](https://docs.github.com/en/codespaces/developing-in-codespaces/using-github-codespaces-with-github-cli)
- [Configuración cliente OpenAI](./SETUP_CLIENTE_PRODUCCION.md)
