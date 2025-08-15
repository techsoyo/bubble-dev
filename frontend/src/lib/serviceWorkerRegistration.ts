/**
 * Registro del Service Worker para habilitar capacidades offline
 * y mejora del rendimiento mediante caching.
 */

export const registerServiceWorker = () => {
  if ('serviceWorker' in navigator && import.meta.env.MODE === 'production') {
    // Registrar Service Worker después de que la página haya cargado por completo
    // para no bloquear la carga inicial
    window.addEventListener('load', () => {
      // Usar un timeout para darle prioridad a la interactividad del usuario
      setTimeout(() => {
        navigator.serviceWorker.register('/sw.js')
          .then(registration => {
            console.log('Service Worker registrado con éxito:', registration.scope);
            registration.addEventListener('updatefound', () => {
              const newWorker = registration.installing;
              if (newWorker) {
                newWorker.addEventListener('statechange', () => {
                  if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                    console.log('Nueva versión del SW disponible');
                  }
                });
              }
            });
          })
          .catch(error => {
            console.error('Fallo al registrar Service Worker:', error);
          });
      }, 2000); // Retrasar 2 segundos después de la carga completa
    });
  }
};

export const unregisterServiceWorker = () => {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready
      .then(registration => {
        registration.unregister();
      })
      .catch(error => {
        console.error(error.message);
      });
  }
};

// Detectar cambios en la conexión
export const setupConnectivityDetection = () => {
  const updateOnlineStatus = () => {
    const status = navigator.onLine ? 'online' : 'offline';
    document.documentElement.setAttribute('data-connection', status);

    // Disparar evento personalizado para que la aplicación pueda reaccionar
    window.dispatchEvent(new CustomEvent('connectionChange', {
      detail: { online: navigator.onLine }
    }));

    // Mostrar toast notificación en la interfaz
    if (!navigator.onLine) {
      // Aquí podemos disparar una notificación de UI
      console.log('Conexión perdida. Modo offline activo.');
    } else {
      console.log('Conexión restablecida.');
    }
  };

  window.addEventListener('online', updateOnlineStatus);
  window.addEventListener('offline', updateOnlineStatus);

  // Inicializar estado
  updateOnlineStatus();
};
