# 🧪 TESTING CHECKLIST COMPLETO - Bubble of Talents

## 📋 GUÍA COMPLETA DE FUNCIONALIDADES PARA TESTING

**Fecha:** 15 de agosto de 2025  
**Estado:** Ready for Production Testing  
**Versión:** 1.0.0

---

## 🔗 CONFIGURACIÓN INICIAL

### **SERVIDORES REQUERIDOS**
```bash
# 1. Backend (Terminal 1)
cd backend
php -S localhost:8000 public/index.php

# 2. Frontend (Terminal 2) 
cd frontend
npm run dev
# Acceso: http://localhost:3002
```

### **CREDENCIALES DE TESTING**
```bash
# ADMIN
Email: ana.torres@bubblegum.agency
Password: BubbleAdmin2025!
Redirección: /dashboard/hrdashboard

# RECRUITERS
Email: miguel.ruiz@bubblegum.agency  
Password: Recruit#2025M
Email: sofia.navarro@bubblegum.agency
Password: Sofia&Talents25
Email: carlos.vega@bubblegum.agency
Password: CarlosRec#2025
Email: elena.ruiz@bubblegum.agency
Password: Elena!Bubble25
Redirección: /dashboard/recruiterdashboard

# BASE DE DATOS
Host: 192.168.1.40:3306
DB: bubble_talents_DB
User: user / Pass: user123
```

---

## 🔐 1. SISTEMA DE AUTENTICACIÓN

### **1.1 Autenticación Staff (HR/Recruiters/Admin)**
- [ ] **Login Staff**: http://localhost:3002/staff/login
  - [ ] Credenciales admin válidas → Redirección HRDashboard
  - [ ] Credenciales recruiter válidas → Redirección RecruiterDashboard
  - [ ] Credenciales inválidas → Mensaje de error
  - [ ] Cookies HTTP-only establecidas correctamente
  - [ ] Contexto isLoggedIn = true
  - [ ] Acceso directo a rutas protegidas funciona tras login

### **1.2 Autenticación Candidatos**
- [ ] **Register Candidatos**: http://localhost:3002/auth/register
  - [ ] Registro nuevo candidato con email válido
  - [ ] Validaciones de formulario funcionan
  - [ ] Redirección automática a /dashboard/cddashboard
  - [ ] Datos guardados en base de datos
  - [ ] Login candidato existente funciona
  - [ ] Password recovery disponible

### **1.3 OAuth Social (Google/LinkedIn)**
- [ ] **Botón Google**: Redirección a Google OAuth
  - [ ] Autorización usuario → Callback correcto
  - [ ] Usuario creado/actualizado en BD
  - [ ] Sesión establecida automáticamente
  - [ ] Redirección a dashboard candidato
- [ ] **Botón LinkedIn**: Mismo flujo que Google
- [ ] **Gestión errores**: OAuth cancelado o fallido

### **1.4 Rutas Protegidas & Redirecciones**
- [ ] **Staff dashboards sin auth** → /staff/login
- [ ] **Candidate dashboard sin auth** → /auth/register
- [ ] **Rutas públicas**: Acceso libre (/, /jobs, etc.)
- [ ] **Logout**: Elimina cookies, redirecciona correctamente

---

## 📊 2. DASHBOARDS & MÓDULOS PRINCIPALES

### **2.1 HR Dashboard (Admin)**
**URL**: http://localhost:3002/dashboard/hrdashboard

#### **Pestañas Principales**
- [ ] **Candidates by Job**: 
  - [ ] Lista trabajos disponibles
  - [ ] Candidatos por trabajo con información completa
  - [ ] Filtros por departamento, estado, fecha
  - [ ] Asignación candidato-recruiter funcional
- [ ] **Job Management**:
  - [ ] CRUD completo de trabajos
  - [ ] Validaciones de formulario
  - [ ] Estados de trabajos (activo/inactivo)
- [ ] **Candidate Database**:
  - [ ] Vista completa de candidatos
  - [ ] Búsqueda y filtros avanzados
  - [ ] Edición inline de información
  - [ ] Eliminación con confirmación
- [ ] **Estadísticas**:
  - [ ] Gráfico circular de estados aplicaciones
  - [ ] Gráfico barras candidatos por departamento  
  - [ ] Top 10 skills más demandadas
  - [ ] Distribución scores de match
  - [ ] Cards métricas principales responsive

#### **Sistema Asignación Candidato-Recruiter**
- [ ] **Modal asignación**:
  - [ ] Selección candidato con info departamento
  - [ ] Recomendación recruiter por departamento
  - [ ] Validación coincidencia departamentos
- [ ] **Notificaciones Email**:
  - [ ] Email automático a recruiter asignado
  - [ ] Email automático a candidato
  - [ ] Contenido personalizado con datos correctos
  - [ ] Confirmación visual de envío
- [ ] **Workflow Admin**:
  - [ ] Admin permanece en HRDashboard post-asignación
  - [ ] Modal se cierra automáticamente
  - [ ] Feedback visual durante proceso
  - [ ] Múltiples asignaciones consecutivas posibles

### **2.2 Recruiter Dashboard**
**URL**: http://localhost:3002/dashboard/recruiterdashboard

#### **Funcionalidades Core**
- [ ] **Candidatos Asignados**:
  - [ ] Lista candidatos específicos del recruiter
  - [ ] Información detallada por candidato
  - [ ] Filtros por estado, departamento, skills
  - [ ] Búsqueda por nombre/email
- [ ] **Gestión Estados**:
  - [ ] Cambio estado candidato con dropdown
  - [ ] Notas adicionales en cambios
  - [ ] Notificación automática a candidato por email
  - [ ] Historial de cambios visible
- [ ] **Programación Entrevistas**:
  - [ ] Formulario completo con validaciones
  - [ ] Tipos: presencial/virtual/telefónica
  - [ ] Integración calendario (si configurada)
  - [ ] Link meeting automático para virtuales
  - [ ] Notificación email candidato con detalles
- [ ] **Quick Actions Menu**:
  - [ ] Acceso rápido desde cada candidato
  - [ ] Programar entrevista directa
  - [ ] Enviar email directo
  - [ ] Cambiar estado rápido

#### **Panel Estadísticas Recruiter**
- [ ] **Métricas personales**:
  - [ ] Total candidatos asignados
  - [ ] Entrevistas programadas
  - [ ] Ratio contratación
  - [ ] Candidatos por estado

### **2.3 Candidate Dashboard**
**URL**: http://localhost:3002/dashboard/cddashboard

#### **Funcionalidades Candidato**
- [ ] **Perfil Personal**:
  - [ ] Información completa editable
  - [ ] Upload/edición CV
  - [ ] Skills y experiencia
  - [ ] Preferencias laborales
- [ ] **Aplicaciones**:
  - [ ] Lista aplicaciones realizadas
  - [ ] Estado actual de cada aplicación
  - [ ] Detalles de trabajos aplicados
  - [ ] Historial de cambios de estado
- [ ] **Notificaciones**:
  - [ ] Alertas nuevas oportunidades
  - [ ] Updates de estado de aplicaciones
  - [ ] Invitaciones a entrevistas
  - [ ] Mensajes de recruiters

### **2.4 Manager Dashboard**
**URL**: http://localhost:3002/dashboard/managerdashboard

- [ ] **Métricas Departamentales**:
  - [ ] KPIs específicos del manager
  - [ ] Performance de recruiters bajo supervisión
  - [ ] Estadísticas de contratación
- [ ] **Gestión Equipos**:
  - [ ] Asignación recursos
  - [ ] Supervisión workflows
  - [ ] Reportes ejecutivos

---

## 💼 3. GESTIÓN DE TRABAJOS & APLICACIONES

### **3.1 Jobs Module**
**URL**: http://localhost:3002/jobs

#### **Lista Trabajos Públicos**
- [ ] **Vista pública**: Trabajos activos visibles
- [ ] **Filtros**: Ubicación, departamento, modalidad
- [ ] **Búsqueda**: Por título, skills, descripción
- [ ] **Paginación**: Navegación correcta

#### **Detalle Trabajo**
- [ ] **URL**: http://localhost:3002/jobs/[id]
- [ ] **Información completa**: Título, descripción, requerimientos
- [ ] **Botón Apply**: Redirección correcta
- [ ] **Skills matching**: Coincidencias destacadas
- [ ] **Related jobs**: Trabajos similares

### **3.2 Apply Module** 
**URL**: http://localhost:3002/jobs/apply

#### **Formulario Aplicación**
- [ ] **Upload CV**: Lazy loading component
  - [ ] Drag & drop funcional
  - [ ] Validación tipos archivo (PDF, DOC, DOCX)
  - [ ] Límite tamaño archivo respetado
  - [ ] Preview CV subido
  - [ ] Parsing automático datos CV
- [ ] **Información Personal**:
  - [ ] Formulario completo con validaciones
  - [ ] Campos requeridos marcados
  - [ ] Validación email, teléfono
- [ ] **Carta Presentación**:
  - [ ] Textarea con límite caracteres
  - [ ] Sugerencias automáticas basadas en trabajo
- [ ] **Submit**:
  - [ ] Validación completa pre-envío
  - [ ] Confirmación aplicación exitosa
  - [ ] Email confirmación a candidato
  - [ ] Redirección a dashboard candidato

---

## 🤖 4. SISTEMA CHATBOT & IA

### **4.1 ChatBot Funcional**
**URL**: Integrado en múltiples páginas

#### **Funcionalidades Core**
- [ ] **Widget flotante**: Visible en todas las páginas
- [ ] **Conversaciones**: 
  - [ ] Respuestas contextuales
  - [ ] Flujo de preguntas lógico
  - [ ] Escalación a humano
- [ ] **Base conocimiento**:
  - [ ] FAQs empresariales
  - [ ] Información trabajos
  - [ ] Proceso aplicación
- [ ] **Persistencia**: Conversaciones guardadas por sesión

### **4.2 Administración ChatBot (HRDashboard)**
- [ ] **Gestión Conversaciones**:
  - [ ] Lista conversaciones recientes
  - [ ] Respuestas pendientes
  - [ ] Analytics de interacciones
- [ ] **Configuración**:
  - [ ] Edición respuestas automáticas
  - [ ] Flujos de conversación
  - [ ] Triggers y keywords

### **4.3 Servicios IA Integrados**
- [ ] **CV Parsing**: Extracción automática datos
- [ ] **Candidate Matching**: Scoring automático
- [ ] **Pre-screening**: Questions automáticas
- [ ] **Content Generation**: Descripciones trabajos
- [ ] **Communication**: Templates emails
- [ ] **Recruitment Insights**: Analytics avanzados

---

## 📧 5. SISTEMA NOTIFICACIONES

### **5.1 Email Notifications**
- [ ] **Candidato Notifications**:
  - [ ] Confirmación aplicación
  - [ ] Cambios estado aplicación
  - [ ] Invitaciones entrevista
  - [ ] Ofertas trabajo
- [ ] **Recruiter Notifications**:
  - [ ] Nuevos candidatos asignados
  - [ ] Confirmaciones entrevista
  - [ ] Updates candidatos
- [ ] **Admin Notifications**:
  - [ ] Reportes semanales
  - [ ] Alertas sistema
  - [ ] Métricas importantes

### **5.2 In-App Notifications**
- [ ] **Real-time updates**: Notificaciones instantáneas
- [ ] **Badge counters**: Contadores no leídas
- [ ] **History**: Historial notificaciones
- [ ] **Preferences**: Configuración por usuario

---

## 🔧 6. FUNCIONALIDADES TÉCNICAS

### **6.1 Performance & Optimización**
- [ ] **Lazy Loading**: Componentes pesados
- [ ] **Code Splitting**: Chunks optimizados
- [ ] **Caching**: Estrategias implementadas
- [ ] **Compression**: Assets comprimidos

### **6.2 Security**
- [ ] **CORS**: Configuración restrictiva
- [ ] **Authentication**: Tokens seguros
- [ ] **Input Validation**: Sanitización datos
- [ ] **HTTPS**: Certificados válidos (producción)

### **6.3 Responsive Design**
- [ ] **Mobile**: Funcionalidad completa
- [ ] **Tablet**: Layout adaptativo
- [ ] **Desktop**: Experiencia optimizada
- [ ] **Cross-browser**: Compatibilidad navegadores

### **6.4 Accessibility**
- [ ] **Screen readers**: Compatible
- [ ] **Keyboard navigation**: Funcional
- [ ] **Color contrast**: WCAG compliant
- [ ] **Focus management**: Correcto

---

## 🧪 7. TESTING METODOLOGÍA

### **Secuencia Testing Recomendada**

#### **Fase 1: Auth & Navigation (30 min)**
1. [ ] Login staff → HRDashboard → Logout
2. [ ] Login recruiter → RecruiterDashboard → Logout  
3. [ ] Register candidato → CDDashboard → Logout
4. [ ] OAuth Google/LinkedIn (si configurado)
5. [ ] Rutas protegidas sin auth → Redirects correctos

#### **Fase 2: Core Workflows (45 min)**
1. [ ] Admin: Crear trabajo → Asignar candidato → Verificar emails
2. [ ] Recruiter: Recibir asignación → Cambiar estado → Programar entrevista
3. [ ] Candidato: Aplicar trabajo → Recibir updates → Ver cambios dashboard

#### **Fase 3: Features Avanzadas (30 min)**
1. [ ] ChatBot: Conversación completa → Escalación
2. [ ] Upload CV: Parsing → Validación datos
3. [ ] Estadísticas: Gráficos → Filtros → Export
4. [ ] Admin Panel: CRUD operaciones

#### **Fase 4: Edge Cases (15 min)**
1. [ ] Errores red → Fallbacks correctos
2. [ ] Datos inválidos → Validaciones
3. [ ] Sesiones expiradas → Re-auth
4. [ ] Mobile responsive → Funcionalidad completa

---

## ✅ CRITERIOS DE ÉXITO

### **MUST HAVE (Críticos)**
- [ ] Autenticación 100% funcional
- [ ] Asignación candidato-recruiter working
- [ ] Notificaciones email enviadas
- [ ] Dashboards cargan correctamente
- [ ] Apply proceso completo
- [ ] Base datos persistencia

### **SHOULD HAVE (Importantes)**
- [ ] ChatBot responde correctamente
- [ ] Estadísticas gráficos rendering
- [ ] Mobile responsive working
- [ ] Performance <3s load time
- [ ] OAuth social funcional

### **NICE TO HAVE (Deseables)**
- [ ] Calendarios integrados
- [ ] Analytics avanzados
- [ ] Exportación reportes
- [ ] Bulk operations
- [ ] Audit logs

---

## 🚨 CHECKLIST FINAL PRE-PRODUCTION

- [ ] **Todos los flows críticos** ✅ funcionando
- [ ] **Base datos** ✅ datos de prueba limpios
- [ ] **Variables entorno** ✅ configuradas producción
- [ ] **CORS policies** ✅ restrictivas aplicadas
- [ ] **Error handling** ✅ implementado global
- [ ] **Logs sistema** ✅ configurados
- [ ] **Performance** ✅ <3s tiempos carga
- [ ] **Security** ✅ vulnerabilidades auditadas

---

**🎯 OBJETIVO: 100% funcionalidades core working before deployment**

**📧 Reporte issues**: Documentar cualquier fallo con steps to reproduce

**⏱️ Tiempo estimado testing completo**: 2 horas

**📅 Próximo milestone**: Deployment producción tras testing exitoso
