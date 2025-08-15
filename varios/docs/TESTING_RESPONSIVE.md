# Tests de Responsividad - Bubble of Talents

Este documento explica cómo usar los tests automatizados de responsividad implementados en el proyecto.

## 🎯 Objetivo

Los tests de responsividad verifican que la aplicación funcione correctamente en diferentes tamaños de pantalla y dispositivos, asegurando una experiencia de usuario óptima en:

- 📱 Móviles (320px - 425px)
- 📱 Tablets (768px - 1024px) 
- 💻 Desktop (1024px - 1440px)
- 🖥️ Pantallas grandes (1920px+)

## 🛠️ Herramientas Utilizadas

### 1. **Jest + Testing Library** (Tests Unitarios)
- Verifica comportamiento responsive de componentes
- Simula diferentes viewports
- Valida que elementos sean visibles y accesibles

### 2. **Playwright** (Tests E2E)
- Tests en navegadores reales
- Múltiples dispositivos y viewports
- Capturas de pantalla automáticas

### 3. **Puppeteer** (Tests Visuales)
- Capturas de pantalla en diferentes resoluciones
- Detección de scroll horizontal
- Validación de elementos visibles

## 🚀 Cómo Ejecutar los Tests

### Tests Rápidos (Jest)
```bash
# Desde la raíz del proyecto
npm run test:responsive

# O desde frontend/
cd frontend && npm run test:responsive
```

### Tests Visuales (Puppeteer)
```bash
# Desde la raíz del proyecto
npm run test:responsive:visual
```

### Tests Completos (Playwright)
```bash
# Tests E2E completos
npm run test:e2e

# Con interfaz visual
npm run test:e2e:ui

# Ver reportes
npm run test:e2e:report
```

### Tests Completos de Responsividad
```bash
# Ejecuta todos los tests de responsividad
npm run test:responsive:full
```

## 📊 Breakpoints Testados

| Dispositivo | Ancho | Alto | Casos de Uso |
|-------------|-------|------|--------------|
| Mobile Small | 320px | 568px | iPhone SE, móviles pequeños |
| Mobile Large | 425px | 768px | iPhone 12, móviles grandes |
| Tablet | 768px | 1024px | iPad, tablets |
| Laptop | 1024px | 768px | Laptops, notebooks |
| Desktop | 1440px | 900px | Monitores estándar |
| Wide Screen | 1920px | 1080px | Monitores grandes |

## 📋 Qué Se Verifica

### ✅ Criterios de Éxito

1. **Sin Scroll Horizontal**
   - El contenido no debe desbordarse horizontalmente
   - `scrollWidth <= clientWidth`

2. **Contenido Principal Visible**
   - Elementos `main`, `section`, `.container` deben ser visibles
   - Altura y ancho > 0

3. **Sección Hero Centrada**
   - La sección hero debe estar centrada verticalmente
   - Título principal visible en todos los breakpoints

4. **Navegación Funcional**
   - Menús y enlaces accesibles
   - Botones clickeables en todos los tamaños

5. **Sin Errores JavaScript**
   - No debe haber errores de consola
   - Funcionalidad completa en todos los viewports

### 🔍 Páginas Testadas

- **Home** (`/`) - Página principal con hero section
- **Jobs** (`/jobs`) - Lista de empleos
- **Login** (`/auth/login`) - Formulario de inicio de sesión
- **Register** (`/auth/register`) - Formulario de registro

## 📸 Capturas de Pantalla

Las capturas se guardan automáticamente en:
```
test-results/screenshots/responsive/
├── home-mobile.png
├── home-tablet.png
├── home-desktop.png
├── jobs-mobile.png
└── ...
```

## 📊 Reportes

### Reporte JSON
```bash
test-results/screenshots/responsive/report.json
```

### Reporte HTML (Playwright)
```bash
playwright-report/index.html
```

## 🐛 Interpretación de Errores

### Error: "Scroll horizontal detectado"
```javascript
{
  "issue": "Scroll horizontal detectado",
  "scrollWidth": 1200,
  "clientWidth": 320
}
```
**Solución**: Revisar CSS, usar `overflow-x: hidden` o ajustar anchos.

### Error: "Contenido principal no visible"
```javascript
{
  "issue": "Contenido principal no visible"
}
```
**Solución**: Verificar que elementos principales tengan display visible.

### Error: "Sección hero no visible"
```javascript
{
  "issue": "Sección hero no visible"
}
```
**Solución**: Revisar CSS de la sección hero, asegurar altura mínima.

## 🔧 Configuración

### Jest (frontend/src/__tests__/responsive.test.tsx)
```typescript
// Helper para cambiar viewport
const setViewport = (width: number, height: number = 768) => {
  Object.defineProperty(window, 'innerWidth', {
    writable: true,
    configurable: true,
    value: width
  });
  // ...
};
```

### Playwright (playwright.config.ts)
```typescript
projects: [
  {
    name: 'Mobile Small',
    use: {
      viewport: { width: 320, height: 568 }
    },
  },
  // ...
]
```

### Puppeteer (scripts/responsive-test.js)
```javascript
const BREAKPOINTS = {
  mobile: { width: 320, height: 568, name: 'Mobile' },
  tablet: { width: 768, height: 1024, name: 'Tablet' },
  // ...
};
```

## 🚨 Troubleshooting

### El servidor no inicia
```bash
# Asegúrate de que el frontend esté corriendo
cd frontend && npm run dev
```

### Tests fallan por timeout
```bash
# Aumentar timeout en playwright.config.ts
timeout: 120 * 1000, // 2 minutos
```

### Capturas no se generan
```bash
# Verificar permisos de escritura
mkdir -p test-results/screenshots/responsive
```

## 📝 Agregar Nuevos Tests

### 1. Nuevo Breakpoint
```typescript
// En responsive.test.tsx
it('should display correctly on new viewport (1600px)', () => {
  setViewport(1600, 900);
  // ... test logic
});
```

### 2. Nueva Página
```javascript
// En responsive-test.js
const PAGES_TO_TEST = [
  // ...existing pages
  { url: '/nueva-pagina', name: 'nueva-pagina' }
];
```

### 3. Nueva Validación
```typescript
// En responsive.test.tsx
it('should maintain accessible font sizes', () => {
  // ... validation logic
});
```

## 🎯 Mejores Prácticas

1. **Ejecutar antes de cada deploy**
2. **Revisar capturas de pantalla regularmente**
3. **Mantener breakpoints actualizados**
4. **Documentar nuevos casos de uso**
5. **Integrar en CI/CD pipeline**

## 📈 Métricas de Éxito

- ✅ **100% de tests pasando** en todos los breakpoints
- ✅ **Sin scroll horizontal** en ningún viewport
- ✅ **Contenido visible** en todos los tamaños
- ✅ **Carga < 5 segundos** en todos los dispositivos
- ✅ **Sin errores JavaScript** reportados

---

*Documentación actualizada: Agosto 2025*
