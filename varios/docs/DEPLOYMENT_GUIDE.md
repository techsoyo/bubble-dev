# 🚀 GUÍA DE DESPLIEGUE SEGURO - BUBBLE OF TALENTS

## ⚡ **PASOS PREVIOS AL DESPLIEGUE**

### 1. **🔒 CONFIGURACIÓN DE SEGURIDAD**

#### Variables de Entorno de Producción
```bash
# Crear archivo .env en servidor con valores reales
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Generar JWT secret seguro (32+ caracteres)
JWT_SECRET=$(openssl rand -base64 32)

# Configurar base de datos
DB_HOST=your_db_host
DB_USER=your_db_user
DB_PASS=your_secure_password

# Configurar API keys (obtener nuevas para producción)
OPENAI_API_KEY=sk-...nueva-clave-de-produccion
```

#### Permisos de Archivos
```bash
# Backend
chmod 644 backend/.env
chmod 755 backend/uploads/
chmod 755 backend/logs/
chown www-data:www-data backend/uploads/
chown www-data:www-data backend/logs/

# Remover archivos de desarrollo
rm -f backend/.env.example
rm -f backend/test_*.php
rm -f backend/debug_*.php
```

### 2. **🛡️ CONFIGURACIÓN DEL SERVIDOR WEB**

#### Apache (.htaccess)
```apache
# Denegar acceso a archivos sensibles
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# SSL/TLS
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

#### Nginx
```nginx
# Bloquear archivos sensibles
location ~ /\.(env|git|log) {
    deny all;
    return 404;
}

# Headers de seguridad
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

# PHP security
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param PHP_VALUE "display_errors=Off";
    fastcgi_param PHP_VALUE "log_errors=On";
}
```

### 3. **📱 BUILD DEL FRONTEND**

```bash
cd frontend/

# Instalar dependencias
pnpm install --frozen-lockfile

# Verificar configuración de producción
echo "VITE_API_BASE_URL=https://your-domain.com/api" > .env.production

# Build optimizado
pnpm run build

# Verificar build
ls -la dist/
```

### 4. **🔧 CONFIGURACIÓN DE PHP**

#### php.ini para Producción
```ini
; Seguridad
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Límites de seguridad
post_max_size = 10M
upload_max_filesize = 5M
max_execution_time = 30
memory_limit = 128M

; Sesiones seguras
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Strict"
session.use_only_cookies = 1
```

## 🧪 **TESTING PREVIO AL DESPLIEGUE**

### Checklist de Verificación

#### ✅ Seguridad
- [ ] Variables .env no están en repositorio
- [ ] APP_DEBUG=false
- [ ] Error reporting deshabilitado
- [ ] Headers de seguridad configurados
- [ ] HTTPS configurado
- [ ] Archivos sensibles protegidos

#### ✅ Funcionalidad
- [ ] Login/logout funciona
- [ ] API endpoints responden
- [ ] Upload de archivos funciona
- [ ] Base de datos conecta
- [ ] IA/OpenAI responde

#### ✅ Performance
- [ ] Assets minificados
- [ ] Cache configurado
- [ ] Compresión habilitada
- [ ] CDN configurado (opcional)

#### ✅ Monitoreo
- [ ] Logs configurados
- [ ] Backup automatizado
- [ ] SSL certificate válido
- [ ] DNS configurado

## 📋 **COMANDOS DE DESPLIEGUE**

### Despliegue Inicial
```bash
# 1. Clonar repositorio
git clone https://github.com/your-repo/bubble-of-talents.git
cd bubble-of-talents/

# 2. Configurar backend
cd backend/
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Editar .env con valores de producción

# 3. Configurar frontend
cd ../frontend/
pnpm install --frozen-lockfile
pnpm run build

# 4. Configurar permisos
sudo chown -R www-data:www-data ../
sudo chmod 755 uploads/ logs/

# 5. Configurar base de datos
mysql -u root -p < database/schema.sql
```

### Actualizaciones
```bash
# 1. Backup
mysqldump -u user -p bubble_talents_db > backup_$(date +%Y%m%d).sql

# 2. Pull cambios
git pull origin main

# 3. Actualizar dependencias
cd backend/ && composer install --no-dev
cd ../frontend/ && pnpm install && pnpm run build

# 4. Verificar
tail -f backend/logs/application.log
```

## 🚨 **MONITOREO Y MANTENIMIENTO**

### Logs a Vigilar
```bash
# Logs de aplicación
tail -f backend/logs/application.log
tail -f backend/logs/security.log

# Logs del servidor
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log

# Logs de PHP
tail -f /var/log/php_errors.log
```

### Backup Automatizado
```bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d_%H%M%S)

# Backup base de datos
mysqldump -u user -p bubble_talents_db > backup_db_$DATE.sql

# Backup archivos
tar -czf backup_files_$DATE.tar.gz backend/uploads/

# Limpiar backups antiguos (>30 días)
find . -name "backup_*" -mtime +30 -delete
```

## 🆘 **TROUBLESHOOTING**

### Problemas Comunes

#### 500 Internal Server Error
```bash
# Verificar logs
tail -f backend/logs/application.log
tail -f /var/log/apache2/error.log

# Verificar permisos
ls -la backend/uploads/
ls -la backend/logs/
```

#### API No Responde
```bash
# Verificar configuración
cat backend/.env | grep APP_ENV
cat backend/.env | grep DB_

# Test de conexión DB
php backend/test_db_connection.php
```

#### Frontend No Carga
```bash
# Verificar build
ls -la frontend/dist/

# Verificar configuración
cat frontend/.env.production
```

## 📞 **CONTACTO DE EMERGENCIA**

- **Soporte Técnico:** [email]
- **Monitoreo:** [URL del dashboard]
- **Documentación:** [URL de la wiki]

---

**🔥 IMPORTANTE:** Hacer siempre backup antes de cualquier cambio en producción.
