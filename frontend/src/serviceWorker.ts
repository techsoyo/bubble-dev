/// <reference lib="webworker" />
/// <reference types="vite/client" />

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

// Función auxiliar para validar URLs
function isValidUrl(url: string): boolean {
  try {
    new URL(url, self.location.origin);
    return true;
  } catch {
    return false;
  }
}

// Instalación del Service Worker
self.addEventListener('install', (event: Event) => {
  const installEvent = event as any;
  installEvent.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(PRECACHE_URLS))
      .then(() => (self as any).skipWaiting())
  );
});

// Activación y limpieza de caches antiguos
self.addEventListener('activate', (event: Event) => {
  const activateEvent = event as any;
  const currentCaches = [CACHE_NAME, RUNTIME_CACHE];
  activateEvent.waitUntil(
    caches.keys()
      .then(cacheNames => cacheNames.filter(name => !currentCaches.includes(name)))
      .then(cachesToDelete => Promise.all(cachesToDelete.map(name => caches.delete(name))))
      .then(() => (self as any).clients.claim())
  );
});

// Estrategia de caché: Cache First, Network Fallback
self.addEventListener('fetch', (event: Event) => {
  const fetchEvent = event as any;
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
        return cachedResponse;
      }

      try {
        const cache = await caches.open(RUNTIME_CACHE);
        const response = await fetch(fetchEvent.request);
        if (response.status === 200) {
          cache.put(fetchEvent.request, response.clone());
        }
        return response;
      } catch (error) {
        // Si la red falla, intentar servir una página fallback para HTML
        if (fetchEvent.request.headers.get('accept')?.includes('text/html')) {
          const offline = await caches.match('/offline.html');
          if (offline) return offline;
        }
        return new Response('Error de conexión', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: { 'Content-Type': 'text/plain' }
        });
      }
    })()
  );
});

// Notificaciones push (opcional)
self.addEventListener('push', (event: Event) => {
  const pushEvent = event as any;
  if (!pushEvent.data) return;

  const data = pushEvent.data.json();
  // Sanitizar datos de notificación
  const title = String(data.title || 'Notificación').substring(0, 100);
  const body = String(data.body || '').substring(0, 500);

  const options = {
    body,
    icon: '/assets/images/logo.svg',
    badge: '/assets/images/badge.png',
    vibrate: [100, 50, 100],
    data: { dateOfArrival: Date.now(), primaryKey: '1' },
    actions: [
      { action: 'explore', title: 'Ver detalles' },
      { action: 'close', title: 'Cerrar' }
    ]
  };

  pushEvent.waitUntil(
    (self as any).registration.showNotification(title, options)
  );
});

// Manejo de sincronización en segundo plano
self.addEventListener('sync', (event: Event) => {
  const syncEvent = event as any;
  if (syncEvent.tag === 'sync-applications') {
    syncEvent.waitUntil(syncApplicationData());
  }
});

// Función de sincronización de datos
async function syncApplicationData() {
  try {
    // Validar y sanitizar datos antes de enviar
    const dataToSync = await getSyncQueue();
    if (!Array.isArray(dataToSync)) return;

    await Promise.all(dataToSync.map(async (item) => {
      if (!isValidSyncItem(item)) return;

      try {
        const response = await fetch('/api/sync', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(item)
        });

        if (response.ok) {
          await removeFromSyncQueue(item.id);
        }
      } catch (error) {
        console.error('Error syncing item:', error);
      }
    }));
  } catch (error) {
    console.error('Sync failed:', error);
  }
}

// Funciones auxiliares para reducir complejidad
async function getSyncQueue(): Promise<unknown[]> {
  // Implementar obtención segura de datos
  return [];
}

async function removeFromSyncQueue(id: string): Promise<void> {
  // Implementar eliminación segura
}

function isValidSyncItem(item: unknown): item is { id: string;[key: string]: unknown } {
  return typeof item === 'object' && item !== null && 'id' in item && typeof (item as any).id === 'string';
}