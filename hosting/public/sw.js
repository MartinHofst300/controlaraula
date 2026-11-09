/**
 * Service Worker (PWA) - Control de Aula
 * Habilita la instalación nativa en Android, iOS y Escritorio (Chrome / Edge)
 * y gestiona caché de recursos estáticos manteniendo las llamadas API en vivo.
 */

const CACHE_NAME = 'control-aula-pwa-v1';
const STATIC_ASSETS = [
  './manifest.json',
  './assets/css/estilos.css',
  './assets/js/panel.js',
  './assets/js/pwa.js',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
  './assets/icons/apple-touch-icon.png',
  './assets/icons/icon.svg'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS).catch(() => {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
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
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Nunca interceptar métodos que no sean GET ni endpoints de API en tiempo real
  if (req.method !== 'GET' || req.url.includes('/api/')) {
    return;
  }

  // Estrategia Network-First con respaldo en Caché para tener siempre el estado al día
  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res && res.status === 200 && res.type === 'basic') {
          const clone = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, clone)).catch(() => {});
        }
        return res;
      })
      .catch(() => caches.match(req))
  );
});

