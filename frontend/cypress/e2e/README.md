# Cypress E2E Testing Suite - Bubble of Talents

## 📋 Descripción General

Suite completa de pruebas E2E para la plataforma **Bubble of Talents** que cubre todas las funcionalidades documentadas en `FUNCIONALIDADES_COMPLETAS_TESTING.md`.

## 🏗️ Arquitectura de Pruebas

### **Estructura de Archivos**
```
frontend/cypress/
├── e2e/                          # Pruebas E2E
│   ├── 01-authentication.cy.ts   # Autenticación y navegación
│   ├── 02-cv-management.cy.ts    # Gestión de CVs e IA
│   ├── 03-job-search-application.cy.ts  # Búsqueda y aplicación
│   ├── 04-dashboards.cy.ts       # Dashboards especializados
│   └── 05-advanced-features.cy.ts # Funcionalidades avanzadas
├── fixtures/                     # Datos de prueba
│   └── test-cvs/                 # CVs reales en PDF
├── support/                      # Configuración y helpers
│   ├── commands.ts              # Comandos personalizados
│   └── e2e.ts                   # Configuración global
└── config.ts                     # Configuración principal
```

## 🚀 Inicio Rápido

### **1. Instalar dependencias**
```bash
cd frontend
pnpm install
```

### **2. Configurar variables de entorno**
Crear archivo `.env-cypress.local`:
```bash
# Copiar desde .env-cypress.example
cp .env-cypress.example .env-cypress.local

# Editar con credenciales reales de testing
CYPRESS_ADMIN_EMAIL=tu-admin@test.com
CYPRESS_ADMIN_PASSWORD=tu-password-admin
# ... otras variables
```

### **3. Ejecutar seed de datos**
```bash
# Ejecutar seed SQL para datos de prueba
mysql -u root bubbleTalents_DB < cypress_e2e_seed_final_real.sql
```

### **4. Ejecutar pruebas**

#### **Modo interactivo (recomendado para desarrollo)**
```bash
cd frontend
pnpm exec cypress open
```

#### **Modo headless (CI/CD)**
```bash
cd frontend
pnpm exec cypress run
```

#### **Ejecutar suite específica**
```bash
# Solo autenticación
pnpm exec cypress run --spec "cypress/e2e/01-authentication.cy.ts"

# Solo gestión de CVs
pnpm exec cypress run --spec "cypress/e2e/02-cv-management.cy.ts"

# Todas las pruebas
pnpm exec cypress run --spec "cypress/e2e/**/*.cy.ts"
```

## 📊 Cobertura de Pruebas

### **✅ Funcionalidades Cubiertas**

| Módulo | Tests | Estado |
|--------|-------|--------|
| **Autenticación** | 10 tests | ✅ Completo |
| **CV Management** | 10 tests | ✅ Completo |
| **Job Search** | 12 tests | ✅ Completo |
| **Dashboards** | 12 tests | ✅ Completo |
| **Advanced Features** | 12 tests | ✅ Completo |

**Total: 56 tests E2E**

### **🎯 Escenarios de Prueba**

#### **1. Autenticación y Navegación**
- ✅ Login candidato/staff con credenciales válidas
- ✅ Login con credenciales inválidas
- ✅ Protección de rutas sin autenticación
- ✅ Logout desde cualquier dashboard
- ✅ Navegación homepage completa
- ✅ Diseño responsive móvil
- ✅ Selector de idioma
- ✅ Chatbot funcional
- ✅ OAuth Google/LinkedIn
- ✅ Permisos por roles

#### **2. Gestión de CVs e IA**
- ✅ Upload CV PDF real
- ✅ Procesamiento automático con IA
- ✅ Validación manual de datos
- ✅ Fallback cuando IA falla
- ✅ Procesamiento batch múltiple
- ✅ Validación formatos de archivo
- ✅ Matching candidato-empleo
- ✅ Exportación datos procesados
- ✅ Importación LinkedIn
- ✅ Privacidad y consentimiento

#### **3. Búsqueda y Aplicación**
- ✅ Listado empleos homepage
- ✅ Navegación página empleos completa
- ✅ Búsqueda por términos
- ✅ Filtros avanzados
- ✅ Detalle completo de empleo
- ✅ Sistema favoritos
- ✅ Aplicación sin login (redirección)
- ✅ Flujo aplicación completo
- ✅ Aplicación con CV adjunto
- ✅ Prevención aplicaciones duplicadas
- ✅ Seguimiento estado aplicación
- ✅ Exportación aplicaciones

#### **4. Dashboards Especializados**
- ✅ Dashboard candidato completo
- ✅ Dashboard HR/Admin gestión
- ✅ Dashboard recruiter pipeline
- ✅ Gestión estados candidatos
- ✅ Programación entrevistas
- ✅ Notificaciones dashboards
- ✅ Estadísticas avanzadas
- ✅ Panel administración
- ✅ Creación gestión empleos
- ✅ Dashboard gerencial KPIs
- ✅ Verificación permisos roles
- ✅ Exportación datos dashboards

#### **5. Funcionalidades Avanzadas**
- ✅ Sistema IA matching inteligente
- ✅ Insights predictivos candidatos
- ✅ Integración calendario completa
- ✅ Recordatorios automáticos
- ✅ Notificaciones multi-canal
- ✅ OAuth social login
- ✅ Chatbot IA avanzado
- ✅ Templates email dinámicos
- ✅ Analytics reporting avanzado
- ✅ Optimizaciones performance
- ✅ Seguridad avanzada
- ✅ Integración herramientas externas

## 🔧 Configuración Detallada

### **Variables de Entorno Requeridas**

```bash
# API Configuration
CYPRESS_API_BASEURL=http://localhost:8000/api

# Admin Credentials (from bubbeTalents_DB.sql)
CYPRESS_ADMIN_EMAIL=copiamedion@gmail.com
CYPRESS_ADMIN_PASSWORD=AdminMaster2025!

# Recruiter Credentials
CYPRESS_RECRUITER_EMAIL=recruiter.test@hotmail.com
CYPRESS_RECRUITER_PASSWORD=RecruiterTest2025!

# Candidate Credentials
CYPRESS_CANDIDATE_EMAIL=lucia.alvarez@mail.com
CYPRESS_CANDIDATE_PASSWORD=test-password-123!

# Database Configuration
CYPRESS_DB_HOST=localhost
CYPRESS_DB_NAME=bubbeTalents_DB
CYPRESS_DB_USER=root
CYPRESS_DB_PASSWORD=

# Test Data IDs
CYPRESS_TEST_JOB_ID=1
CYPRESS_TEST_CANDIDATE_ID=1
CYPRESS_TEST_APPLICATION_ID=1
```

### **Datos de Prueba Reales**

#### **CVs PDF Reales** (ubicación: `frontend/cypress/fixtures/test-cvs/`)
- `Curriculum Vitae - daniel-alvarez-DevWeb.pdf`
- `Curriculum Vitae - javier-rodriguez-mkt.pdf`
- `Curriculum Vitae - laura-gomez-mkt.pdf`
- `Curriculum Vitae - pablo-torres-mkt.pdf`

#### **Base de Datos** (archivo: `cypress_e2e_seed_final_real.sql`)
- **10 Candidatos** con perfiles completos
- **10 Empleos** de Bubblegum Agency
- **Aplicaciones** de prueba
- **Usuarios staff** (admin, recruiters)
- **Datos relacionales** completos

## 🛠️ Comandos Personalizados

### **Comandos de Autenticación**
```typescript
cy.loginAsCandidate(email, password)
cy.loginAsStaff(email, password)
cy.logout()
```

### **Comandos de CV Management**
```typescript
cy.uploadCV('filename.pdf')
cy.validateCVData(expectedData)
cy.mockAIResponse(responseData)
```

### **Comandos de Navegación**
```typescript
cy.searchJobs(query)
cy.filterJobs(filters)
cy.applyToJob(jobId)
cy.verifyCandidateDashboard()
cy.verifyHRDashboard()
```

### **Comandos de Notificaciones**
```typescript
cy.verifyNotification(notificationData)
cy.clearNotifications()
cy.mockEmailService()
```

## 📈 Reportes y Resultados

### **Ejecutar con Reportes**
```bash
# Con reportes HTML
pnpm exec cypress run --reporter html

# Con videos de ejecución
pnpm exec cypress run --record --key your-record-key

# Con screenshots en fallos
pnpm exec cypress run --screenshot-on-run-failure
```

### **Análisis de Cobertura**
```bash
# Instalar plugin de cobertura
pnpm add -D @cypress/code-coverage

# Ejecutar con cobertura
pnpm exec cypress run --env coverage=true
```

## 🔄 CI/CD Integration

### **GitHub Actions Example**
```yaml
name: E2E Tests
on: [push, pull_request]
jobs:
  cypress-run:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: cypress-io/github-action@v5
        with:
          working-directory: frontend
          start: pnpm dev
          wait-on: 'http://localhost:3002'
```

### **Docker para Testing**
```dockerfile
FROM cypress/included:13.6.0
WORKDIR /app
COPY . .
RUN npm install
CMD ["npx", "cypress", "run"]
```

## 🐛 Debugging y Troubleshooting

### **Debug Mode**
```bash
# Ejecutar en modo debug
pnpm exec cypress run --browser chrome --headed

# Con dev tools abiertos
DEBUG=cypress:* pnpm exec cypress run
```

### **Comandos Útiles para Debug**
```bash
# Ver logs detallados
cy.window().then((win) => console.log(win))

# Pausar ejecución
cy.pause()

# Tomar screenshot en cualquier punto
cy.screenshot('debug-point')

# Ver estado de red
cy.intercept('**', (req) => console.log(req))
```

## 📚 Mejores Prácticas

### **✅ Recomendaciones**
- ✅ Usar datos reales de BD (no mocks)
- ✅ Ejecutar seed antes de cada suite
- ✅ Mantener independencia entre tests
- ✅ Usar comandos personalizados reutilizables
- ✅ Tomar screenshots en puntos críticos
- ✅ Verificar estados de BD después de operaciones
- ✅ Probar flujos completos end-to-end
- ✅ Incluir validaciones de seguridad

### **❌ Evitar**
- ❌ Dependencias entre tests
- ❌ Datos hardcodeados en tests
- ❌ Esperas fijas (`cy.wait(5000)`)
- ❌ Selectores frágiles
- ❌ Tests que requieren estado específico
- ❌ Ignorar flujos de error
- ❌ No validar respuestas de API

## 🎯 Próximos Pasos

### **Mejoras Planificadas**
- [ ] Tests de carga con múltiples usuarios
- [ ] Tests de stress para APIs
- [ ] Tests de accesibilidad (WCAG)
- [ ] Tests de performance (Lighthouse)
- [ ] Tests de seguridad automatizados
- [ ] Integración con servicios externos
- [ ] Tests de mobile nativo
- [ ] Reportes avanzados de métricas

### **Mantenimiento**
- 🔄 Actualizar datos de prueba regularmente
- 🔄 Revisar cobertura de tests mensualmente
- 🔄 Actualizar dependencias de Cypress
- 🔄 Monitorear flakiness de tests
- 🔄 Documentar nuevos casos de prueba

---

## 📞 Soporte

Para preguntas sobre las pruebas E2E:
- 📧 Email: soporte@bubbletalents.com
- 📱 Slack: #testing-channel
- 📖 Docs: [Documentación Técnica](./docs/)

**⚠️ IMPORTANTE**: Estas pruebas están diseñadas para usar datos reales y no mocks. Asegurarse de tener el entorno de testing correctamente configurado antes de ejecutar.
