const CACHE_NAME = 'rifagrid-v9';
const ASSETS = [
  './',
  './index.php',
  './offline.html',
  './manifest.json',
  './assets/css/app.css',
  './assets/js/rifa.js',
  './assets/js/admin.js',
  './assets/js/pwa.js',
  './assets/icon.svg'
];

function isRifasPublicRequest(request) {
  const requestUrl = new URL(request.url);
  const workerBaseUrl = new URL('./', self.location.href);
  return requestUrl.origin === workerBaseUrl.origin
    && requestUrl.pathname.startsWith(workerBaseUrl.pathname);
}

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)).catch(() => undefined));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))));
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  if (!isRifasPublicRequest(event.request)) return;

  const request = event.request;
  const acceptsHtml = request.headers.get('accept')?.includes('text/html');

  if (acceptsHtml) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => undefined);
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match('./offline.html')))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      return cached || fetch(request).then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => undefined);
        return response;
      });
    })
  );
});

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = { body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'RifaGrid';
  const options = {
    body: data.body || 'Tienes una nueva notificacion.',
    icon: './assets/icon.svg',
    badge: './assets/icon.svg',
    tag: data.tag || 'rifagrid-admin',
    renotify: true,
    data: { url: data.url || './index.php' }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || './index.php', self.location.href).href;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url === target && 'focus' in client) {
          return client.focus();
        }
      }
      return clients.openWindow(target);
    })
  );
});
