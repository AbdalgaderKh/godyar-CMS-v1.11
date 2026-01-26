/* Service Worker (safe defaults)
 * - Avoids caching/admin interception to prevent CSRF/session issues
 * - Caches only a minimal offline page
 * - Runtime caches static assets for frontend GET requests
 */

// Bump this value whenever you change caching behaviour to force a Service Worker refresh.
const CACHE_NAME = 'godyar-cache-v4';
const OFFLINE_URL = '/offline.html';

// Minimal precache (keep this short and ensure files exist)
const PRECACHE_URLS = [
  OFFLINE_URL,
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.map((key) => (key !== CACHE_NAME ? caches.delete(key) : Promise.resolve())));
      await self.clients.claim();
    })()
  );
});

self.addEventListener('message', (event) => {
  if (event && event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

function isAdminRequest(url) {
  try {
    return url.pathname.startsWith('/admin');
  } catch (e) {
    return false;
  }
}

function isStaticAsset(url) {
  const p = url.pathname;
  return (
    p.endsWith('.css') || p.endsWith('.js') || p.endsWith('.png') || p.endsWith('.jpg') ||
    p.endsWith('.jpeg') || p.endsWith('.webp') || p.endsWith('.svg') || p.endsWith('.woff') ||
    p.endsWith('.woff2') || p.endsWith('.ttf') || p.endsWith('.ico')
  );
}

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Never handle non-GET (prevents CSRF/session problems)
  if (req.method !== 'GET') {
    return;
  }

  const url = new URL(req.url);

  // Do not intercept admin at all
  if (isAdminRequest(url)) {
    return;
  }

  // For same-origin static assets: cache-first
  if (url.origin === self.location.origin && isStaticAsset(url)) {
    event.respondWith(
      (async () => {
        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(req);
        if (cached) return cached;
        try {
          const res = await fetch(req);
          // Cache only successful (basic) responses
          if (res && res.ok) {
            cache.put(req, res.clone());
          }
          return res;
        } catch (e) {
          return cached || Response.error();
        }
      })()
    );
    return;
  }

  // For navigations: network-first with offline fallback
  if (req.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          const res = await fetch(req);
          return res;
        } catch (e) {
          const cache = await caches.open(CACHE_NAME);
          const offline = await cache.match(OFFLINE_URL);
          return offline || new Response('Offline', { status: 503, statusText: 'Offline' });
        }
      })()
    );
  }
});
