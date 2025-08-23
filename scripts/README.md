# Scripts de Migración de Endpoints

Esta carpeta contiene scripts para migrar endpoints legacy a la nueva estructura REST API.

## 🚀 Instalación

Las dependencias ya están instaladas localmente en esta carpeta.

## 📋 Scripts Disponibles

### 1. Análisis y Migración
```bash
npm run migrate
```
- Analiza todos los archivos del frontend
- Encuentra uso de endpoints legacy
- Genera resumen en `endpoint-usage-summary.json`
- Crea tests de Playwright automáticamente

### 2. Ejecución de Tests
```bash
npm run test           # Ejecutar tests con verificación previa
npm run test:endpoints # Ejecutar tests directamente
npm run test:headed    # Ejecutar tests con interfaz visual
```

## 📄 Archivos Generados

### `endpoint-usage-summary.json`
Contiene el mapeo de endpoints legacy → REST y archivos donde se usan.

### `tests/generated-endpoint-tests.spec.ts`
Tests de Playwright para validar que los nuevos endpoints funcionen.

### `tests/test-config.json`
Configuración y estadísticas de los tests.

## 🎯 Estadísticas Actuales

- **Total endpoints**: 35
- **Endpoints en uso**: 29 
- **Archivos afectados**: 30

## 📈 Endpoints Encontrados

Los endpoints más utilizados:
- `notifications.php` → `/api/notifications` (2 archivos)
- `candidates.php` → `/api/candidates`
- `skills.php` → `/api/skills`
- `chatbot.php` → `/api/ai/chatbot`

## ⚙️ Configuración

El script busca archivos TypeScript/JavaScript en:
- `../frontend/src/`
- `../oauth_implementation/frontend/`

Los tests apuntan a: `http://localhost:8000`

## 🔧 Requisitos

- Backend corriendo en `http://localhost:8000`
- Node.js y npm
- Playwright (instalado automáticamente)

## 🚦 Estado de Endpoints

Para ver el estado actual de cada endpoint, revisa:
- `endpoint-usage-summary.json` - Lista completa
- `tests/test-config.json` - Estadísticas resumidas

## 💡 Próximos Pasos

1. Ejecutar `npm run test` para validar endpoints
2. Implementar endpoints faltantes en el backend
3. Actualizar código frontend para usar nuevos endpoints REST
