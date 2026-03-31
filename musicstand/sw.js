/**
 * Music Stand Service Worker
 * Provides offline functionality for the PWA
 */

const CACHE_NAME = 'musicstand-v1';
const STATIC_ASSETS = [
    '/musicstand/',
    '/musicstand/assets/css/app.css',
    '/musicstand/assets/js/app.js',
    '/musicstand/assets/js/swipe.js',
    '/adminnew/assets/js/chord-transposer.js',
    '/musicstand/manifest.json'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

// Fetch event - network first, fallback to cache
self.addEventListener('fetch', event => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip API requests (always go to network)
    if (event.request.url.includes('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Clone the response to cache it
                const responseClone = response.clone();
                caches.open(CACHE_NAME)
                    .then(cache => cache.put(event.request, responseClone));
                return response;
            })
            .catch(() => {
                // Network failed, try cache
                return caches.match(event.request)
                    .then(response => response || new Response('Offline', { status: 503 }));
            })
    );
});

// Handle messages from the app
self.addEventListener('message', event => {
    if (event.data.action === 'skipWaiting') {
        self.skipWaiting();
    }
});
