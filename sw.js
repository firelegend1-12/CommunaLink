const CACHE_NAME = 'communalink-system-v4';
const ASSETS_TO_CACHE = [
    '/',
    '/index.php',
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
    const acceptHeader = event.request.headers.get('Accept') || '';
    const isPageNavigation = event.request.mode === 'navigate' || acceptHeader.includes('text/html');
    const isDynamicRequest =
        requestUrl.pathname.endsWith('.php') ||
        requestUrl.pathname.startsWith('/api/') ||
        requestUrl.pathname.includes('/partials/');

    if (isDynamicRequest) {
        if (isPageNavigation) {
            return;
        }

        event.respondWith(
            fetch(event.request).catch(() => {
                if (acceptHeader.includes('application/json')) {
                    return new Response(JSON.stringify({
                        success: false,
                        error: 'Network request failed. Please check your connection and try again.'
                    }), {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: { 'Content-Type': 'application/json' }
                    });
                }

                return new Response('Service temporarily unavailable. Please refresh and try again.', {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: { 'Content-Type': 'text/plain' }
                });
            })
        );
        return;
    }
    
    // We avoid caching API or dynamic PHP endpoints indiscriminately.
    // Instead we do a Network-First strategy, falling back to cache.
    // Notice: if you want local caching to be "stale-while-revalidate", that's better for assets.
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
