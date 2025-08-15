# Herramientas de Diagnóstico - Bubble of Talents

## COMANDOS TESTING IA CODESPACES

```bash
# Iniciar servidor IA en Codespaces
python ai_server_codespaces.py

# Test completo sistema IA  
cd backend && php test_codespaces_complete.php

# Verificar conectividad GPU
nvidia-smi

# Test conectividad API
curl http://localhost:5000/health

# Configurar provider Codespaces
echo "AI_PROVIDER=codespaces" >> backend/.env
echo "CODESPACES_AI_URL=http://localhost:5000" >> backend/.env
```

## Comandos de Desarrollo

### Frontend (React/TypeScript)
```powershell
# Verificación TypeScript
npm run typecheck
pnpm typecheck

# Build y compilación
npm run build
pnpm build

# Servidor desarrollo
npm run dev
pnpm dev

# Linting y formato
npm run lint
pnpm lint
```

### Backend (PHP)
```powershell
# Verificar sintaxis PHP
php -l archivo.php

# Composer
composer install
composer update
composer dump-autoload

# Tests PHP
php backend/tests/test_cors.php
```

### Sistema CORS
```powershell
# Auditoría CORS
php backend/cors_audit.php
php backend/cors_final_check.php

# Monitoreo específico
php backend/test_cors_specific_endpoints.php
```

## 🤖 Automatización IA Reclutamiento

```powershell
# Endpoint de Matching IA
curl -X POST "http://localhost:8000/api/match.php" -H "Content-Type: application/json" -d '{"candidates":[{"id":"test","hard_skills":["React","JS"]}],"job":{"required_skills":["React"]},"feature_flags":{"ai_matching_enabled":false}}'

# Test scoring individual
php -r "require 'config/bootstrap.php'; $m = new Services\MatchingService(); $r = $m->createFallbackScore(['hard_skills'=>['React','JS']], ['required_skills'=>['React']]); echo 'Score: ' . $r['overall_score'] . PHP_EOL;"

# Auditoría servicios IA
php -r "require 'config/bootstrap.php'; echo 'AdvancedCVParser: '; try { new Services\CV\AdvancedCVParser('test'); echo 'OK'; } catch(Exception $e) { echo $e->getMessage(); } echo PHP_EOL;"
```
```powershell
# Estado Git
git status
git log --oneline -10

# Estructura proyecto
tree /f /a
Get-ChildItem -Recurse -Name
```

## Historial de Comandos Utilizados

### 2025-08-14 17:30
- **Corrección UI**: Banderas agregadas al language-switcher.tsx (🇪🇸 🇺🇸)
- **Traducciones**: Corregido "Spanish" → "Español" en translations.ts
- **Archivos limpiados**: debug_chatbot_current.php, fix_remove_buscar_empleo.php

### 2025-08-14 17:15
- **Verificación chatbot**: `php test_chatbot_nodes.php` (tabla bt_chatbot_nodes: 4 registros)
- **Endpoint chatbot**: `/api/endpoints/chatbot_decision_tree.php` (datos reales confirmados)
- **Servidores controlados**: Frontend:3002, Backend:8000 (no reiniciar)

### 2025-08-14 16:45
- `npm run build`: Verificación compilación exitosa CDDashboard
- `php backend/cors_final_check.php`: Auditoría CORS completa (21 tests)
- Sistema consultor IA activado con protocolo estructurado

---
*Actualizado automáticamente por el sistema consultor IA*
