# 🧪 TESTING DEL STORAGE GUARD - GUÍA RÁPIDA

## 🚀 Instrucciones Inmediatas

**El frontend está corriendo en:** http://localhost:3002

### 📋 Pasos para Testing:

1. **Abrir el navegador** → http://localhost:3002
2. **Abrir DevTools** (F12)
3. **Ir a Console**
4. **Ejecutar los siguientes comandos:**

```javascript
// 🧪 Test completo del Storage Guard
testStorageGuard()

// ⚡ Test rápido
quickTest()

// 🔍 Ver estado del Storage Guard
storageGuard.debugStorageGuard()
```

## 🔍 Qué Esperar Ver:

### ✅ En Modo Desarrollo (actual):
```
🧪 INICIANDO TESTING DEL STORAGE GUARD
📋 Test 1: Verificación de entorno
  Modo producción: false
  NODE_ENV: browser

📋 Test 2: Testing con funciones seguras
  🔍 Testing auth_token con Storage Guard:
    ✅ ÉXITO - permitido en desarrollo
  
  🔍 Testing cv-draft con Storage Guard:
    ✅ ÉXITO - permitido en desarrollo
```

### 🔒 En Modo Producción:
```
[StorageGuard] 🚫 localStorage.setItem("auth_token") BLOQUEADO en producción
[StorageGuard] 💡 Usa sessionStorage temporal o migra al backend
```

## 🎯 Tests Específicos a Realizar:

### Test 1: Verificar Bloqueo de Claves Sensibles
```javascript
// Estas deberían estar permitidas en desarrollo, bloqueadas en producción
localStorage.setItem("auth_token", "test");
localStorage.setItem("cv-draft", "test");
localStorage.setItem("emailHistory", "test");
```

### Test 2: Verificar Claves Permitidas
```javascript
// Estas siempre deberían funcionar
localStorage.setItem("bt_theme_preference", "dark");
localStorage.setItem("bt_security_cleanup_test", "ok");
```

### Test 3: Verificar sessionStorage
```javascript
// Siempre debe funcionar
sessionStorage.setItem("temp-data", "test");
console.log(sessionStorage.getItem("temp-data"));
```

## 🏆 Resultado Esperado:

- ✅ **Desarrollo**: Todo permitido + warnings para claves sensibles
- ✅ **sessionStorage**: Siempre funciona
- ✅ **Producción**: Solo claves de seguridad permitidas
- ✅ **Logs claros**: Warnings y bloqueos bien documentados

---

## 🚨 Si Algo No Funciona:

1. **Verificar que el Storage Guard se cargó:**
   ```javascript
   typeof storageGuard !== 'undefined'
   ```

2. **Ver errores en Console:**
   - Buscar mensajes rojos de error
   - Verificar que las importaciones funcionan

3. **Verificar modo de la app:**
   ```javascript
   import.meta.env.PROD  // false = desarrollo, true = producción
   ```

---

**🎯 Objetivo**: Confirmar que el Storage Guard bloquea datos sensibles en producción y permite todo en desarrollo.
