const CACHE_NAME = 'communalink-system-v5';
const ASSETS_TO_CACHE = [
    '/assets/images/barangay-logo.png'
];

self.addEventListener('install', (e) => {
    self.skipWaiting();
    e.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        }).catch(err => console.log('Install caching failed:', err))
    );
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(keyList.map((key) => {
                if (key !== CACHE_NAME) {
                    return caches.delete(key);
                }
            }));
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const requestUrl = new URL(event.request.url);

    const isDynamicRequest =
        requestUrl.pathname.endsWith('.php') ||
        requestUrl.pathname.startsWith('/api/') ||
        requestUrl.pathname.includes('/partials/');

    // Let dynamic requests hit the network so errors surface directly.
    if (isDynamicRequest) {
        return;
    }
    
    // Cache static assets with a network-first strategy.
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            const fetchPromise = fetch(event.request).then((networkResponse) => {
                // Ensure we only cache valid responses
                if(networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                    // we don't want to aggressively cache dashboard html without network
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                }
                return networkResponse;
            }).catch(() => {
                return cachedResponse || new Response('Offline', {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: { 'Content-Type': 'text/plain' }
                });
            });

            return fetchPromise;
        })
    );
});
