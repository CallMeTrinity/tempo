/*
 * Service worker Tempo — cache léger (coquille de l'app).
 *
 * Stratégies :
 *  - Navigations (mode 'navigate') : network-first, fallback cache si hors-ligne.
 *  - Assets statiques same-origin (/assets/...) : stale-while-revalidate.
 *  - Tout le reste (POST de saisie, cross-origin) : passe au réseau sans interception.
 *
 * Le nom de cache est versionné : incrémenter à chaque déploiement pour purger.
 */
const CACHE = 'tempo-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // On ne gère que les GET same-origin. Les POST (saisie) passent tels quels.
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Navigations : network-first avec repli sur le cache.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match('/')))
        );
        return;
    }

    // Assets bundlés (CSS/JS/images via AssetMapper) : stale-while-revalidate.
    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(
            caches.open(CACHE).then((cache) =>
                cache.match(request).then((cached) => {
                    const network = fetch(request)
                        .then((response) => {
                            if (response && response.status === 200) {
                                cache.put(request, response.clone());
                            }
                            return response;
                        })
                        .catch(() => cached);
                    return cached || network;
                })
            )
        );
    }
});
