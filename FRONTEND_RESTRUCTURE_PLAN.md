# Frontend Restructure Plan - Bubble of Talents

## 🎯 OBJETIVO
Reestructurar el frontend hacia una arquitectura escalable, mantenible y organizada por features/módulos.

## 📊 ANÁLISIS SITUACIÓN ACTUAL

### Problemas Identificados:
- ❌ **Duplicación**: Dashboard en `pages/` y `components/`
- ❌ **Inconsistencia**: Archivos similares en ubicaciones diferentes  
- ❌ **Escalabilidad**: Estructura flat difícil de mantener
- ❌ **Imports**: Rutas largas y confusas
- ❌ **Testing**: Tests dispersos sin organización

### Archivos Problemáticos:
```
# DUPLICADOS PARA ELIMINAR:
pages/dashboard/HRDashboard.tsx          → Duplicado de components/dashboard/
pages/dashboard/RecruiterDashboard.tsx   → Duplicado de components/dashboard/
TestApiConnection.tsx.new                → Backup innecesario
RecruiterDashboardBackup.tsx            → Backup innecesario

# COMPONENTES MAL UBICADOS:
pages/ApiTester.tsx                     → Debería estar en shared/components/debug/
pages/CandidatePage.tsx                 → Debería estar en features/candidates/
components/ChatBotManage.tsx            → Debería estar en features/ai/
```

## 🏗️ NUEVA ARQUITECTURA PROPUESTA

```
frontend/src/
├── app/                                 # 🔧 Configuración aplicación
│   ├── App.tsx                         # Componente raíz
│   ├── router.tsx                      # Configuración React Router
│   ├── providers.tsx                   # Context providers
│   └── store/                          # Estado global (Zustand/Redux)
│
├── shared/                             # 🔄 Código compartido
│   ├── components/
│   │   ├── ui/                        # Componentes base (shadcn/ui)
│   │   │   ├── button.tsx
│   │   │   ├── input.tsx
│   │   │   ├── modal.tsx
│   │   │   └── ...
│   │   ├── layout/                    # Layout components
│   │   │   ├── Header.tsx
│   │   │   ├── Footer.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   └── Layout.tsx
│   │   ├── forms/                     # Componentes formulario globales
│   │   │   ├── FormWrapper.tsx
│   │   │   ├── ErrorBoundary.tsx
│   │   │   └── LoadingSpinner.tsx
│   │   └── navigation/                # Navegación global
│   │       ├── Breadcrumbs.tsx
│   │       ├── UserMenu.tsx
│   │       └── ProtectedRoute.tsx
│   ├── hooks/                         # Custom hooks compartidos
│   │   ├── useAuth.ts
│   │   ├── useLocalStorage.ts
│   │   ├── useDebounce.ts
│   │   └── useApi.ts
│   ├── utils/                         # Utilidades
│   │   ├── api.ts
│   │   ├── formatters.ts
│   │   ├── validators.ts
│   │   └── constants.ts
│   ├── types/                         # Tipos TypeScript globales
│   │   ├── api.ts
│   │   ├── user.ts
│   │   └── common.ts
│   └── services/                      # Servicios externos
│       ├── apiClient.ts
│       ├── authService.ts
│       └── storageService.ts
│
├── features/                          # 🎯 Módulos por funcionalidad
│   │
│   ├── auth/                         # 🔐 Sistema autenticación
│   │   ├── components/
│   │   │   ├── LoginForm.tsx
│   │   │   ├── RegisterForm.tsx
│   │   │   ├── SocialLoginButtons.tsx
│   │   │   └── PasswordReset.tsx
│   │   ├── pages/
│   │   │   ├── LoginPage.tsx
│   │   │   ├── RegisterPage.tsx
│   │   │   ├── CandidateAuthPage.tsx
│   │   │   └── StaffLogin.tsx
│   │   ├── hooks/
│   │   │   ├── useLogin.ts
│   │   │   ├── useRegister.ts
│   │   │   └── usePasswordReset.ts
│   │   ├── services/
│   │   │   ├── authApi.ts
│   │   │   └── oauthService.ts
│   │   └── types/
│   │       ├── auth.ts
│   │       └── user.ts
│   │
│   ├── dashboard/                    # 📊 Sistema dashboards
│   │   ├── components/
│   │   │   ├── shared/               # Componentes compartidos dashboards
│   │   │   │   ├── DashboardHeader.tsx
│   │   │   │   ├── StatsCard.tsx
│   │   │   │   ├── FilterPanel.tsx
│   │   │   │   └── StatusBadge.tsx
│   │   │   ├── candidate/            # Dashboard candidato
│   │   │   │   ├── ProfileSection.tsx
│   │   │   │   ├── ApplicationsTable.tsx
│   │   │   │   └── NotificationsPanel.tsx
│   │   │   ├── hr/                   # Dashboard HR
│   │   │   │   ├── CandidatesList.tsx
│   │   │   │   ├── RecruitmentMetrics.tsx
│   │   │   │   └── AIInsights.tsx
│   │   │   ├── recruiter/            # Dashboard recruiter
│   │   │   │   ├── JobPipeline.tsx
│   │   │   │   ├── CandidateCards.tsx
│   │   │   │   └── InterviewScheduler.tsx
│   │   │   └── manager/              # Dashboard manager
│   │   │       ├── TeamOverview.tsx
│   │   │       ├── ApprovalQueue.tsx
│   │   │       └── BudgetTracking.tsx
│   │   ├── pages/
│   │   │   ├── CandidateDashboard.tsx
│   │   │   ├── HRDashboard.tsx
│   │   │   ├── RecruiterDashboard.tsx
│   │   │   ├── ManagerDashboard.tsx
│   │   │   └── AdminDashboard.tsx
│   │   ├── hooks/
│   │   │   ├── useDashboardData.ts
│   │   │   ├── useMetrics.ts
│   │   │   └── useFilters.ts
│   │   └── services/
│   │       ├── metricsApi.ts
│   │       └── dashboardApi.ts
│   │
│   ├── candidates/                   # 👥 Gestión candidatos
│   │   ├── components/
│   │   │   ├── forms/
│   │   │   │   ├── CandidateForm.tsx
│   │   │   │   ├── CVUpload.tsx
│   │   │   │   ├── CVValidationModal.tsx
│   │   │   │   └── ManualCVForm.tsx
│   │   │   ├── profile/
│   │   │   │   ├── ProfileEditor.tsx
│   │   │   │   ├── SkillsSection.tsx
│   │   │   │   ├── ExperienceSection.tsx
│   │   │   │   └── ChangePassword.tsx
│   │   │   └── search/
│   │   │       ├── CandidateSearch.tsx
│   │   │       ├── FiltersPanel.tsx
│   │   │       └── ResultsList.tsx
│   │   ├── pages/
│   │   │   ├── CandidateProfile.tsx
│   │   │   ├── CandidatesList.tsx
│   │   │   └── ApplicationDetails.tsx
│   │   ├── hooks/
│   │   │   ├── useCandidateProfile.ts
│   │   │   ├── useCVProcessing.ts
│   │   │   └── useCandidateSearch.ts
│   │   ├── services/
│   │   │   ├── candidateApi.ts
│   │   │   └── cvProcessingApi.ts
│   │   └── types/
│   │       ├── candidate.ts
│   │       ├── cv.ts
│   │       └── application.ts
│   │
│   ├── jobs/                         # 💼 Gestión trabajos
│   │   ├── components/
│   │   │   ├── JobCard.tsx
│   │   │   ├── JobFilters.tsx
│   │   │   ├── JobDetails.tsx
│   │   │   ├── ApplicationForm.tsx
│   │   │   └── JobHero.tsx
│   │   ├── pages/
│   │   │   ├── JobsList.tsx
│   │   │   ├── JobDetailsPage.tsx
│   │   │   └── ApplyPage.tsx
│   │   ├── hooks/
│   │   │   ├── useJobs.ts
│   │   │   ├── useJobDetails.ts
│   │   │   └── useJobApplication.ts
│   │   └── services/
│   │       ├── jobsApi.ts
│   │       └── applicationApi.ts
│   │
│   ├── ai/                          # 🤖 Funcionalidades IA
│   │   ├── components/
│   │   │   ├── ChatBot.tsx
│   │   │   ├── ChatBotManage.tsx
│   │   │   ├── AIInsights.tsx
│   │   │   ├── MatchingResults.tsx
│   │   │   └── PreScreening.tsx
│   │   ├── services/
│   │   │   ├── openaiService.ts
│   │   │   ├── chatbotService.ts
│   │   │   ├── matchingService.ts
│   │   │   └── screeningService.ts
│   │   └── types/
│   │       ├── chatbot.ts
│   │       ├── matching.ts
│   │       └── aiInsights.ts
│   │
│   ├── notifications/               # 🔔 Sistema notificaciones
│   │   ├── components/
│   │   │   ├── NotificationCenter.tsx
│   │   │   ├── NotificationItem.tsx
│   │   │   ├── PreferencesPanel.tsx
│   │   │   └── EmailTemplates.tsx
│   │   ├── hooks/
│   │   │   ├── useNotifications.ts
│   │   │   └── usePreferences.ts
│   │   └── services/
│   │       ├── notificationApi.ts
│   │       └── emailService.ts
│   │
│   └── admin/                       # ⚙️ Panel administración
│       ├── components/
│       │   ├── UserManagement.tsx
│       │   ├── SystemSettings.tsx
│       │   ├── DatabaseViewer.tsx
│       │   └── APITester.tsx
│       ├── pages/
│       │   └── AdminPanel.tsx
│       └── services/
│           └── adminApi.ts
│
├── assets/                          # 📁 Recursos estáticos
│   ├── images/
│   ├── icons/
│   ├── fonts/
│   └── styles/
│       ├── globals.css
│       ├── components.css
│       └── themes/
│
└── __tests__/                       # 🧪 Tests globales
    ├── setup.ts
    ├── mocks/
    ├── utils/
    └── fixtures/
```

## 🔧 CONFIGURACIÓN TÉCNICA

### Path Mapping (tsconfig.json):
```json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@/*": ["src/*"],
      "@/shared/*": ["src/shared/*"],
      "@/features/*": ["src/features/*"],
      "@/app/*": ["src/app/*"],
      "@/assets/*": ["src/assets/*"]
    }
  }
}
```

### Vite Config (vite.config.ts):
```typescript
export default defineConfig({
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@/shared': path.resolve(__dirname, './src/shared'),
      '@/features': path.resolve(__dirname, './src/features'),
      '@/app': path.resolve(__dirname, './src/app'),
      '@/assets': path.resolve(__dirname, './src/assets')
    }
  }
})
```

## 📋 PLAN DE MIGRACIÓN

### **FASE 1: Setup Base (2-3 días)**
1. ✅ Crear estructura de carpetas nueva
2. ✅ Configurar path mapping 
3. ✅ Migrar componentes UI base a `/shared/components/ui/`
4. ✅ Actualizar imports en archivos existentes
5. ✅ Configurar testing por feature

### **FASE 2: Migración Módulos (1 semana)**

#### Día 1-2: Auth Module
- Migrar `/pages/auth/` → `/features/auth/pages/`
- Migrar `/components/auth/` → `/features/auth/components/`
- Crear hooks específicos auth
- Consolidar servicios OAuth

#### Día 3-4: Dashboard Module  
- **CRÍTICO**: Eliminar duplicaciones
- Consolidar todos los dashboards en `/features/dashboard/`
- Crear componentes compartidos (DashboardHeader, StatsCard, etc.)
- Implementar lazy loading por dashboard

#### Día 5: Candidates Module
- Migrar formularios y CV processing
- Agrupar componentes de perfil
- Crear hooks para gestión candidatos

#### Día 6-7: Jobs + AI Modules
- Migrar gestión de trabajos
- Consolidar funcionalidades IA (ChatBot, matching, etc.)
- Crear servicios específicos por feature

### **FASE 3: Optimización (3-4 días)**
1. ✅ Eliminar archivos duplicados y backups
2. ✅ Implementar code splitting por feature
3. ✅ Configurar tests por módulo
4. ✅ Documentar cada feature
5. ✅ Optimizar bundle sizes
6. ✅ Validar performance

## 🎯 BENEFICIOS ESPERADOS

### **Organización:**
- ✅ **Código agrupado** por funcionalidad
- ✅ **Imports claros** con alias configurados
- ✅ **Tests organizados** junto al código

### **Performance:**
- ✅ **Lazy loading** por feature
- ✅ **Tree shaking** mejorado  
- ✅ **Bundle splitting** optimizado
- ✅ **Reduced bundle sizes**

### **Desarrollador:**
- ✅ **Facilidad navegación** en IDE
- ✅ **Debugging localizado** por feature
- ✅ **Refactoring seguro** con tipos TypeScript
- ✅ **Team scaling** por módulos independientes

### **Mantenimiento:**
- ✅ **Eliminación duplicaciones**
- ✅ **Estructura predecible**
- ✅ **Adición features** simplificada
- ✅ **Testing coverage** mejorado

## 🚨 RIESGOS Y MITIGACIÓN

### **Riesgos:**
- **Tiempo migración**: 1-2 semanas sin features nuevas
- **Breaking changes**: Posibles errores durante migración
- **Learning curve**: Equipo debe adaptarse a nueva estructura

### **Mitigación:**
- **Migración gradual**: Por módulos, no todo a la vez
- **Testing exhaustivo**: Validar cada módulo migrado
- **Documentación**: Guías claras de la nueva estructura
- **Rollback plan**: Mantener backup de estructura actual

## ✅ CRITERIOS DE ÉXITO

1. **Eliminación duplicaciones**: 0 archivos duplicados
2. **Imports optimizados**: Todos usan alias `/features/`, `/shared/`
3. **Bundle size**: Reducción 20-30% por lazy loading
4. **Test coverage**: Mantener >80% con tests organizados
5. **Build time**: Reducción tiempo compilación
6. **Team feedback**: Aprobación equipo desarrollo nueva estructura

## 📚 DOCUMENTACIÓN

Crear documentación para:
- **Feature structure guide**: Cómo organizar nuevas features
- **Import conventions**: Reglas para imports entre módulos  
- **Component guidelines**: Cuándo crear shared vs feature component
- **Testing standards**: Estructura tests por feature
- **Migration guide**: Para futuras reestructuraciones

---

**STATUS**: 📋 **PROPUESTA LISTA PARA IMPLEMENTACIÓN**
**ESTIMACIÓN**: 1-2 semanas
**PRIORIDAD**: 🔴 **ALTA** - Fundación para escalabilidad futura
