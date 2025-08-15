# Setup Producción - Cliente Final

## Configuración IA para Producción

### 1. Obtener API Key de OpenAI
1. Ir a https://platform.openai.com/
2. Crear cuenta o iniciar sesión
3. Navegar a "API Keys" en el dashboard
4. Crear nueva API key con permisos de "All"
5. Copiar la key (empieza con `sk-proj-...`)

### 2. Configurar Variables de Entorno

Editar archivo `backend/.env`:

```bash
# Cambiar estas líneas para producción:
AI_PROVIDER=openai
OPENAI_API_KEY=sk-proj-TU_API_KEY_AQUI
OPENAI_MODEL=gpt-4o-mini
APP_ENV=production
APP_DEBUG=false
```

### 3. Modelos Recomendados por Costo/Rendimiento

#### Opción Económica (Recomendado para empezar):
```bash
OPENAI_MODEL=gpt-4o-mini
```
- Costo: ~$0.15 per 1M tokens
- Rendimiento: Excelente para matching CV
- Velocidad: Rápido

#### Opción Premium (Máximo rendimiento):
```bash
OPENAI_MODEL=gpt-4-turbo
```
- Costo: ~$10 per 1M tokens
- Rendimiento: Máximo disponible
- Velocidad: Moderado

### 4. Verificar Configuración

Ejecutar test de conexión:
```bash
cd backend
php test_openai_production.php
```

### 5. Monitoreo de Costos

- Dashboard OpenAI: https://platform.openai.com/usage
- Establecer límites de gasto mensual
- Monitorear tokens consumidos

### 6. Fallback Sin IA

El sistema incluye fallback algorítmico automático:
- Si OpenAI falla → matching básico por keywords
- Sin pérdida de funcionalidad
- Logs detallados para debugging

## Estimación de Costos

### Procesamiento típico por CV:
- Parsing: ~1,000 tokens
- Matching: ~1,500 tokens  
- Ruteo: ~800 tokens
- **Total por CV: ~3,300 tokens**

### Costos estimados (gpt-4o-mini):
- 100 CVs/mes: ~$0.05
- 1,000 CVs/mes: ~$0.50
- 10,000 CVs/mes: ~$5.00

*Costos reales pueden variar según complejidad de CVs*
