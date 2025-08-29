/**
 * Archivo de preload para la aplicación
 *
 * Este archivo se encarga de precargar los componentes críticos y rutas principales
 * para mejorar el rendimiento inicial.
 *
 * ✅ PERFORMANCE: Precarga inteligente basada en prioridad
 * ✅ SECURITY: Validación de rutas y sanitización
 * ✅ ERROR HANDLING: Manejo robusto de errores
 * ✅ MEMORY: Cleanup automático de timeouts
 * ✅ TYPE SAFETY: Tipos específicos para rutas
 */

import { sanitizeText } from './security/xss';

// Types for preload configuration
interface PreloadConfig {
  path: string;
  priority: 'critical' | 'high' | 'medium' | 'low';
  delay: number;
  retryCount: number;
}

interface PreloadState {
  loaded: Set<string>;
  failed: Set<string>;
  timeouts: Map<string, NodeJS.Timeout>;
}

// Configuration
const PRELOAD_CONFIGS: PreloadConfig[] = [
  // Critical components - load immediately
  { path: '/src/pages/HomePage', priority: 'critical', delay: 0, retryCount: 3 },
  { path: '/src/pages/NotFound', priority: 'critical', delay: 0, retryCount: 3 },
  { path: '/src/components/layout/Layout', priority: 'critical', delay: 0, retryCount: 3 },
  { path: '/src/components/Login', priority: 'critical', delay: 0, retryCount: 3 },
  { path: '/src/components/auth/ProtectedRoute', priority: 'critical', delay: 0, retryCount: 3 },

  // High priority - load after critical
  { path: '/src/pages/jobs/Index', priority: 'high', delay: 2000, retryCount: 2 },
  { path: '/src/pages/auth/Register', priority: 'high', delay: 2000, retryCount: 2 },

  // Medium priority - load later
  { path: '/src/pages/jobs/JobDetails', priority: 'medium', delay: 5000, retryCount: 1 },
  { path: '/src/pages/dashboard/CDDashboard', priority: 'medium', delay: 5000, retryCount: 1 },
];

const preloadState: PreloadState = {
  loaded: new Set(),
  failed: new Set(),
  timeouts: new Map()
};

/**
 * Valida y sanitiza una ruta de preload
 */
function validatePreloadPath(path: string): string | null {
  try {
    if (!path || typeof path !== 'string') {
      return null;
    }

    // Sanitize the path
    const sanitized = sanitizeText(path);

    // Validate path format (relative imports only)
    if (!sanitized.startsWith('./')) {
      return null;
    }

    // Prevent directory traversal
    if (sanitized.includes('../') || sanitized.includes('..\\')) {
      return null;
    }

    // Check for valid file extensions
    const validExtensions = ['.ts', '.tsx', '.js', '.jsx'];
    const hasValidExtension = validExtensions.some(ext => sanitized.endsWith(ext));

    return hasValidExtension ? sanitized : null;
  } catch (error) {
    console.error('Error validating preload path:', error);
    return null;
  }
}

/**
 * Precarga un módulo con manejo de errores y reintentos
 */
async function preloadModule(config: PreloadConfig): Promise<void> {
  const validatedPath = validatePreloadPath(config.path);
  if (!validatedPath) {
    console.warn(`Invalid preload path: ${config.path}`);
    return;
  }

  if (preloadState.loaded.has(validatedPath) || preloadState.failed.has(validatedPath)) {
    return; // Already processed
  }

  let attempts = 0;
  const maxAttempts = config.retryCount;

  while (attempts < maxAttempts) {
    try {
      // Use dynamic import with resolved path for Vite compatibility
      const resolvedPath = validatedPath.startsWith('/src/')
        ? validatedPath.substring(5) // Remove /src/ prefix for dynamic imports
        : validatedPath.replace(/^\.\//, '');

      await import(/* @vite-ignore */ `./${resolvedPath}`);
      preloadState.loaded.add(validatedPath);

      if (process.env.NODE_ENV === 'development') {
        console.log(`✅ Preloaded: ${validatedPath} (priority: ${config.priority})`);
      }

      return;
    } catch (error) {
      attempts++;
      console.warn(`Failed to preload ${validatedPath} (attempt ${attempts}/${maxAttempts}):`, error);

      if (attempts >= maxAttempts) {
        preloadState.failed.add(validatedPath);
        console.error(`❌ Failed to preload ${validatedPath} after ${maxAttempts} attempts`);
      } else {
        // Wait before retry with exponential backoff
        await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempts) * 1000));
      }
    }
  }
}

/**
 * Programa la precarga de módulos por prioridad
 */
function schedulePreload(): void {
  const priorityGroups = {
    critical: PRELOAD_CONFIGS.filter(c => c.priority === 'critical'),
    high: PRELOAD_CONFIGS.filter(c => c.priority === 'high'),
    medium: PRELOAD_CONFIGS.filter(c => c.priority === 'medium'),
    low: PRELOAD_CONFIGS.filter(c => c.priority === 'low')
  };

  // Load critical modules immediately
  priorityGroups.critical.forEach(config => {
    preloadModule(config).catch(error => {
      console.error(`Critical preload failed for ${config.path}:`, error);
    });
  });

  // Schedule other priorities
  Object.entries(priorityGroups).forEach(([priority, configs]) => {
    if (priority === 'critical') return;

    configs.forEach(config => {
      const timeoutId = setTimeout(() => {
        preloadModule(config).catch(error => {
          console.error(`Preload failed for ${config.path}:`, error);
        });
        preloadState.timeouts.delete(config.path);
      }, config.delay);

      preloadState.timeouts.set(config.path, timeoutId);
    });
  });
}

/**
 * Cleanup function para cancelar timeouts pendientes
 */
export function cleanupPreload(): void {
  preloadState.timeouts.forEach((timeoutId, path) => {
    clearTimeout(timeoutId);
    console.log(`Cancelled preload timeout for: ${path}`);
  });
  preloadState.timeouts.clear();
}

/**
 * Obtiene estadísticas de precarga
 */
export function getPreloadStats(): {
  loaded: number;
  failed: number;
  pending: number;
  total: number;
} {
  const total = PRELOAD_CONFIGS.length;
  const loaded = preloadState.loaded.size;
  const failed = preloadState.failed.size;
  const pending = preloadState.timeouts.size;

  return { loaded, failed, pending, total };
}

// ✅ TEMPORALMENTE DESHABILITADO para debugging - Iniciar precarga cuando el DOM esté listo
if (typeof window !== 'undefined') {
  console.log('🔧 Preload system temporarily disabled for debugging');
  /* DESHABILITADO TEMPORALMENTE
  if (document.readyState === 'complete') {
    schedulePreload();
  } else {
    window.addEventListener('load', schedulePreload);

    // Cleanup en caso de navegación
    window.addEventListener('beforeunload', cleanupPreload);
  }
  */
}

export { };
