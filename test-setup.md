# 🧪 Guía de Pruebas - Bubble of Talents

## ✅ Estado del Proyecto

### Backend ✅
- ✅ Dependencias instaladas (Composer)
- ✅ Base de datos conectada
- ✅ Configuración de entorno corregida (development)
- ✅ CORS configurado correctamente

### Frontend ✅
- ✅ Dependencias instaladas (pnpm)
- ✅ Configuración de entorno corregida (development)
- ✅ Variables de entorno configuradas

## 🚀 Comandos para Iniciar el Proyecto

### 1. Iniciar Backend (API)
```bash
# Desde el directorio backend
cd backend
php -S localhost:8000 public/index.php
```

### 2. Iniciar Frontend (React)
```bash
# Desde el directorio frontend
cd frontend
pnpm dev
```

### 3. Acceder a la aplicación
- Frontend: http://localhost:3002
- Backend API: http://localhost:8000

## 🧪 Pruebas de Funcionalidad

### A. Pruebas del Backend
```bash
# Probar conexión a base de datos
php backend/config/test_conection_db.php

# Probar endpoints de la API
curl http://localhost:8000/api/test.php

# Probar autenticación
php backend/scripts/test_authentication.php
```

### B. Pruebas del Frontend
```bash
# Verificar compilación TypeScript
cd frontend
pnpm typecheck

# Ejecutar linting
pnpm lint

# Construir para producción
pnpm build
```

### C. Pruebas E2E con Playwright
```bash
# Desde la raíz del proyecto
npm run test:e2e
```

## 🔧 Configuraciones Aplicadas

### Variables de Entorno Backend
- `APP_ENV=development`
- `APP_DEBUG=true`
- `CORS_ALLOWED_ORIGINS` actualizado
- Base de datos funcional en 192.168.1.40

### Variables de Entorno Frontend
- `VITE_APP_ENV=development`
- Analytics deshabilitados para desarrollo
- `VITE_CV_ALLOW_MANUAL_ONLY=false`

## 📝 Próximos Pasos para Pruebas

1. **Probar funcionalidades básicas**:
   - Login/Registro
   - Subida de CV
   - Gestión de candidatos

2. **Probar integraciones**:
   - API de OpenAI (parseo de CV)
   - Sistema de autenticación
   - Base de datos

3. **Pruebas de rendimiento**:
   - Carga de archivos
   - Respuesta de la API
   - Interfaz de usuario

## 🐛 Debugging

### Logs del Backend
- Errores: `backend/storage/logs/`
- CORS: Verificar cabeceras en navegador

### Logs del Frontend
- Console del navegador
- Network tab para APIs

### Base de Datos
- Acceder directamente: `mysql -h 192.168.1.40 -u user -p bubble_talents_DB`

## 🔒 Seguridad

- JWT configurado con clave segura
- CORS configurado para localhost
- Headers de seguridad habilitados
- Debug habilitado solo en desarrollo
