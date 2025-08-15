# 🛡️ CORS Architecture Documentation

## 📋 Resumen

Este documento describe la arquitectura centralizada de CORS implementada en Bubble of Talents v3.0.0.

## 🏗️ Arquitectura Centralizada

### Una Sola Fuente de Verdad

```
config/bootstrap.php (PUNTO DE ENTRADA)
    ↓
cors.php (ÚNICA FUENTE DE VERDAD)
    ↓
Todos los endpoints automáticamente protegidos
```

### Archivos Principales

- **`cors.php`**: Única fuente de verdad para configuración CORS
- **`config/bootstrap.php`**: Carga automática de CORS en línea 27
- **`.env`**: Configuración de orígenes permitidos

## 🔧 Configuración

### Variables de Entorno

```env
# Orígenes permitidos (separados por coma)
CORS_ALLOWED_ORIGINS=http://localhost:3002,http://localhost:3000,http://127.0.0.1:3002

# Ambiente de aplicación
APP_ENV=development  # development|production
APP_DEBUG=true       # true|false
```

### Orígenes por Ambiente

**Desarrollo:**
- Permite orígenes configurados en `CORS_ALLOWED_ORIGINS`
- Permite automáticamente `http://localhost:*` y `http://127.0.0.1:*`
- Logging detallado habilitado

**Producción:**
- Solo orígenes específicos en `CORS_ALLOWED_ORIGINS`
- Rechaza cualquier origen no autorizado
- Logging de violaciones de seguridad habilitado

## 📊 Logging de Seguridad

### Tipos de Eventos Logged

1. **`CORS_CHECK_INITIATED`**: Inicio de verificación CORS
2. **`CORS_ACCESS_GRANTED`**: Acceso autorizado
3. **`CORS_DEV_ACCESS_GRANTED`**: Acceso de desarrollo
4. **`CORS_ACCESS_DENIED_PROD`**: Acceso denegado en producción
5. **`CORS_CONFIG_APPLIED`**: Configuración aplicada
6. **`CORS_PREFLIGHT_HANDLED`**: Manejo de preflight OPTIONS
7. **`CORS_VIOLATION`**: Violación de seguridad (siempre logged)

### Formato de Logs

```json
{
  "timestamp": "2025-08-14 09:15:30",
  "event": "CORS_ACCESS_GRANTED",
  "origin": "http://localhost:3002",
  "method": "POST",
  "user_agent": "Mozilla/5.0...",
  "remote_addr": "127.0.0.1"
}
```

## 🚫 Archivos Deprecated

Los siguientes archivos/clases ya NO deben usarse:

- ❌ `src/Utils/ResponseHandler::setCorsHeaders()`
- ❌ `src/Utils/Cors::enforce()`
- ❌ `src/Middleware/CorsMiddleware::handle()`
- ❌ `api/bootstrap.php::sendCorsHeaders()`
- ❌ `api/bootstrap.php::preflightHandle()`

Estos métodos se mantienen por compatibilidad pero emiten warnings de deprecación.

## ✅ Mejores Prácticas

### Para Nuevos Endpoints

```php
<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__);
$BOOT = $ROOT . '/config/bootstrap.php';
if (!is_file($BOOT)) {
  http_response_code(500);
  exit('Bootstrap no encontrado');
}
require_once $BOOT;

// CORS ya configurado automáticamente - NO agregar headers manuales
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Tu lógica aquí...
```

### Headers NO Permitidos

❌ **NUNCA hacer esto:**
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Origin: http://localhost:3002');
header('Access-Control-Allow-Credentials: true');
// ... otros headers CORS manuales
```

## 🔍 Verificación

### Comando de Test

```bash
curl -v -H "Origin: http://localhost:3002" "http://localhost:8000/api/endpoint"
```

### Headers Esperados

```
Access-Control-Allow-Origin: http://localhost:3002
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token
Access-Control-Max-Age: 600
Vary: Origin
```

## 🚨 Troubleshooting

### Errores Comunes

1. **"No 'Access-Control-Allow-Origin' header"**
   - Verificar que el origen esté en `CORS_ALLOWED_ORIGINS`
   - Verificar que bootstrap.php se esté cargando

2. **Headers duplicados**
   - Eliminar cualquier header CORS manual
   - Usar solo el sistema centralizado

3. **Preflight fallando**
   - Verificar manejo de método OPTIONS
   - Revisar logs de CORS para más detalles

### Debug

```php
// Verificar si CORS está aplicado
if (defined('CORS_APPLIED')) {
    echo "CORS aplicado en: " . date('Y-m-d H:i:s', CORS_APPLIED_AT);
}

// Revisar logs
tail -f backend/logs/error.log | grep "CORS_"
```

## 📈 Métricas

El sistema registra métricas de performance:
- Tiempo de procesamiento de CORS
- Número de requests por origen
- Violaciones de seguridad por IP

## 🔒 Seguridad

- ✅ Validación estricta de orígenes
- ✅ Logging de intentos no autorizados
- ✅ Headers de seguridad adicionales
- ✅ Rate limiting implícito por MaxAge
- ✅ Protección contra bypass de CORS

---

**Versión:** 3.0.0  
**Última actualización:** 14 de Agosto de 2025  
**Mantenido por:** Bubble of Talents Security Team
