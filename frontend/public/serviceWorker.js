// Service Worker para almacenamiento en caché y capacidades offline

const CACHE_NAME = 'bubble-talents-cache-v1';
const RUNTIME_CACHE = 'runtime-cache';

// Recursos para precache
const PRECACHE_URLS = [
  '/',
  '/index.html',
  '/offline.html',
  '/favicon.ico',
  '/favicon.svg',
  '/assets/css/critical.css',
  '/assets/images/logo.svg',
  '/assets/fonts/custom-font.woff2'
];

// Instalación del Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Cache abierto y guardando recursos iniciales');
        return cache.addAll(PRECACHE_URLS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activación y limpieza de caches antiguos
self.addEventListener('activate', (event) => {
  const currentCaches = [CACHE_NAME, RUNTIME_CACHE];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return cacheNames.filter(cacheName => !currentCaches.includes(cacheName));
    }).then(cachesToDelete => {
      return Promise.all(cachesToDelete.map(cacheToDelete => {
        return caches.delete(cacheToDelete);
      }));
    }).then(() => self.clients.claim())
  );
});

// Estrategia de caché: Cache First, Network Fallback
self.addEventListener('fetch', (event) => {
  // Evitar cachear llamadas a la API
  if (event.request.url.includes('/api/')) {
    return;
  }

  // No cachear peticiones POST u otras no GET
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cachedResponse => {
      if (cachedResponse) {
        // Recurso encontrado en caché
        return cachedResponse;
      }

      // No está en caché, buscarlo en la red
      return caches.open(RUNTIME_CACHE).then(cache => {
        return fetch(event.request).then(response => {
          // Guardar copia de la respuesta en el caché runtime
          if (response.status === 200) {
            cache.put(event.request, response.clone());
          }
          return response;
        }).catch(() => {
          // Si la red falla, intentar servir una página fallback para HTML
          if (event.request.headers.get('accept')?.includes('text/html')) {
            return caches.match('/offline.html');
          }
          return new Response('Error de conexión', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: new Headers({
              'Content-Type': 'text/plain'
            })
          });
        });
      });
    })
  );
});

// Notificaciones push (opcional)
self.addEventListener('push', (event) => {
  if (!event.data) return;

  try {
    const data = event.data.json();
    const options = {
      body: data.body,
      icon: '/assets/images/logo.svg',
      badge: '/assets/images/badge.png',
      vibrate: [100, 50, 100],
      data: {
        dateOfArrival: Date.now(),
        primaryKey: '1'
      },
      actions: [
        {
          action: 'explore',
          title: 'Ver detalles',
        },
        {
          action: 'close',
          title: 'Cerrar',
        },
      ]
    };

    event.waitUntil(
      self.registration.showNotification(data.title, options)
    );
  } catch (e) {
    console.error('Error procesando notificación push:', e);
  }
});

// Manejo de sincronización en segundo plano
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-applications') {
    event.waitUntil(syncApplicationData());
  }
});

// Función de sincronización de datos
async function syncApplicationData() {
  try {
    // Esta funcionalidad requeriría una implementación específica
    // con IndexedDB para datos pendientes de sincronización
    console.log('Sincronización en segundo plano ejecutada');
  } catch (error) {
    console.error('Sync failed:', error);
  }
}
