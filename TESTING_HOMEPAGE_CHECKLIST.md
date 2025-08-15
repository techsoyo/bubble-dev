# 🏠 TESTING HOMEPAGE - Bubble of Talents

## 📋 FUNCIONALIDADES HOMEPAGE DETECTADAS

**URL**: http://localhost:3002  
**Fecha**: 15 de agosto de 2025  
**Estado**: Testing en curso

---

## 🧭 1. NAVEGACIÓN PRINCIPAL

### **Header & Menu Principal**
- [ ] **Logo Bubblegum.agency**: Clic → Recarga homepage
- [ ] **Menú Navegación**:
  - [ ] **Inicio**: Vuelve a homepage
  - [ ] **Empleos**: Redirección a /jobs
  - [ ] **Cultura**: Sección scroll o página dedicada
  - [ ] **Blog**: Redirección a blog/noticias
- [ ] **Toggle Language (Idioma)**:
  - [ ] Cambio ES/EN funcional
  - [ ] Persistencia del idioma seleccionado
- [ ] **Toggle Menu Mobile**: 
  - [ ] Funcional en responsive
  - [ ] Animación smooth
  - [ ] Cierre automático al clic fuera

### **Botones de Acceso (CTAs Principales)**
- [ ] **"Soy Candidato/a"**:
  - [ ] Redirección a página registro candidatos
  - [ ] URL esperada: /auth/register
- [ ] **"Soy de Bubble"**:
  - [ ] Redirección a login staff
  - [ ] URL esperada: /staff/login

---

## 🎯 2. SECCIÓN HERO

### **Contenido Principal**
- [ ] **Título**: "Oportunidades que transforman carreras"
- [ ] **Subtítulo**: Descripción del valor de la plataforma
- [ ] **Elementos visuales**: Animaciones AOS funcionando
- [ ] **CTA Hero**: Botón principal visible y funcional

### **Sección Beneficios**
- [ ] **Cards Beneficios**:
  - [ ] "Compensación competitiva y transparente"
  - [ ] "Flexibilidad total de horarios" 
  - [ ] "Crecimiento profesional continuo"
- [ ] **Iconografía**: Íconos visibles (●○◆)
- [ ] **Animaciones**: Efectos hover funcionando

---

## 💼 3. SECCIÓN EMPLEOS

### **"Donde el talento encuentra su lugar"**
- [ ] **Botón "Buscar empleos"**:
  - [ ] Redirección a página jobs
  - [ ] URL esperada: /jobs

### **Grid Trabajos (6 positions detectadas)**
1. [ ] **Full Stack Developer**:
   - [ ] Información: Madrid • full-time • Engineering
   - [ ] Botón "Ver →": Redirección a detalle trabajo
2. [ ] **Frontend Developer**:
   - [ ] Información: Barcelona • full-time • Engineering  
   - [ ] Botón "Ver →": Funcional
3. [ ] **Backend Developer**:
   - [ ] Información: Valencia • full-time • Engineering
   - [ ] Botón "Ver →": Funcional
4. [ ] **DevOps Engineer**:
   - [ ] Información: Sevilla • full-time • Operations
   - [ ] Botón "Ver →": Funcional
5. [ ] **Data Scientist**:
   - [ ] Información: Bilbao • full-time • Data
   - [ ] Botón "Ver →": Funcional
6. [ ] **UX/UI Designer**:
   - [ ] Información: Madrid • part-time • Design
   - [ ] Botón "Ver →": Funcional

### **Funcionalidades Grid**
- [ ] **Responsive**: Grid se adapta correctamente
- [ ] **Hover effects**: Animaciones en cards
- [ ] **Loading**: Cards cargan sin errores

---

## 🏢 4. SECCIÓN CULTURA EMPRESARIAL

### **Cards Cultura (3 elementos)**
- [ ] **Innovación Continua**:
  - [ ] Título y descripción visibles
  - [ ] "Fomentamos la experimentación y el aprendizaje diario"
- [ ] **Trabajo en Equipo**:
  - [ ] Título y descripción visibles
  - [ ] "Creemos que los mejores resultados surgen de la colaboración"
- [ ] **Orientación al Cliente**:
  - [ ] Título y descripción visibles
  - [ ] "El cliente es el centro de todo lo que hacemos"

### **Funcionalidades**
- [ ] **Layout**: Cards alineadas correctamente
- [ ] **Responsive**: Se adapta a mobile/tablet
- [ ] **Animaciones**: Efectos de entrada funcionando

---

## 📰 5. SECCIÓN BLOG/NOTICIAS

### **"Noticias" - 3 artículos detectados**
1. [ ] **"Consejos para tu entrevista técnica"**:
   - [ ] Fecha: 30/7/2025
   - [ ] Descripción: "Aprende a prepararte con éxito..."
   - [ ] Botón "Read more →": Funcional
2. [ ] **"Evento de Networking en Madrid"**:
   - [ ] Fecha: 25/7/2025
   - [ ] Descripción: "Únete a nuestro próximo evento..."
   - [ ] Botón "Read more →": Funcional
3. [ ] **"Lanzamiento de nuestra nueva plataforma"**:
   - [ ] Fecha: 20/7/2025
   - [ ] Descripción: "Presentamos Bubbletalents 2.0..."
   - [ ] Botón "Read more →": Funcional

### **Funcionalidades Blog**
- [ ] **Ordenación**: Artículos por fecha descendente
- [ ] **Imágenes**: Thumbnails cargan correctamente
- [ ] **Links**: Redirecciones a artículos completos

---

## 🗣️ 6. CHATBOT & ELEMENTOS INTERACTIVOS

### **Widget ChatBot**
- [ ] **Icono ChatBot (💬)**: Visible en pantalla
- [ ] **Clic ChatBot**: Modal o panel se abre
- [ ] **Conversación**: Responde a mensajes básicos
- [ ] **Posición**: Sticky en esquina (típicamente bottom-right)

---

## 🔗 7. FOOTER

### **Links Footer**
- [ ] **Terms & Conditions**: Redirección funcional
- [ ] **Home**: Vuelve a homepage
- [ ] **Services**: Página de servicios
- [ ] **Project**: Portfolio/casos de estudio
- [ ] **About Us**: Información de la empresa
- [ ] **Contact**: Página de contacto
- [ ] **Privacy Policy**: Política de privacidad

### **Copyright**
- [ ] **Copyright**: "© 2025 Bubblegum.agency. All rights reserved."
- [ ] **Links legales**: Funcionan correctamente

---

## 🔧 8. ASPECTOS TÉCNICOS

### **Performance**
- [ ] **Tiempo carga**: Homepage <3 segundos
- [ ] **Imágenes**: Lazy loading funcionando
- [ ] **Fuentes**: Google Fonts cargan correctamente
- [ ] **AOS Animations**: Efectos suaves sin lag

### **SEO & Meta**
- [ ] **Title**: "Bubblegum.agency - Job Portal"
- [ ] **Description**: Meta description presente
- [ ] **Open Graph**: Meta tags configuradas
- [ ] **Favicon**: Visible en pestaña browser

### **Responsive Design**
- [ ] **Desktop (1280px+)**: Layout completo
- [ ] **Tablet (768-1024px)**: Adaptación correcta
- [ ] **Mobile (320-767px)**: Menú hamburguesa funcional
- [ ] **Touch**: Botones táctiles responsive

### **Accessibility**
- [ ] **Focus**: Tab navigation funcional
- [ ] **Alt texts**: Imágenes con atributos alt
- [ ] **Contrast**: Colores cumplen WCAG
- [ ] **Screen readers**: Estructura semántica

---

## ✅ CRITERIOS ÉXITO HOMEPAGE

### **CRÍTICOS (Must Have)**
- [ ] Navegación principal 100% funcional
- [ ] CTAs principales ("Soy Candidato/a", "Soy de Bubble") working
- [ ] Jobs grid con redirecciones correctas
- [ ] Responsive design en todos los breakpoints
- [ ] Tiempo carga <3 segundos

### **IMPORTANTES (Should Have)**
- [ ] ChatBot responde básicamente
- [ ] Animaciones AOS sin errores
- [ ] Footer links funcionan
- [ ] Blog articles accesibles
- [ ] SEO meta tags correctos

### **DESEABLES (Nice to Have)**
- [ ] Language toggle persistente
- [ ] Hover effects smooth
- [ ] Advanced animations
- [ ] Analytics tracking
- [ ] Microinteracciones

---

**📝 PRÓXIMO PASO**: Testear cada elemento de forma sistemática

**⏱️ Tiempo estimado**: 20-30 minutos para testing completo homepage

**🎯 Objetivo**: 100% funcionalidades homepage antes de pasar a auth flows
