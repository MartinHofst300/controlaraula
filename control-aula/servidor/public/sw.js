/**
 * SERVICE WORKER - Control de Aula (PWA)
 *
 * Estrategias:
 *  - API (/api/...) y peticiones que no son GET: NUNCA se cachean (estado siempre real).
 *  - Paginas PHP (navegacion): siempre desde la red. Si no hay conexion, se muestra offline.html.
 *  - Archivos estaticos propios (CSS, JS, iconos): "stale-while-revalidate"
 *    (responde rapido desde cache y actualiza en segundo plano).
 *  - Bootstrap / Bootstrap Icons desde CDN (URLs versionadas): "cache-first".
 *
 * Al cambiar algo importante de este archivo, subir VERSION para renovar la cache.
 */

const VERSION = 'v2.0.0';
const CACHE_ESTATICO = `control-aula-estatico-${VERSION}`;
const CACHE_CDN = `control-aula-cdn-${VERSION}`;
const PAGINA_OFFLINE = 'offline.html';

const PRECACHE = [
    PAGINA_OFFLINE,
    'manifest.json',
    'assets/css/estilos.css',
    'assets/js/panel.js',
    'assets/js/pwa.js',
    'assets/icons/icon.svg',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
    'assets/icons/apple-touch-icon.png'
];

const HOSTS_CDN = ['cdn.jsdelivr.net'];

// 1. Instalacion: guardar en cache los archivos basicos
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_ESTATICO)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

// 2. Activacion: borrar caches de versiones anteriores
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(
                claves
                    .filter((clave) => clave.startsWith('control-aula-') && clave !== CACHE_ESTATICO && clave !== CACHE_CDN)
                    .map((clave) => caches.delete(clave))
            ))
            .then(() => self.clients.claim())
    );
});

// 3. Intercepcion de peticiones
self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Solo GET; POST de luces/aire/proyector/login siempre van directo a la red
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // API: jamas cachear (el estado de los equipos tiene que ser el real)
    if (url.origin === self.location.origin && url.pathname.includes('/api/')) return;

    // Navegacion a paginas (index.php, login.php, ...): red primero, offline.html si falla
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match(PAGINA_OFFLINE))
        );
        return;
    }

    // Bootstrap y Bootstrap Icons desde CDN: cache-first
    if (HOSTS_CDN.includes(url.hostname)) {
        event.respondWith(
            caches.open(CACHE_CDN).then((cache) =>
                cache.match(req).then((enCache) => {
                    if (enCache) return enCache;
                    return fetch(req).then((resp) => {
                        if (resp && (resp.ok || resp.type === 'opaque')) {
                            cache.put(req, resp.clone());
                        }
                        return resp;
                    });
                })
            )
        );
        return;
    }

    // Archivos estaticos propios: network-first (siempre toma la ultima version y usa cache solo si no hay red)
    if (url.origin === self.location.origin && /\.(css|js|png|svg|ico|json|woff2?)$/i.test(url.pathname)) {
        event.respondWith(
            caches.open(CACHE_ESTATICO).then((cache) =>
                fetch(req)
                    .then((resp) => {
                        if (resp && resp.ok) cache.put(req, resp.clone());
                        return resp;
                    })
                    .catch(() => cache.match(req, { ignoreSearch: true }))
            )
        );
    }
});
