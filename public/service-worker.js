/**
 * LinguaCafe Service Worker (enhanced PWA)
 *
 * - Precache Mix-built assets dynamically from /mix-manifest.json
 * - Cache-first for static assets (css/js/fonts/images)
 * - Network-first for API requests with cache fallback
 * - Offline fallback page for navigations
 */

const VERSION = 'v3';
const STATIC_CACHE = `lc-static-${VERSION}`;
const RUNTIME_CACHE = `lc-runtime-${VERSION}`;
const OFFLINE_URL = '/offline.html';

// Utility: add URL to cache ignoring opaque failures
async function cacheAddAllSafe(cache, urls) {
  for (const url of urls) {
    try { await cache.add(url); } catch (e) { /* ignore */ }
  }
}

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    self.skipWaiting();
    const cache = await caches.open(STATIC_CACHE);

    // Precache offline page
    await cacheAddAllSafe(cache, [OFFLINE_URL]);

    // Precache Mix assets by reading /mix-manifest.json
    try {
      const res = await fetch('/mix-manifest.json', { cache: 'no-store' });
      if (res.ok) {
        const manifest = await res.json();
        const assets = Object.values(manifest);
        await cacheAddAllSafe(cache, assets);
      }
    } catch (e) {
      // continue; we still have runtime caching
    }
  })());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => {
      if (k !== STATIC_CACHE && k !== RUNTIME_CACHE) return caches.delete(k);
    }));
    await self.clients.claim();
  })());
});

// Navigation requests: network-first with offline fallback
async function handleNavigate(event) {
  try {
    const response = await fetch(event.request);
    return response;
  } catch (e) {
    const cache = await caches.open(STATIC_CACHE);
    const offline = await cache.match(OFFLINE_URL);
    return offline || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
  }
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Only same-origin handled
  if (url.origin !== location.origin) return;

  // Navigations
  if (req.mode === 'navigate') {
    event.respondWith(handleNavigate(event));
    return;
  }

  // API requests: network-first
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/settings/') || url.pathname.startsWith('/books') || url.pathname.startsWith('/playlists')) {
    event.respondWith((async () => {
      try {
        const res = await fetch(req);
        const cache = await caches.open(RUNTIME_CACHE);
        cache.put(req, res.clone());
        return res;
      } catch (e) {
        const cached = await caches.match(req);
        if (cached) return cached;
        throw e;
      }
    })());
    return;
  }

  // Static assets: cache-first with background update
  if (['style', 'script', 'image', 'font'].includes(req.destination)) {
    event.respondWith((async () => {
      const cached = await caches.match(req);
      const fetchPromise = fetch(req).then(async (res) => {
        const cache = await caches.open(STATIC_CACHE);
        cache.put(req, res.clone());
        return res;
      }).catch(() => cached);
      return cached || fetchPromise;
    })());
  }
});
