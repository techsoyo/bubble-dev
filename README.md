<<<<<<< HEAD
# 🎯 Bubble of Talents 1.0

Plataforma de reclutamiento con IA - Sistema completo de gestión de candidatos y procesos de selección.

## 📁 Estructura del Proyecto

```
bubble_of_talents_1.0/
├── README.md                    # Este archivo
├── package.json                 # Scripts del proyecto completo
├── playwright.config.ts         # Configuración de tests E2E
├── tsconfig.json               # Configuración TypeScript global
├── vite.config.ts              # Configuración Vite (frontend)
├── .env                        # Variables de entorno
├── .gitignore                  # Exclusiones Git
├── LICENSE                     # Licencia del proyecto
│
├── frontend/                   # Aplicación React + TypeScript
│   ├── src/                   # Código fuente del frontend
│   ├── public/                # Archivos estáticos
│   ├── package.json           # Dependencias del frontend
│   └── varios-frontend/       # Archivos auxiliares del frontend
│       ├── tests/             # Tests aislados del frontend
│       └── config/            # Configuraciones obsoletas
│
├── backend/                    # API PHP
│   ├── api/                   # Endpoints de la API
│   ├── config/                # Configuraciones del backend
│   ├── public/                # Punto de entrada público
│   ├── src/                   # Código fuente del backend
│   ├── composer.json          # Dependencias del backend
│   └── varios-backend/        # Archivos auxiliares del backend
│       ├── tests/             # Tests aislados del backend
│       ├── docs/              # Documentación técnica
│       └── uploads/           # Archivos de prueba
│
└── varios/                     # Recursos auxiliares del proyecto
    ├── docs/                  # Documentación y guías
    │   ├── AUTH_FIXES_SUMMARY.md
    │   ├── DEPLOYMENT_GUIDE.md
    │   ├── SECURITY_AUDIT_REPORT.md
    │   └── ...
    ├── scripts/               # Scripts de automatización y pruebas
    │   ├── test-*.js          # Scripts de prueba
    │   ├── *.ps1              # Scripts PowerShell
    │   └── scripts-original/  # Scripts originales del proyecto
    ├── tests/                 # Resultados y configuraciones de tests
    │   ├── playwright-report/ # Reportes de Playwright
    │   ├── tests/             # Tests E2E
    │   └── tests-examples/    # Ejemplos de tests
    └── data/                  # Datos auxiliares y respaldos
        ├── *.zip              # Respaldos del proyecto
        ├── tree.txt           # Estructura del proyecto
        └── lint-report.json   # Reportes de linting
```

## 🚀 Inicio Rápido

### Instalación completa
```bash
npm run install:all
```

### Desarrollo
```bash
npm run dev          # Inicia el frontend en modo desarrollo
```

### Construcción
```bash
npm run build        # Construye el frontend para producción
```

### Tests
```bash
npm run test         # Tests unitarios del frontend
npm run test:e2e     # Tests E2E con Playwright
npm run test:responsive  # Tests de responsive design
```

## CORS y Proxy en Bubble of Talents

### Configuración CORS Backend (PHP)

El backend implementa un middleware CORS global en `backend/api/bootstrap.php`.

#### Variables de entorno relevantes (`backend/.env`):

```
CORS_ALLOWED_ORIGINS=http://localhost:3002,http://127.0.0.1:3002,https://app.mi-dominio.com
CORS_ALLOW_CREDENTIALS=true
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With
CORS_MAX_AGE=86400
APP_ENV=development
```

**Notas:**
- En desarrollo, si no hay `Origin`, permite `http://localhost:3002` como fallback.
- En producción, solo los Origins permitidos reciben cabecera `Access-Control-Allow-Origin`.
- Si `CORS_ALLOW_CREDENTIALS=true`, nunca se usa `*` como valor de Allow-Origin.
- Todas las respuestas incluyen las cabeceras CORS correctas y los preflight OPTIONS responden 204 sin body.
- Si `APP_ENV=development`, los rechazos de Origin se loguean en el error log.

#### Ejemplo de .env:

```
CORS_ALLOWED_ORIGINS=http://localhost:3002,http://127.0.0.1:3002,https://app.mi-dominio.com
CORS_ALLOW_CREDENTIALS=true
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With
CORS_MAX_AGE=86400
APP_ENV=development
```

### Configuración Frontend (Vite)

Para evitar problemas CORS en desarrollo, el frontend usa un proxy en `frontend/config/vite.config.ts`:

```js
server: {
  proxy: {
    '/api': {
      target: process.env.VITE_API_PROXY_TARGET || 'http://localhost:8000',
      changeOrigin: true,
      secure: false,
    },
  },
}
```

Agrega en tu `.env.development` del frontend:

```
VITE_API_PROXY_TARGET=http://localhost:8000
```

**Importante:** Si `API_BASE_URL` es same-origin (mismo host/puerto que el frontend), el proxy no es necesario.

### Troubleshooting

- Si ves errores CORS en navegador, revisa que el Origin esté en la allowlist y que las variables de entorno sean correctas.
- Verifica que el backend responde OPTIONS con 204 y las cabeceras Allow-* correctas.
- Si usas credenciales (cookies, Authorization), asegúrate que Allow-Origin nunca sea `*`.
- En desarrollo, revisa el log de errores del backend para diagnósticos de CORS.

---

**Actualizado:** 8 de agosto de 2025

---

## Ingesta de CV (AI + Manual / Solo Manual por política)

Esta plataforma permite dos modos de captura de CV:

1. Modo híbrido (por defecto): el candidato sube un PDF y se intenta parsear con IA para pre-rellenar el formulario, pudiendo editar antes de confirmar.
2. Modo manual forzado: por políticas de privacidad/compliance se desactiva el uso de IA y el candidato completa todo manualmente desde el inicio.

La selección del modo se controla con un flag de entorno.

### Variables de entorno backend (archivo `.env` en `backend/`)

```
# Clave OpenAI (requerida si se usa modo IA)
OPENAI_API_KEY=sk-xxxx

# (Opcionales si se añaden en el futuro)
# OPENAI_API_BASE=https://api.openai.com/v1
# OPENAI_MODEL=gpt-4

# Forzar sólo entrada manual (oculta parseo IA en frontend si se propaga)
CV_ALLOW_MANUAL_ONLY=false
```

Notas:
- Si `OPENAI_API_KEY` no está presente, el endpoint `/api/cv/parse` devolverá error `PARSE_FAILED` y el frontend caerá a modo manual.
- `CV_ALLOW_MANUAL_ONLY=true` puede usarse también en frontend como `VITE_CV_ALLOW_MANUAL_ONLY=true` para ocultar el uploader y abrir directamente el formulario manual.

### Variables de entorno frontend (`.env`, prefijo VITE_)

```
VITE_API_PROXY_TARGET=http://localhost:8000
VITE_CV_ALLOW_MANUAL_ONLY=false
```

Si se establece `VITE_CV_ALLOW_MANUAL_ONLY=true` el componente `CvIntake`:
- No muestra el uploader PDF.
- Abre el modal de edición manual automáticamente.
- Presenta un aviso: "El modo IA está deshabilitado por política".

### Endpoints

1. `POST /api/cv/parse` (multipart/form-data)
   - Campo requerido: `file` (PDF)
   - Validaciones: MIME `application/pdf`, extensión `.pdf`, tamaño < 5MB.
   - Éxito 200: `{ success:true, data:<CvSchema>, meta:{ mode:"ai" } }`
   - Errores estandarizados:
     - 400 `MISSING_FILE | INVALID_MIME | MAX_SIZE_EXCEEDED`
     - 422 `PARSE_FAILED`
     - 405 `METHOD_NOT_ALLOWED`
   - El PDF se procesa en temporal y se elimina (no se conserva salvo posibles logs de auditoría textuales). No se guardan copias binarias persistentes.

2. `POST /api/cv/confirm` (application/json)
   - Body: Objeto que cumple el contrato `CvSchema` (los campos faltantes se llenan vacíos en normalización backend).
   - Lógica: Validación mínima + transacción (upsert candidato + reemplazo de tablas hijas experiencias, educación, proyectos, etc.).
   - Éxito 200: `{ success:true, data:{ id:<candidate_id> } }` (o similar; puede ampliarse).
   - Errores: `VALIDATION_FAILED`, `DB_ERROR`, `METHOD_NOT_ALLOWED` con los mismos envoltorios `{ success:false, error:{ code,message,details } }`.

### Contrato de Datos (CvSchema simplificado)

```
{
  nombre: string,
  email: string,
  telefono: string,
  ubicacion_actual: string,
  fecha_nacimiento: string (YYYY-MM-DD),
  portfolio: string,
  linkedin: string,
  otras_redes: string[],
  resumen_profesional: string,
  soft_skills: string[],
  hard_skills: string[],
  idiomas: string[],
  intereses: string[],
  referencias: string[],
  disponibilidad: string,
  puestos_anteriores: [ { puesto, empresa, fecha_inicio, fecha_fin, descripcion, responsabilidades: string[] } ],
  educacion: [ { titulo, institucion, fecha_inicio, fecha_fin, descripcion } ],
  certificaciones: string[],
  proyectos: [ { nombre, descripcion } ]
}
```

Campos adicionales pueden existir internamente (ej. metadatos `processed_with`, `processed_at`) pero se normalizan y/o filtran según necesidades de persistencia.

Validaciones mínimas (frontend + backend):
- `email` válido.
- Al menos un nombre y email no vacíos para confirmar.
- Fecha inicio <= fecha fin en experiencias y educación si ambas existen.

### Privacidad y retención

El PDF se mueve a una ruta temporal, se procesa y se elimina. No se almacena el binario original en disco permanente. Si se requiere auditoría se recomienda (opcional y configurable) almacenar únicamente hash y log del resultado de parseo, nunca el PDF completo salvo consentimiento explícito.

### Ejemplos curl

Parse exitoso:
```bash
curl -X POST http://localhost:8000/api/cv/parse \
  -H "Accept: application/json" \
  -F "file=@./tests/cv/sample.pdf;type=application/pdf"
```

Parse fallo (sin archivo):
```bash
curl -X POST http://localhost:8000/api/cv/parse -H "Accept: application/json"
```

Confirmación (JSON) éxito:
```bash
curl -X POST http://localhost:8000/api/cv/confirm \
  -H "Content-Type: application/json" \
  -d @tests/cv/cv_ok.json
```

Confirmación con error validación (email vacío):
```bash
curl -X POST http://localhost:8000/api/cv/confirm \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Test","email":""}'
```

### Flag de política: operación sin IA

Para organizaciones que no permiten uso de IA externa:

1. Backend: defina `CV_ALLOW_MANUAL_ONLY=true` y (opcional) NO configure `OPENAI_API_KEY`.
2. Frontend: defina `VITE_CV_ALLOW_MANUAL_ONLY=true` en `.env` para ocultar el flujo de subida PDF.
3. El formulario se abre directamente en modo manual; cualquier intento de llamar `/api/cv/parse` (si se expone) debe ser ignorado en UI.

### Criterio de aceptación empresarial

- Con `VITE_CV_ALLOW_MANUAL_ONLY=true` la aplicación sigue siendo totalmente usable: captura, validación y persistencia sin depender de IA.
- Con `VITE_CV_ALLOW_MANUAL_ONLY=false` se aprovecha el parseo asistido para reducir fricción, manteniendo siempre la posibilidad de editar antes de confirmar.
- Errores se entregan con formato consistente (`success:false, error:{ code,message,details }`).
- No se persisten PDFs originales por defecto (solo datos estructurados normalizados).

---

## Tests E2E (Playwright)

Se han añadido pruebas end-to-end para los dos flujos principales del intake de CV.

Rutas de tests: `frontend/tests/e2e/`.

Escenarios:
- `ai-happy-path.spec.ts`: Subida PDF legible -> parse exitoso (mock) -> modal pre-rellenado -> edición campo -> confirmación.
- `manual-fallback.spec.ts`: Parse falla (422 mock) -> fallback manual -> completar mínimos -> confirmación.

Fixtures: en `frontend/tests/e2e/fixtures/` (`cv_legible.json`, `parse_422.json`, PDFs placeholder).

Mock API: MSW intercepta `/api/cv/parse` y `/api/cv/confirm` (archivo `utils/mockServer.ts`). La app debe inicializar el worker en entorno e2e (ej. condicionando por `process.env.NODE_ENV === 'test'`); si no, puede integrarse añadiendo en la entrada principal del frontend.

Ejecución local:
```bash
cd frontend
pnpm install
npx playwright install --with-deps
pnpm run test:e2e
```

Artefactos: videos y traces en `test-results/` y reporte HTML `playwright-report/`.

CI: Workflow `.github/workflows/e2e.yml` ejecuta y sube artefactos (retención 7 días).

Variables relevantes (si se quiere forzar modo manual en e2e): `VITE_CV_ALLOW_MANUAL_ONLY=false` para permitir ambos caminos.

---

## Retención y GDPR Básico

Variables `.env` backend nuevas:
```
CV_RETENTION_DAYS=30
CV_ALLOW_EXPORT_JSON=true
CV_ALLOW_DELETE_REQUEST=true
```

- `CV_RETENTION_DAYS`: días que se conservan ficheros temporales en `storage/private/cv/` y JSON auxiliares en `storage/json/` antes de ser purgados.
- `CV_ALLOW_EXPORT_JSON`: si `true`, habilita `GET /api/cv/export/{candidate_id}` (requiere auth) para extraer los datos normalizados del candidato.
- `CV_ALLOW_DELETE_REQUEST`: si `true`, habilita `DELETE /api/cv/{candidate_id}` (requiere auth) para suprimir datos del candidato.

### Limpieza programada

Script CLI: `backend/bin/cleanup.php`

Ejemplo cron diario (02:15):
```
15 2 * * * /usr/bin/php /var/www/app/backend/bin/cleanup.php >> /var/log/app/cleanup.log 2>&1
```
Log JSON (stderr): `{ event:"cleanup", scanned, deleted, retention_days, duration_ms }`.

### Endpoints de administración (auditables)

1. Exportación:
```
GET /api/cv/export/{id}
Auth: Authorization: Bearer <token>
Res 200: { success:true, data:{ ...CvSchema + hijos } }
```
2. Supresión:
```
DELETE /api/cv/{id}
Auth: Authorization: Bearer <token>
Res 200: { success:true, data:{ deleted:true, candidate_id } }
```

Flags deben estar activos; si no, 403 con `EXPORT_DISABLED` o `DELETE_DISABLED`.
Cada acción genera log estructurado (`event=cv_admin action=export|delete`).

### Consentimiento del candidato

Antes de subir el CV se presenta banner/checkbox de consentimiento con enlace a la política de privacidad. Sin aceptación no se debe iniciar el parse.

### Ejemplos curl

Exportar (id=123):
```bash
curl -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  http://localhost:8000/api/cv/export/123
```

Eliminar (id=123):
```bash
curl -X DELETE \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  http://localhost:8000/api/cv/123
```

### Consideraciones
- Export sólo expone datos normalizados; no se devuelven PDFs binarios.
- Borrado delega en FKs con ON DELETE CASCADE o eliminación directa dentro de transacción.
- Ajustar `CV_RETENTION_DAYS` según política corporativa.
- Para auditoría adicional se pueden almacenar hashes de PDF (no implementado por defecto).

---
=======
# bubble-of-talents-ai
>>>>>>> 3bb4e215817ee88010a1fcbe8401151e40995c5a
