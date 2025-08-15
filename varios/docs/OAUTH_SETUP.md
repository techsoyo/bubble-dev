# Configuración de Autenticación Social - Google y LinkedIn

## 📋 Descripción

Se ha implementado autenticación social para candidatos usando Google y LinkedIn OAuth2. Los candidatos pueden registrarse o iniciar sesión usando sus cuentas de Google o LinkedIn.

## 🚀 Características Implementadas

- ✅ **Botones de Google y LinkedIn** en formularios de login y registro
- ✅ **Flujo OAuth2 completo** con callback handling
- ✅ **Base de datos actualizada** con columnas para proveedores sociales
- ✅ **Interfaz visual mejorada** con iconos oficiales de las plataformas
- ✅ **Manejo de errores** y feedback visual para el usuario
- ✅ **Migración automática** de base de datos ejecutada

## 🔧 Configuración Requerida

### 1. Google OAuth Configuration

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la API de Google+ y Gmail API
4. Ve a "Credenciales" → "Crear credenciales" → "ID de cliente OAuth"
5. Selecciona "Aplicación web"
6. Configura las URIs de redirección autorizadas:
   ```
   http://localhost:3002/auth/callback.html
   https://tudominio.com/auth/callback.html
   ```
7. Copia el Client ID y actualiza estas variables:

**Backend (.env):**
```env
GOOGLE_CLIENT_ID=tu_google_client_id_aqui
GOOGLE_CLIENT_SECRET=tu_google_client_secret_aqui
```

**Frontend (.env):**
```env
VITE_GOOGLE_CLIENT_ID=tu_google_client_id_aqui
```

### 2. LinkedIn OAuth Configuration

1. Ve a [LinkedIn Developer Portal](https://developer.linkedin.com/)
2. Crea una nueva aplicación
3. En "Auth" tab, agrega estas URLs de redirección:
   ```
   http://localhost:3002/auth/callback.html
   https://tudominio.com/auth/callback.html
   ```
4. Solicita acceso a los siguientes scopes:
   - `r_liteprofile` (información básica del perfil)
   - `r_emailaddress` (dirección de email)
5. Copia el Client ID y actualiza estas variables:

**Backend (.env):**
```env
LINKEDIN_CLIENT_ID=tu_linkedin_client_id_aqui
LINKEDIN_CLIENT_SECRET=tu_linkedin_client_secret_aqui
```

**Frontend (.env):**
```env
VITE_LINKEDIN_CLIENT_ID=tu_linkedin_client_id_aqui
```

## 📁 Archivos Creados/Modificados

### Backend:
- `api/auth/social-callback.php` - Endpoint para procesar callbacks OAuth
- `database/migrations/add_social_auth_columns.php` - Migración ejecutada
- `.env` - Variables de entorno agregadas

### Frontend:
- `pages/auth/CandidateAuthPage.tsx` - Botones sociales agregados
- `public/auth/callback.html` - Página de callback para OAuth
- `.env` - Variables de entorno agregadas

### Base de Datos:
Se agregaron las siguientes columnas a `bt_candidates`:
- `provider_id` - ID del usuario en el proveedor OAuth
- `provider_type` - Tipo de proveedor (google, linkedin)
- `avatar` - URL del avatar del usuario

## 🔄 Flujo de Autenticación

1. **Usuario hace clic** en "Continuar con Google/LinkedIn"
2. **Redirección** a la página de autorización del proveedor
3. **Usuario autoriza** la aplicación en Google/LinkedIn
4. **Callback** redirige a `/auth/callback.html`
5. **JavaScript procesa** el código de autorización
6. **Backend intercambia** código por token de acceso
7. **Backend obtiene** información del usuario del proveedor
8. **Usuario se crea/actualiza** en base de datos
9. **Sesión establecida** y redirección al dashboard

## 🎨 Interfaz Visual

### Botones de Login:
- **Google**: Fondo blanco, icono oficial de Google
- **LinkedIn**: Fondo azul LinkedIn (#0077B5), icono oficial

### Estados de Botones:
- **Normal**: Estados predeterminados
- **Hover**: Efectos de hover apropiados
- **Disabled**: Durante procesamiento o loading
- **Loading**: Estados de carga con spinners

## ⚠️ Notas de Seguridad

1. **HTTPS Requerido**: En producción, las URLs de callback deben usar HTTPS
2. **Dominios Verificados**: Solo dominios verificados pueden usar OAuth
3. **Client Secrets**: Nunca expongas los client secrets en el frontend
4. **State Parameter**: Se usa para validar requests y prevenir CSRF

## 🧪 Testing

### Para probar en desarrollo:

1. **Configurar credenciales** en los archivos `.env`
2. **Iniciar servers**:
   ```bash
   # Backend
   cd backend && php -S localhost:8000
   
   # Frontend  
   cd frontend && npm run dev
   ```
3. **Navegar a** `http://localhost:3002/auth/candidate`
4. **Hacer clic** en botones de Google/LinkedIn

### URLs de Testing:
- Login candidatos: `http://localhost:3002/auth/candidate`
- Callback OAuth: `http://localhost:3002/auth/callback.html`
- API callback: `http://localhost:8000/api/auth/social-callback.php`

## 🚀 Deployment

### Para producción:

1. **Actualizar URLs** en consolas de Google y LinkedIn
2. **Configurar HTTPS** en server web
3. **Actualizar variables** de entorno con URLs de producción
4. **Verificar dominios** en ambas plataformas OAuth

## 💡 Próximas Mejoras Posibles

- 🔄 **Vincular cuentas** existentes con proveedores sociales
- 👤 **Importar foto de perfil** desde redes sociales
- 📧 **Sincronización de datos** de perfil profesional
- 🔗 **Múltiples proveedores** por usuario
- 📱 **Autenticación móvil** con deep links

## 📞 Soporte

Si tienes problemas con la configuración OAuth:

1. **Verifica las URLs** de redirección en ambas consolas
2. **Revisa los logs** del servidor para errores específicos
3. **Comprueba las variables** de entorno
4. **Valida los permisos** de las aplicaciones OAuth

---

**¡La autenticación social está lista para usar!** 🎉

Solo necesitas configurar las credenciales de Google y LinkedIn OAuth para activar completamente la funcionalidad.
