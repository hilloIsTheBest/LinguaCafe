/**
 * Service Worker for PWA Caching
 *
 * This worker uses a simple cache-first strategy for static assets and a network-first
 * strategy for API calls to keep data fresh while still providing offline support.
 */

const CACHE_NAME = 'linguacafe-cache-v1';
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/css/app.css',
    '/js/app.js',
    // Add any other assets that should be precached
];

// Install event – cache static assets
self.addEventListener('install', (event) => {
    console.log('[SW] Install');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
});

// Activate event – clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activate');
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            )
        )
    );
});

// Fetch event – serve from cache or network
self.addEventListener('fetch', (event) => {
    const requestUrl = new URL(event.request.url);

    // API requests: try network first, fallback to cache
    if (requestUrl.origin === location.origin && requestUrl.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    // Clone and store in cache for future use
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, responseClone));
                    return response;
                })
                .catch(() => caches.match(event.request))
        );
    } else {
        // Static assets: cache-first strategy
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(event.request);
            })
        );
    }
});
