const CACHE_NAME = 'cobranca-v1';
const CACHE_STATIC = 'cobranca-static-v1';
const CACHE_PAGES = 'cobranca-pages-v1';

const STATIC_ASSETS = [
    '/cobranca/app/css/app.css',
    '/cobranca/app/manifest.json',
    '/cobranca/app/icon.php?size=192',
    '/cobranca/app/icon.php?size=512',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_STATIC).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch(() => {});
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((k) => k !== CACHE_STATIC && k !== CACHE_PAGES && k !== CACHE_NAME)
                    .map((k) => caches.delete(k))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET') return;

    if (url.pathname.includes('/cobranca/app/') && url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    if (response && response.status === 200) {
                        const clone = response.clone();
                        caches.open(CACHE_PAGES).then((cache) => cache.put(event.request, clone));
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(event.request).then((cached) => {
                        return cached || new Response(
                            '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sem conexão</title><style>body{font-family:Roboto,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f5f7fa;color:#1e293b;text-align:center;padding:20px}i{font-size:3rem;color:#6C5CE7;margin-bottom:16px}h2{margin-bottom:8px}p{color:#94a3b8}</style></head><body><div><i class="fas fa-wifi-slash"></i><h2>Sem conexão</h2><p>Verifique sua internet e tente novamente.</p><button onclick="location.reload()" style="margin-top:16px;padding:10px 24px;background:#6C5CE7;color:#fff;border:none;border-radius:10px;font-size:0.95rem;font-weight:600;cursor:pointer">Tentar novamente</button></div></body></html>',
                            { headers: { 'Content-Type': 'text/html' } }
                        );
                    });
                })
        );
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => {
            return cached || fetch(event.request).then((response) => {
                if (response && response.status === 200 && url.origin === self.location.origin) {
                    const clone = response.clone();
                    caches.open(CACHE_STATIC).then((cache) => cache.put(event.request, clone));
                }
                return response;
            }).catch(() => {
                return cached;
            });
        })
    );
});

self.addEventListener('push', (event) => {
    let data = { title: 'Cobrança', body: 'Você tem uma notificação.', icon: '/cobranca/app/icon.php?size=192', url: '/cobranca/app/dashboard.php' };

    if (event.data) {
        try {
            data = { ...data, ...event.data.json() };
        } catch (e) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon,
            badge: data.icon,
            vibrate: [200, 100, 200],
            tag: 'cobranca-push',
            renotify: true,
            data: { url: data.url }
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/cobranca/app/dashboard.php';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes('/cobranca/app/') && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});