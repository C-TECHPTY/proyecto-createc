const CACHE_NAME = 'rifagrid-admin-v1';
const ASSETS = [
  './admin/login.php',
  './public/offline.html',
  './manifest.webmanifest',
  './public/assets/css/app.css',
  './public/assets/js/admin.js',
  './public/assets/js/pwa.js',
  './public/assets/icon.svg',
  './public/assets/admin-icon.svg'
];

function isRifasRequest(request) {
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
  if (!isRifasRequest(event.request)) return;

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
        .catch(() => caches.match(request).then((cached) => cached || caches.match('./public/offline.html')))
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

  const title = data.title || 'RifaGrid Admin';
  const options = {
    body: data.body || 'Tienes una nueva notificacion.',
    icon: './public/assets/admin-icon.svg',
    badge: './public/assets/admin-icon.svg',
    tag: data.tag || 'rifagrid-admin',
    renotify: true,
    data: { url: data.url || './admin/dashboard.php' }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || './admin/dashboard.php', self.location.href).href;

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
