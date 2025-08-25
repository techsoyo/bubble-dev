/**
 * Safe Storage Wrapper - PRODUCCIÓN READY
 * 
 * Elimina completamente el uso de localStorage/sessionStorage/indexedDB en producción
 * Solo permite storage en desarrollo para debugging
 */

export const isProd = import.meta.env.MODE === 'production' || process.env.NODE_ENV === 'production';

/**
 * Safe localStorage setter - NO-OP en producción
 */
export const safeSet = (key: string, value: any) => {
  if (isProd) {
    return; // NO-OP in production
  }

  if (!isProd) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.warn(`safeSet failed for key ${key}:`, error);
    }
  }
};

/**
 * Safe localStorage getter - Siempre retorna fallback en producción
 */
export const safeGet = <T>(key: string, fallback: T): T => {
  if (isProd) return fallback;

  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (error) {
    console.warn(`safeGet failed for key ${key}:`, error);
    return fallback;
  }
};

/**
 * Safe localStorage remover - NO-OP en producción
 */
export const safeRemove = (key: string) => {
  if (!isProd) {
    try {
      localStorage.removeItem(key);
    } catch (error) {
      console.warn(`safeRemove failed for key ${key}:`, error);
    }
  }
};

/**
 * Safe sessionStorage setter - NO-OP en producción
 */
export const safeSetSession = (key: string, value: any) => {
  if (!isProd) {
    try {
      sessionStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.warn(`safeSetSession failed for key ${key}:`, error);
    }
  }
};

/**
 * Safe sessionStorage getter - Siempre retorna fallback en producción
 */
export const safeGetSession = <T>(key: string, fallback: T): T => {
  if (isProd) return fallback;

  try {
    const raw = sessionStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (error) {
    console.warn(`safeGetSession failed for key ${key}:`, error);
    return fallback;
  }
};

/**
 * Safe sessionStorage remover - NO-OP en producción
 */
export const safeRemoveSession = (key: string) => {
  if (!isProd) {
    try {
      sessionStorage.removeItem(key);
    } catch (error) {
      console.warn(`safeRemoveSession failed for key ${key}:`, error);
    }
  }
};

/**
 * Deprecation warnings para desarrolladores
 */
export const deprecatedStorageWarning = (storageType: string, functionName: string) => {
  if (!isProd) {
    console.warn(
      `🚨 DEPRECATED: ${functionName} usa ${storageType}. ` +
      `Migra a cookies seguras o usa safeStorage. ` +
      `Esta funcionalidad está DESHABILITADA en producción.`
    );
  }
};
