/* NIS-REDAS Low-Bandwidth Service Worker
 * - Cache-First for fingerprinted static Vite assets (/build/assets/*) and images/icons
 * - Network-Only for authenticated HTML pages and API endpoints (never caches confidential data)
 * - Lightweight inline offline screen when device loses connectivity completely
 */
const STATIC_CACHE = 'nis-redas-static-v2';
const PRECACHE_ASSETS = [
    '/nis-logo.png',
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => Promise.allSettled(PRECACHE_ASSETS.map((url) => cache.add(url))))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) =>
            Promise.all(
                names
                    .filter((name) => name !== STATIC_CACHE)
                    .map((name) => caches.delete(name))
            )
        ).then(() => self.clients.claim())
    );
});

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/images/') ||
        url.pathname.startsWith('/assets/') ||
        url.pathname === '/nis-logo.png' ||
        url.pathname === '/manifest.json' ||
        /\.(?:css|js|woff2?|ttf|png|jpe?g|webp|svg|ico)$/i.test(url.pathname)
    );
}

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Static assets: Cache-First for instant 0ms loads on 2G/3G networks
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) {
                    return cached;
                }
                return fetch(event.request).then((networkResponse) => {
                    if (networkResponse && networkResponse.ok) {
                        const clone = networkResponse.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(event.request, clone));
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Navigation requests: Network-Only (never cache confidential HTML), with clean offline fallback
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => new Response(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline | NIS-REDAS</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#f4f7f5;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}.card{background:#fff;border-radius:16px;padding:24px;max-width:360px;width:100%;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08)}h1{font-size:1.15rem;color:#006633;margin:0 0 8px}p{font-size:.9rem;color:#475569;margin:0 0 16px}button{background:#006633;color:#fff;border:0;border-radius:10px;padding:12px 20px;font-weight:600;font-size:.95rem;width:100%}</style></head><body><div class="card"><h1>No Internet Connection</h1><p>You appear to be offline. Any in-progress form draft is preserved in your browser. Reconnect and tap Retry.</p><button onclick="location.reload()">Retry Connection</button></div></body></html>',
                { status: 503, headers: { 'Content-Type': 'text/html; charset=UTF-8' } }
            ))
        );
    }
});
