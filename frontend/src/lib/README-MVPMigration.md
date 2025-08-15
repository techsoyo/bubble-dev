# Migración de MVP a Base de Datos

## Archivos Deprecated

Los siguientes archivos han sido renombrados con el sufijo `.mvp-deprecated.ts` porque ya no se utilizan. Ahora toda la información se obtiene directamente de la base de datos:

### Archivos de datos mock (ya no se usan)
- `constants.deprecated.ts` - Datos mock generales
- `constants-users.mvp-deprecated.ts` - Datos mock de usuarios
- `constants-departments.mvp-deprecated.ts` - Datos mock de departamentos
- (Eliminado) `setup-mock-data.mvp-deprecated.ts` - Inicialización de datos mock en localStorage
- (Eliminado) `mock-data-mvp-deprecated/` - Directorio completo con todos los datos mock

### Archivos de mapeo y asignación (ya no se usan)
- `candidate-department-assignment.mvp-deprecated.ts` - Asignación de candidatos a departamentos
- `category-department-mapping.mvp-deprecated.ts` - Mapeo de categorías a departamentos
- `db-mapping.mvp-deprecated.ts` - Mapeo de base de datos
- `department-recruiters.mvp-deprecated.ts` - Asignación de reclutadores a departamentos
- `departments.mvp-deprecated.ts` - Definiciones de departamentos

## Nueva Arquitectura

### ❌ Ya NO se usa localStorage
- **Antes**: Los datos se almacenaban en localStorage usando `setup-mock-data.ts`
- **Ahora**: Todos los datos se obtienen directamente de APIs de base de datos
- **Beneficio**: Datos siempre actualizados y consistentes entre usuarios

### APIs de Base de Datos
Ahora se utilizan las siguientes APIs para obtener datos:

- `/api/candidates` - Obtener candidatos
- `/api/jobs` - Obtener trabajos
- `/api/applications` - Obtener aplicaciones
- `/api/recruiters` - Obtener reclutadores
- `/api/departments` - Obtener departamentos
- `/api/hr/dashboard-stats` - Estadísticas del dashboard de HR
- `/api/recruiter/{id}/dashboard-stats` - Estadísticas del dashboard del reclutador

### Archivos Activos
Los únicos archivos que siguen activos en `/lib/` son:

- `api.ts` - Configuración de API
- `apiService.ts` - Servicios de API actualizados para usar base de datos
- `index.ts` - Exportaciones centralizadas (actualizado)
- `emailService.ts` - Servicio de emails
- `environment.ts` - Configuración de entorno
- `pdfProcessor.ts` - Procesamiento de PDFs
- `protectedRoutes.ts` - Rutas protegidas
- `serviceWorkerRegistration.ts` - Registro de Service Worker
- `utils.ts` - Utilidades generales
- Archivos de autenticación e internacionalización

## Notas Importantes

1. **No importar archivos deprecated**: Los archivos con sufijo `.mvp-deprecated.ts` no deben ser importados por ningún componente activo.

2. **Migración completa**: Toda la lógica que dependía de datos mock ahora debe usar las APIs de base de datos.

3. **Fallbacks**: Los servicios incluyen fallbacks apropiados en caso de error de API.

4. **Performance**: La nueva arquitectura es más eficiente al obtener solo los datos necesarios de la base de datos.

## Fecha de Migración
- Iniciada: 7 de agosto de 2025
- Completada: 7 de agosto de 2025
