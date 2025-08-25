/**
 * Service Worker Management - PRODUCCIÓN READY
 * 
 * Registra o desregistra el Service Worker según el entorno
 * En producción se DESACTIVA por completo para evitar uso de IndexedDB
 */

export const isProd = import.meta.env?.PROD === true;

/**
 * Registra el Service Worker solo en desarrollo
 * En producción es NO-OP por seguridad
 */
export const registerSW = async (): Promise<void> => {
  if (isProd) {
    console.log('🔒 Service Worker DESHABILITADO en producción por política de seguridad');
    return;
  }

  if ('serviceWorker' in navigator) {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js');
      console.log('✅ Service Worker registrado (desarrollo):', registration);
    } catch (error) {
      console.warn('⚠️ Error registrando Service Worker:', error);
    }
  }
};

/**
 * Desregistra todos los Service Workers - Para limpieza en producción
 */
export const unregisterSW = async (): Promise<void> => {
  if ('serviceWorker' in navigator) {
    try {
      const registrations = await navigator.serviceWorker.getRegistrations();

      for (const registration of registrations) {
        const success = await registration.unregister();
        if (success) {
          console.log('✅ Service Worker desregistrado:', registration.scope);
        }
      }
    } catch (error) {
      console.warn('⚠️ Error desregistrando Service Workers:', error);
    }
  }
};

/**
 * Limpia todos los cachés del Service Worker
 */
export const clearSWCaches = async (): Promise<void> => {
  if ('caches' in window) {
    try {
      const cacheNames = await caches.keys();

      await Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
      );

      console.log('✅ Todos los cachés del Service Worker eliminados');
    } catch (error) {
      console.warn('⚠️ Error limpiando cachés:', error);
    }
  }
};

/**
 * Inicialización automática según entorno
 */
export const initServiceWorker = async (): Promise<void> => {
  if (isProd) {
    // En producción: desregistrar SW y limpiar cachés
    await unregisterSW();
    await clearSWCaches();
    console.log('🔒 PRODUCCIÓN: Service Worker y cachés eliminados por política de seguridad');
  } else {
    // En desarrollo: registrar normalmente
    await registerSW();
    console.log('🔧 DESARROLLO: Service Worker habilitado');
  }
};
