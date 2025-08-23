/**
 * Service Worker for Bubble of Talents Frontend
 * 
 * Production-ready service worker with caching strategies, offline support,
 * and performance optimization for the recruitment platform.
 * 
 * @package ServiceWorker
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-01-05
 */

const CACHE_NAME = 'bubble-talents-v1';
const STATIC_CACHE = 'bubble-talents-static-v1';
const DYNAMIC_CACHE = 'bubble-talents-dynamic-v1';
const IMAGE_CACHE = 'bubble-talents-images-v1';
const API_CACHE = 'bubble-talents-api-v1';

// Static assets to cache immediately
const STATIC_ASSETS = [
    '/',
    '/assets/index-zPkeikH6.js',
    '/assets/index-BLSAxj3v.css',
    '/manifest.json',
    '/favicon.ico',
    '/favicon.svg'
];

// API endpoints to cache
const API_ENDPOINTS = [
    '/api/auth/profile',
    '/api/jobs',
    '/api/candidates'
];

// Routes that need network-first strategy
const NETWORK_FIRST_ROUTES = [
    '/api/auth',
    '/api/upload',
    '/api/messages'
];

// Routes that work offline
const OFFLINE_FALLBACK_ROUTES = [
    '/jobs',
    '/dashboard',
    '/profile'
];

/**
 * Install event - cache static assets
 */
self.addEventListener('install', (event) => {

    event.waitUntil(
        Promise.all([
            // Cache static assets
            caches.open(STATIC_CACHE).then((cache) => {
                return cache.addAll(STATIC_ASSETS);
            }),
            // Skip waiting to activate immediately
            self.skipWaiting()
        ])
    );
});

/**
 * Activate event - cleanup old caches
 */
self.addEventListener('activate', (event) => {

    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (
                            cacheName !== STATIC_CACHE &&
                            cacheName !== DYNAMIC_CACHE &&
                            cacheName !== IMAGE_CACHE &&
                            cacheName !== API_CACHE
                        ) {
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            // Take control of all clients
            self.clients.claim()
        ])
    );
});

/**
 * Fetch event - handle different caching strategies
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Handle different types of requests
    if (isStaticAsset(url)) {
        event.respondWith(handleStaticAsset(request));
    } else if (isImage(url)) {
        event.respondWith(handleImage(request));
    } else if (isAPICall(url)) {
        event.respondWith(handleAPICall(request));
    } else if (isHTMLPage(url, request)) {
        event.respondWith(handleHTMLPage(request));
    } else {
        event.respondWith(handleDynamic(request));
    }
});

/**
 * Check if request is for static asset
 */
function isStaticAsset(url) {
    return url.pathname.startsWith('/assets/') ||
        url.pathname.includes('.js') ||
        url.pathname.includes('.css') ||
        url.pathname.includes('.woff') ||
        url.pathname.includes('.woff2') ||
        url.pathname.includes('.ttf') ||
        url.pathname === '/manifest.json' ||
        url.pathname === '/favicon.ico' ||
        url.pathname === '/favicon.svg';
}

/**
 * Check if request is for image
 */
function isImage(url) {
    return url.pathname.includes('.jpg') ||
        url.pathname.includes('.jpeg') ||
        url.pathname.includes('.png') ||
        url.pathname.includes('.gif') ||
        url.pathname.includes('.webp') ||
        url.pathname.includes('.svg');
}

/**
 * Check if request is API call
 */
function isAPICall(url) {
    return url.pathname.startsWith('/api/');
}

/**
 * Check if request is for HTML page
 */
function isHTMLPage(url, request) {
    return request.destination === 'document' ||
        url.pathname === '/' ||
        (!url.pathname.includes('.') && !url.pathname.startsWith('/api/'));
}

/**
 * Handle static assets with cache-first strategy
 */
async function handleStaticAsset(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        console.error('Service Worker: Static asset error:', error);
        throw error;
    }
}

/**
 * Handle images with cache-first strategy and compression
 */
async function handleImage(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(IMAGE_CACHE);

            // Only cache images smaller than 5MB
            const contentLength = networkResponse.headers.get('content-length');
            if (!contentLength || parseInt(contentLength) < 5 * 1024 * 1024) {
                cache.put(request, networkResponse.clone());
            }
        }

        return networkResponse;
    } catch (error) {
        console.error('Service Worker: Image error:', error);
        // Return placeholder image for failed image requests
        return new Response(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f0f0f0"/><text x="100" y="100" text-anchor="middle" dy=".3em" fill="#999">Image unavailable</text></svg>',
            { headers: { 'Content-Type': 'image/svg+xml' } }
        );
    }
}

/**
 * Handle API calls with different strategies based on endpoint
 */
async function handleAPICall(request) {
    const url = new URL(request.url);

    // Network-first for critical endpoints
    if (NETWORK_FIRST_ROUTES.some(route => url.pathname.startsWith(route))) {
        return handleNetworkFirst(request, API_CACHE);
    }

    // Cache-first for less critical endpoints
    if (API_ENDPOINTS.some(endpoint => url.pathname.startsWith(endpoint))) {
        return handleCacheFirst(request, API_CACHE);
    }

    // Stale-while-revalidate for other API calls
    return handleStaleWhileRevalidate(request, API_CACHE);
}

/**
 * Handle HTML pages with network-first strategy and offline fallback
 */
async function handleHTMLPage(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Network failed, checking cache');

        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline page
        return getOfflinePage();
    }
}

/**
 * Handle dynamic content with stale-while-revalidate
 */
async function handleDynamic(request) {
    return handleStaleWhileRevalidate(request, DYNAMIC_CACHE);
}

/**
 * Network-first strategy
 */
async function handleNetworkFirst(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        throw error;
    }
}

/**
 * Cache-first strategy
 */
async function handleCacheFirst(request, cacheName) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.error('Service Worker: Network and cache failed:', error);
        throw error;
    }
}

/**
 * Stale-while-revalidate strategy
 */
async function handleStaleWhileRevalidate(request, cacheName) {
    const cachedResponse = await caches.match(request);
    // Lanzar la petición de red en paralelo
    const networkPromise = fetch(request)
        .then(async response => {
            if (response && response.ok) {
                try {
                    const cache = await caches.open(cacheName);
                    await cache.put(request, response.clone());
                } catch (e) {
                    console.error('[SW] Error al clonar/guardar response en caché', e);
                }
            }
            return response;
        })
        .catch(error => {
            return null;
        });

    // Si hay caché, devuélvela inmediatamente y actualiza en segundo plano
    if (cachedResponse) {
        networkPromise; // se ejecuta en segundo plano
        return cachedResponse;
    }
    // Si no hay caché, espera la respuesta de red
    const netResp = await networkPromise;
    return netResp;
}

/**
 * Get offline page
 */
function getOfflinePage() {
    return new Response(`
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sin conexión - Bubble of Talents</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-align: center;
                    padding: 20px;
                }
                .offline-content {
                    max-width: 400px;
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border-radius: 20px;
                    padding: 40px;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
                }
                h1 {
                    font-size: 2.5em;
                    margin-bottom: 20px;
                    font-weight: 300;
                }
                p {
                    font-size: 1.1em;
                    line-height: 1.6;
                    margin-bottom: 30px;
                    opacity: 0.9;
                }
                .retry-btn {
                    background: rgba(255, 255, 255, 0.2);
                    border: 2px solid rgba(255, 255, 255, 0.3);
                    color: white;
                    padding: 12px 30px;
                    border-radius: 50px;
                    font-size: 1em;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }
                .retry-btn:hover {
                    background: rgba(255, 255, 255, 0.3);
                    transform: translateY(-2px);
                }
                .icon {
                    font-size: 4em;
                    margin-bottom: 20px;
                    opacity: 0.7;
                }
            </style>
        </head>
        <body>
            <div class="offline-content">
                <div class="icon">📡</div>
                <h1>Sin conexión</h1>
                <p>No se pudo conectar a Bubble of Talents. Verifica tu conexión a internet e inténtalo de nuevo.</p>
                <button class="retry-btn" onclick="window.location.reload()">
                    Reintentar
                </button>
            </div>
        </body>
        </html>
    `, {
        headers: {
            'Content-Type': 'text/html',
            'Cache-Control': 'no-cache'
        }
    });
}

/**
 * Handle background sync for offline actions
 */
self.addEventListener('sync', (event) => {
    console.log('Service Worker: Background sync triggered:', event.tag);

    if (event.tag === 'background-sync-jobs') {
        event.waitUntil(syncJobApplications());
    } else if (event.tag === 'background-sync-profile') {
        event.waitUntil(syncProfileUpdates());
    }
});

/**
 * Sync job applications when back online
 */
async function syncJobApplications() {
    try {
        // Get pending job applications from IndexedDB or localStorage
        const pendingApplications = await getPendingApplications();

        for (const application of pendingApplications) {
            try {
                const response = await fetch('/api/jobs/apply', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(application)
                });

                if (response.ok) {
                    await removePendingApplication(application.id);
                }
            } catch (error) {
                console.error('Service Worker: Failed to sync application:', error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Background sync failed:', error);
    }
}

/**
 * Sync profile updates when back online
 */
async function syncProfileUpdates() {
    try {
        const pendingUpdates = await getPendingProfileUpdates();

        for (const update of pendingUpdates) {
            try {
                const response = await fetch('/api/auth/profile', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(update.data)
                });

                if (response.ok) {
                    await removePendingProfileUpdate(update.id);
                }
            } catch (error) {
                console.error('Service Worker: Failed to sync profile update:', error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Profile sync failed:', error);
    }
}

/**
 * Handle push notifications
 */
self.addEventListener('push', (event) => {

    const options = {
        body: 'Tienes nuevas notificaciones en Bubble of Talents',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: 'bubble-talents-notification',
        requireInteraction: false,
        actions: [
            {
                action: 'view',
                title: 'Ver',
                icon: '/static/images/view-icon.png'
            },
            {
                action: 'dismiss',
                title: 'Descartar',
                icon: '/static/images/dismiss-icon.png'
            }
        ]
    };

    if (event.data) {
        const data = event.data.json();
        options.body = data.body || options.body;
        options.title = data.title || 'Bubble of Talents';
    }

    event.waitUntil(
        self.registration.showNotification('Bubble of Talents', options)
    );
});

/**
 * Handle notification clicks
 */
self.addEventListener('notificationclick', (event) => {

    event.notification.close();

    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow('/dashboard')
        );
    } else if (event.action === 'dismiss') {
        // Just close the notification
        return;
    } else {
        // Default action - open the app
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

/**
 * Utility functions for IndexedDB operations
 * In a real implementation, these would use IndexedDB
 */
async function getPendingApplications() {
    // Placeholder - implement with IndexedDB
    return [];
}

async function removePendingApplication(id) {
    // Placeholder - implement with IndexedDB
}

async function getPendingProfileUpdates() {
    // Placeholder - implement with IndexedDB
    return [];
}

async function removePendingProfileUpdate(id) {
    // Placeholder - implement with IndexedDB
}

/**
 * Cache cleanup on storage pressure
 */
self.addEventListener('storage', (event) => {
    if (event.bucketInfo && event.bucketInfo.quota && event.bucketInfo.usage) {
        const usagePercentage = (event.bucketInfo.usage / event.bucketInfo.quota) * 100;

        if (usagePercentage > 80) {
            cleanupOldCache();
        }
    }
});

/**
 * Cleanup old cache entries
 */
async function cleanupOldCache() {
    try {
        const cache = await caches.open(DYNAMIC_CACHE);
        const requests = await cache.keys();

        // Sort by timestamp and remove oldest entries
        const sortedRequests = requests.sort((a, b) => {
            return new Date(a.headers.get('date') || 0) - new Date(b.headers.get('date') || 0);
        });

        // Remove oldest 25% of entries
        const toRemove = sortedRequests.slice(0, Math.floor(sortedRequests.length * 0.25));

        await Promise.all(toRemove.map(request => cache.delete(request)));
    } catch (error) {
        console.error('Service Worker: Cache cleanup failed:', error);
    }
}

