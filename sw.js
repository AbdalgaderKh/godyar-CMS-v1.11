/* Service Worker for Godyar CMS - offline cache + push notifications */
/* eslint-disable no-restricted-globals */

const CACHE_VERSION = 'v1';
const CACHE_NAME = `godyar-cache-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';

const CORE_ASSETS = [
  '/',
  OFFLINE_URL,
  '/assets/vendor/bootstrap/css/bootstrap.rtl.min.css',
  '/assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
];

/** Pre-cache core assets */
self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(CORE_ASSETS);
  })());
  self.skipWaiting();
});

/** Cleanup old caches */
self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : null)));
    await self.clients.claim();
  })());
});

/**
 * Fetch strategy:
 * - HTML navigations: network-first with offline fallback
 * - Other GET same-origin: cache-first then network; cache successful responses
 */
self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only handle GET and same-origin
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const accept = request.headers.get('accept') || '';
  const isNavigation = request.mode === 'navigate' || accept.includes('text/html');

  if (isNavigation) {
    event.respondWith((async () => {
      try {
        const response = await fetch(request);
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, response.clone());
        return response;
      } catch (e) {
        const cache = await caches.open(CACHE_NAME);
        return (await cache.match(request)) || (await cache.match(OFFLINE_URL));
      }
    })());
    return;
  }

  event.respondWith((async () => {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);
    if (cached) return cached;

    try {
      const response = await fetch(request);
      if (response?.ok) {
        cache.put(request, response.clone());
      }
      return response;
    } catch (e) {
      return new Response('', { status: 504 });
    }
  })());
});

/* Push Notifications (payload should include title/body/icon/url) */
self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = {};
  }

  const title = data.title || 'Godyar News';
  const options = {
    body: data.body || 'خبر جديد',
    icon: data.icon || '/icons/icon-192x192.png',
    badge: data.badge || '/icons/badge-72x72.png',
    data: { url: data.url || '/' },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const urlToOpen = event.notification?.data?.url
    ? event.notification.data.url
    : '/';

  event.waitUntil((async () => {
    const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });

    for (const client of allClients) {
      try {
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      } catch (e) {}
    }

    if (clients.openWindow) {
      return clients.openWindow(urlToOpen);
    }
  })());
});
