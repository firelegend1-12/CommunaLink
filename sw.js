const CACHE_NAME = 'communalink-system-v1';
const ASSETS_TO_CACHE = [
    '/',
    '/index.php',
    '/assets/images/barangay-logo.png'
];

self.addEventListener('install', (e) => {
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
        })
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    
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
                // Return cached fallback if network fails
            });

            return fetchPromise.catch(() => cachedResponse);
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const rawTarget = (event.notification && event.notification.data && event.notification.data.link)
        ? String(event.notification.data.link)
        : '/resident/notifications.php';

    const targetUrl = new URL(rawTarget, self.registration.scope).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }

            return null;
        })
    );
});
