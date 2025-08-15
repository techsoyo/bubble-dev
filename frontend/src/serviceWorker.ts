/// <reference lib="webworker" />
// src/serviceWorker.ts
// Service Worker para almacenamiento en caché y capacidades offline

const CACHE_NAME = 'bubble-talents-cache-v1';
const RUNTIME_CACHE = 'runtime-cache';

// Recursos para precache
const PRECACHE_URLS = [
  '/',
  '/index.html',
  '/favicon.ico',
  '/favicon.svg',
  '/assets/css/critical.css',
  '/assets/images/logo.svg'
];


// Polyfill para SyncEvent si no está en el tipo global
export { };
declare global {
  // Solo si no existe
   
  interface SyncEvent extends ExtendableEvent {
    tag: string;
  }
}

// Instalación del Service Worker
self.addEventListener('install', (event) => {
  const swEvent = event as ExtendableEvent;
  swEvent.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        // ...eliminado console.log para producción...
        return cache.addAll(PRECACHE_URLS);
      })
      .then(() => (self as unknown as ServiceWorkerGlobalScope).skipWaiting())
  );
});

// Activación y limpieza de caches antiguos
self.addEventListener('activate', (event) => {
  const swEvent = event as ExtendableEvent;
  const currentCaches = [CACHE_NAME, RUNTIME_CACHE];
  swEvent.waitUntil(
    caches.keys().then(cacheNames => {
      return cacheNames.filter(cacheName => !currentCaches.includes(cacheName));
    }).then(cachesToDelete => {
      return Promise.all(cachesToDelete.map(cacheToDelete => {
        return caches.delete(cacheToDelete);
      }));
    }).then(() => (self as unknown as ServiceWorkerGlobalScope).clients.claim())
  );
});

// Estrategia de caché: Cache First, Network Fallback
self.addEventListener('fetch', (event) => {
  const fetchEvent = event as FetchEvent;
  // Evitar cachear llamadas a la API
  if (fetchEvent.request.url.includes('/api/')) {
    return;
  }

  // No cachear peticiones POST u otras no GET
  if (fetchEvent.request.method !== 'GET') {
    return;
  }

  fetchEvent.respondWith(
    (async () => {
      const cachedResponse = await caches.match(fetchEvent.request);
      if (cachedResponse) {
        // Recurso encontrado en caché
        return cachedResponse;
      }
      // No está en caché, buscarlo en la red
      try {
        const cache = await caches.open(RUNTIME_CACHE);
        const response = await fetch(fetchEvent.request);
        if (response.status === 200) {
          cache.put(fetchEvent.request, response.clone());
        }
        return response;
      } catch (err) {
        // Si la red falla, intentar servir una página fallback para HTML
        if (fetchEvent.request.headers.get('accept')?.includes('text/html')) {
          const offline = await caches.match('/offline.html');
          if (offline) return offline;
        }
        return new Response('Error de conexión', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: new Headers({
            'Content-Type': 'text/plain'
          })
        });
      }
    })()
  );
});

// Notificaciones push (opcional)
self.addEventListener('push', (event) => {
  const pushEvent = event as PushEvent;
  const data = pushEvent.data ? pushEvent.data.json() : { title: 'Notificación', body: '' };
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

  pushEvent.waitUntil(
    (self as unknown as ServiceWorkerGlobalScope).registration.showNotification(data.title, options)
  );
});

// Manejo de sincronización en segundo plano
self.addEventListener('sync', (event) => {
  const syncEvent = event as SyncEvent;
  if (syncEvent.tag === 'sync-applications') {
    syncEvent.waitUntil(syncApplicationData());
  }
});

// Función de sincronización de datos
async function syncApplicationData() {
  try {
    // Lógica para sincronizar datos de aplicaciones
    // idb es una extensión personalizada en self
    const dataToSync = await (self as any).idb?.get('syncQueue');
    if (dataToSync && dataToSync.length) {
      // Enviar datos al servidor
      await Promise.all(dataToSync.map(async (item: Record<string, unknown>) => {
        try {
          const response = await fetch('/api/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(item)
          });
          if (response.ok) {
            // Eliminar de la cola si se sincronizó correctamente
            // idb es una extensión personalizada en self
            await (self as any).idb?.delete('syncQueue', item.id);
          }
        } catch (error) {
          console.error('Error syncing item:', error);
        }
      }));
    }
  } catch (error) {
    console.error('Sync failed:', error);
  }
}
