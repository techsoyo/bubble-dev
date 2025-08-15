# 🚀 GUÍA DE CONFIGURACIÓN OAUTH - GOOGLE & LINKEDIN

## 📋 RESUMEN EJECUTIVO

Esta guía te permitirá activar el login con Google y LinkedIn en tu plataforma Bubble of Talents en **menos de 30 minutos**.

**Tiempo estimado:** 25-30 minutos  
**Dificultad:** Básica  
**Requisitos:** Cuenta Google y LinkedIn

---

## 🎯 PASO 1: CONFIGURAR GOOGLE OAUTH (15 minutos)

### 1.1 Acceder a Google Cloud Console
1. Ve a: https://console.cloud.google.com/
2. Inicia sesión con tu cuenta Google
3. **Crear proyecto** (si no tienes uno):
   - Haz clic en "Seleccionar proyecto" → "Nuevo proyecto"
   - Nombre: `Bubble of Talents Auth`
   - Ubicación: Tu organización (opcional)
   - Haz clic en **"Crear"**

### 1.2 Habilitar Google+ API
1. En el menú lateral: **APIs y servicios** → **Biblioteca**
2. Busca: `Google+ API`
3. Haz clic en **"Habilitar"**

### 1.3 Crear credenciales OAuth
1. Ve a: **APIs y servicios** → **Credenciales**
2. Haz clic en **"+ CREAR CREDENCIALES"** → **"ID de cliente OAuth 2.0"**
3. Si aparece la pantalla de consentimiento:
   - **Tipo de usuario:** Externo
   - **Nombre de la aplicación:** `Bubble of Talents`
   - **Correo del usuario:** tu email
   - **Dominios autorizados:** `localhost` (para desarrollo)

### 1.4 Configurar ID de cliente OAuth
1. **Tipo de aplicación:** Aplicación web
2. **Nombre:** `Bubble Talents Web Client`
3. **Orígenes autorizados de JavaScript:**
   ```
   http://localhost:3002
   http://localhost:8000
   ```
4. **URIs de redirección autorizados:**
   ```
   http://localhost:8000/auth/google/callback
   ```
5. Haz clic en **"Crear"**

### 1.5 Copiar credenciales
1. **Copia el Client ID** (termina en `.apps.googleusercontent.com`)
2. **Copia el Client Secret**
3. Guárdalos temporalmente

---

## 🔗 PASO 2: CONFIGURAR LINKEDIN OAUTH (10 minutos)

### 2.1 Acceder a LinkedIn Developers
1. Ve a: https://www.linkedin.com/developers/apps
2. Inicia sesión con tu cuenta LinkedIn
3. Haz clic en **"Create app"**

### 2.2 Crear aplicación
1. **App name:** `Bubble of Talents`
2. **LinkedIn Page:** Selecciona tu empresa (crear si no existe)
3. **Privacy policy URL:** `http://localhost:3002/privacy` (temporal)
4. **App logo:** Sube el logo de Bubble of Talents
5. Acepta términos y haz clic en **"Create app"**

### 2.3 Configurar OAuth
1. Ve a la pestaña **"Auth"**
2. En **"Redirect URLs"** añade:
   ```
   http://localhost:8000/auth/linkedin/callback
   ```
3. En **"Scopes"** selecciona:
   - ✅ `r_liteprofile` (información básica del perfil)
   - ✅ `r_emailaddress` (dirección de email)

### 2.4 Copiar credenciales
1. **Copia el Client ID**
2. **Copia el Client Secret**
3. Guárdalos temporalmente

---

## ⚙️ PASO 3: CONFIGURAR TU APLICACIÓN (5 minutos)

### 3.1 Configurar variables de entorno
1. Ve a la carpeta: `backend/`
2. Copia el archivo: `.env.oauth.example` → `.env.oauth`
3. Abre `.env.oauth` y completa:

```bash
# GOOGLE OAUTH
GOOGLE_CLIENT_ID=tu_client_id_google_aqui.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tu_client_secret_google_aqui

# LINKEDIN OAUTH  
LINKEDIN_CLIENT_ID=tu_client_id_linkedin_aqui
LINKEDIN_CLIENT_SECRET=tu_client_secret_linkedin_aqui

# GENERAR KEYS ALEATORIAS (usa una herramienta online o comando)
OAUTH_SESSION_SECRET=una_clave_aleatoria_de_32_caracteres_minimo
OAUTH_ENCRYPTION_KEY=exactamente_32_caracteres_aleatorios_aqui
```

### 3.2 Generar claves de seguridad
**Opción A - Online:**
- Ve a: https://www.allkeysgenerator.com/Random/Security-Encryption-Key-Generator.aspx
- Genera 2 claves de 32 caracteres

**Opción B - Terminal:**
```bash
# Para Windows PowerShell
[System.Web.Security.Membership]::GeneratePassword(32, 5)

# Para Linux/Mac
openssl rand -base64 32
```

---

## 🚀 PASO 4: ACTIVAR EN PRODUCCIÓN

### 4.1 Cuando depliegues a producción
1. **Google Cloud Console:**
   - Añade tu dominio real a "Orígenes autorizados"
   - Actualiza URI de redirección: `https://tudominio.com/auth/google/callback`

2. **LinkedIn Developers:**
   - Añade URI de redirección: `https://tudominio.com/auth/linkedin/callback`

3. **Variables de entorno:**
   ```bash
   GOOGLE_REDIRECT_URI=https://tudominio.com/auth/google/callback
   LINKEDIN_REDIRECT_URI=https://tudominio.com/auth/linkedin/callback
   ```

---

## ✅ VERIFICACIÓN

### Cómo saber si funciona:
1. Ve a: `http://localhost:3002/auth/register`
2. Deberías ver 4 botones:
   - "Iniciar sesión con Google" ✅
   - "Iniciar sesión con LinkedIn" ✅
   - "Registrarse con Google" ✅  
   - "Registrarse con LinkedIn" ✅

3. Al hacer clic, deberías ser redirigido a Google/LinkedIn

---

## 🆘 SOPORTE TÉCNICO

**Si tienes problemas:**
1. Verifica que las URLs de callback coincidan exactamente
2. Asegúrate de que las APIs estén habilitadas
3. Comprueba que el archivo `.env.oauth` esté bien configurado
4. Revisa los logs del navegador (F12 → Console)

**Errores comunes:**
- `redirect_uri_mismatch`: Las URLs no coinciden
- `invalid_client`: Client ID o Secret incorrectos
- `access_denied`: Usuario canceló el proceso

---

## 📞 CONTACTO

Si necesitas ayuda adicional, contacta con el equipo de desarrollo con:
- Los mensajes de error exactos
- Screenshots de la configuración
- Los logs del navegador

**¡La configuración OAuth estará lista en menos de 30 minutos!** 🎉
