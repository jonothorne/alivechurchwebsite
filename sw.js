/**
 * Alive Church Service Worker
 * Enables offline access and caching for PWA functionality
 */

const CACHE_VERSION = 'alive-church-v20';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const STUDY_CACHE = `${CACHE_VERSION}-studies`;

// Static assets to cache immediately on install
const STATIC_ASSETS = [
    '/',
    '/assets/css/style.css',
    '/manifest.json'
];

// Pages to cache with network-first strategy
const CACHEABLE_PAGES = [
    '/bible-study',
    '/reading-plans',
    '/events',
    '/about',
    '/visit',
    '/connect',
    '/give',
    '/blog'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('[SW] Installing service worker...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Caching static assets');
                // Cache each asset individually to avoid failing on missing files
                return Promise.allSettled(
                    STATIC_ASSETS.map(url =>
                        cache.add(url).catch(err => console.log('[SW] Failed to cache:', url, err))
                    )
                );
            })
            .then(() => self.skipWaiting())
            .catch(err => console.log('[SW] Error caching static assets:', err))
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating service worker...');
    event.waitUntil(
        caches.keys()
            .then(keys => {
                return Promise.all(
                    keys.filter(key => key !== STATIC_CACHE && key !== DYNAMIC_CACHE && key !== STUDY_CACHE)
                        .map(key => {
                            console.log('[SW] Removing old cache:', key);
                            return caches.delete(key);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests
    if (request.method !== 'GET') return;

    // Skip cross-origin requests
    if (url.origin !== location.origin) return;

    // Skip admin pages
    if (url.pathname.startsWith('/admin')) return;

    // Skip API calls
    if (url.pathname.startsWith('/api')) return;

    // Bible study pages - cache for offline reading
    if (url.pathname.startsWith('/bible-study/') && url.pathname.split('/').length > 2) {
        event.respondWith(networkFirstThenCache(request, STUDY_CACHE));
        return;
    }

    // Reading plan pages - cache for offline reading
    if (url.pathname.startsWith('/reading-plan/')) {
        event.respondWith(networkFirstThenCache(request, STUDY_CACHE));
        return;
    }

    // Static assets - cache first
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirstThenNetwork(request, STATIC_CACHE));
        return;
    }

    // HTML pages - network first with dynamic cache
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirstThenCache(request, DYNAMIC_CACHE));
        return;
    }

    // Default - network first
    event.respondWith(networkFirstThenCache(request, DYNAMIC_CACHE));
});

// Cache-first strategy (for static assets)
async function cacheFirstThenNetwork(request, cacheName) {
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
        console.log('[SW] Network request failed:', error);
        return caches.match('/offline');
    }
}

// Network-first strategy (for dynamic content)
async function networkFirstThenCache(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network request failed, trying cache:', request.url);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline page for navigation requests
        if (request.headers.get('accept')?.includes('text/html')) {
            const offlinePage = await caches.match('/offline');
            if (offlinePage) return offlinePage;

            // Fallback if offline page not cached
            return new Response(`
                <!DOCTYPE html>
                <html>
                <head><title>Offline</title><meta name="viewport" content="width=device-width"></head>
                <body style="font-family:system-ui;text-align:center;padding:2rem;">
                    <h1>You're Offline</h1>
                    <p>Please check your internet connection.</p>
                </body>
                </html>
            `, { headers: { 'Content-Type': 'text/html' } });
        }

        return new Response('Offline', { status: 503 });
    }
}

// Check if request is for a static asset
function isStaticAsset(pathname) {
    return pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot|ico)$/);
}

// Handle push notifications (for future use)
self.addEventListener('push', event => {
    if (!event.data) return;

    const data = event.data.json();
    const options = {
        body: data.body || '',
        icon: '/assets/imgs/icons/icon-192x192.png',
        badge: '/assets/imgs/icons/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/'
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Alive Church', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Focus existing window if available
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Background sync (for future use - saving highlights offline, etc.)
self.addEventListener('sync', event => {
    if (event.tag === 'sync-reading-progress') {
        event.waitUntil(syncReadingProgress());
    }
});

async function syncReadingProgress() {
    // Future: sync offline reading progress to server
    console.log('[SW] Syncing reading progress...');
}
