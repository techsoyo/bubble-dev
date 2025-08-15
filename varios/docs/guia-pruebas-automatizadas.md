# Guía de Pruebas Automatizadas - React TypeScript + PHP

## 🎯 Frontend (React + TypeScript)

### Análisis de Código y Linting
```bash
# ESLint - Detecta errores y problemas de estilo en código TS/JS
npx eslint src/ --ext .ts,.tsx

# ESLint con auto-corrección
npx eslint src/ --ext .ts,.tsx --fix

# TypeScript compiler check - Verifica tipos sin compilar
npx tsc --noEmit

# Prettier - Formateo de código
npx prettier --check src/

# Prettier con auto-corrección
npx prettier --write src/
```

### Pruebas Unitarias y de Integración
```bash
# Jest - Pruebas unitarias estándar
npm test

# Jest en modo watch (se ejecuta automáticamente al cambiar archivos)
npm test -- --watch

# Jest con coverage (cobertura de código)
npm test -- --coverage

# Testing Library - Pruebas de componentes React
npm test -- --testPathPattern=components

# Pruebas específicas por archivo
npm test -- Button.test.tsx
```

### Pruebas End-to-End
```bash
# Playwright - Pruebas E2E modernas
npx playwright test

# Playwright con interfaz gráfica
npx playwright test --ui

# Playwright solo en Chrome
npx playwright test --project=chromium

# Cypress - Alternativa popular para E2E
npx cypress run

# Cypress con interfaz gráfica
npx cypress open
```

### Análisis de Rendimiento
```bash
# Bundle analyzer - Analiza el tamaño del bundle
npm run build && npx webpack-bundle-analyzer build/static/js/*.js

# Lighthouse CI - Auditoría de rendimiento automatizada
npm install -g @lhci/cli
lhci autorun
```

## 🔧 Backend (PHP)

### Análisis de Código y Estilo
```bash
# PHP_CodeSniffer - Estándares de código PHP
composer require --dev squizlabs/php_codesniffer
./vendor/bin/phpcs --standard=PSR12 src/

# PHP CS Fixer - Auto-corrección de estilo
composer require --dev friendsofphp/php-cs-fixer
./vendor/bin/php-cs-fixer fix src/

# PHPStan - Análisis estático de tipos
composer require --dev phpstan/phpstan
./vendor/bin/phpstan analyse src/

# PHPMD - Detector de mess/problemas de diseño
composer require --dev phpmd/phpmd
./vendor/bin/phpmd src/ text cleancode,codesize,controversial,design,naming,unusedcode
```

### Pruebas Unitarias
```bash
# PHPUnit - Framework de testing estándar
composer require --dev phpunit/phpunit

# Ejecutar todas las pruebas
./vendor/bin/phpunit

# Pruebas con coverage
./vendor/bin/phpunit --coverage-html coverage/

# Pruebas específicas
./vendor/bin/phpunit tests/UserTest.php

# Pruebas en modo watch (con herramientas adicionales)
composer require --dev spatie/phpunit-watcher
./vendor/bin/phpunit-watcher watch
```

### Pruebas de API
```bash
# Pest - Framework de testing moderno para PHP
composer require --dev pestphp/pest
./vendor/bin/pest

# Postman/Newman - Pruebas de API automatizadas
npm install -g newman
newman run collection.json

# curl - Pruebas básicas de endpoints
curl -X GET http://localhost:8000/api/users
curl -X POST http://localhost:8000/api/users -H "Content-Type: application/json" -d '{"name":"test"}'
```

### Servidor de Desarrollo
```bash
# Servidor built-in de PHP
php -S localhost:8000 -t public/

# Con auto-reload usando nodemon
npm install -g nodemon
nodemon --exec "php -S localhost:8000 -t public/" --ext php
```

## 🔄 Integración Continua (Scripts Completos)

### Script de Verificación Frontend
```bash
#!/bin/bash
# frontend-check.sh
echo "🔍 Verificando Frontend..."
npx tsc --noEmit &&
npx eslint src/ --ext .ts,.tsx &&
npm test -- --coverage --watchAll=false &&
npm run build
echo "✅ Frontend OK"
```

### Script de Verificación Backend
```bash
#!/bin/bash
# backend-check.sh
echo "🔍 Verificando Backend..."
./vendor/bin/phpcs --standard=PSR12 src/ &&
./vendor/bin/phpstan analyse src/ &&
./vendor/bin/phpunit --coverage-text &&
echo "✅ Backend OK"
```

### Script Completo de CI
```bash
#!/bin/bash
# full-check.sh
echo "🚀 Iniciando verificación completa..."

# Frontend
cd frontend/
npm ci
./frontend-check.sh

# Backend  
cd ../backend/
composer install --no-dev --optimize-autoloader
./backend-check.sh

# E2E Tests
cd ../
npx playwright test

echo "🎉 Todas las pruebas pasaron!"
```

## 📦 Package.json Scripts Recomendados

```json
{
  "scripts": {
    "dev": "react-scripts start",
    "build": "react-scripts build",
    "test": "react-scripts test",
    "test:ci": "react-scripts test --coverage --watchAll=false",
    "test:e2e": "playwright test",
    "lint": "eslint src/ --ext .ts,.tsx",
    "lint:fix": "eslint src/ --ext .ts,.tsx --fix",
    "format": "prettier --write src/",
    "format:check": "prettier --check src/",
    "type-check": "tsc --noEmit",
    "analyze": "npm run build && npx webpack-bundle-analyzer build/static/js/*.js",
    "check-all": "npm run type-check && npm run lint && npm run test:ci && npm run build"
  }
}
```

## 🔧 Composer Scripts para PHP

```json
{
  "scripts": {
    "test": "./vendor/bin/phpunit",
    "test:coverage": "./vendor/bin/phpunit --coverage-html coverage/",
    "lint": "./vendor/bin/phpcs --standard=PSR12 src/",
    "lint:fix": "./vendor/bin/php-cs-fixer fix src/",
    "analyze": "./vendor/bin/phpstan analyse src/",
    "check-all": "composer run lint && composer run analyze && composer run test"
  }
}
```

## 🚨 Herramientas de Monitoreo en Desarrollo

### Watching automático
```bash
# Frontend - Auto-reload con hot module replacement
npm start

# Backend - Auto-reload del servidor PHP
nodemon --exec "php -S localhost:8000 -t public/" --ext php

# Tests en modo watch
npm test -- --watch  # Frontend
./vendor/bin/phpunit-watcher watch  # Backend
```

### Git Hooks (Husky + lint-staged)
```bash
# Instalar husky para hooks de git
npm install --save-dev husky lint-staged

# Pre-commit hook que ejecuta tests automáticamente
npx husky add .husky/pre-commit "npm run check-all"
```

## 📊 Métricas y Reportes

```bash
# Generar reporte completo de cobertura
npm test -- --coverage --coverageReporters=html lcov text

# Análisis de complejidad del código
./vendor/bin/phpmd src/ html cleancode,codesize > complexity-report.html

# Auditoría de seguridad
npm audit  # Frontend
composer audit  # Backend (Composer 2.4+)
```