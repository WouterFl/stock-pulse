/**
 * Stock Pulse service worker (SP-27 / SP-29).
 *
 * Strategie:
 *  - network-first voor navigaties, /admin/*, /livewire/* en API-calls
 *    (we willen nooit stale koersen/alerts tonen) → fallback naar offline.html
 *  - cache-first voor statische assets (build, icons, fonts)
 *
 * Service worker draait vanaf root-scope (/sw.js) zodat hij het hele /admin-pad dekt.
 */
const VERSION = 'v1';
const CACHE_NAME = `stock-pulse-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const APP_SHELL = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

function isStaticAsset(url) {
    return /\.(?:css|js|woff2?|png|jpg|jpeg|svg|gif|ico)$/.test(url.pathname) ||
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/js/filament/') ||
        url.pathname.startsWith('/css/filament/');
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Alleen GET cachen; laat POST/PUT/etc. ongemoeid.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Externe origins niet afhandelen.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Cache-first voor statische assets.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) =>
                cached ||
                fetch(request).then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    return response;
                }).catch(() => cached)
            )
        );
        return;
    }

    // Network-first voor navigaties + dynamische routes (admin/livewire/api).
    const isNavigation = request.mode === 'navigate';
    const isDynamic = url.pathname.startsWith('/admin') ||
        url.pathname.startsWith('/livewire') ||
        url.pathname.startsWith('/api') ||
        url.pathname.startsWith('/horizon');

    if (isNavigation || isDynamic) {
        event.respondWith(
            fetch(request).catch(() =>
                isNavigation ? caches.match(OFFLINE_URL) : Response.error()
            )
        );
        return;
    }

    // Overige GET-requests: probeer netwerk, val terug op cache.
    event.respondWith(fetch(request).catch(() => caches.match(request)));
});

/**
 * Push-notificaties (SP-29): toon de notificatie op basis van de payload.
 */
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let data = {};
    try {
        data = event.data.json();
    } catch (e) {
        data = { title: 'Stock Pulse', body: event.data.text() };
    }

    // De webpush-package nest custom payload onder `data`; url zit daar.
    const url = (data.data && data.data.url) || data.url || '/admin/alerts';

    const options = {
        body: data.body,
        icon: data.icon || '/icons/icon-192.png',
        badge: data.badge || '/icons/badge-72.png',
        data: { url: url },
        tag: data.tag,
        renotify: !!data.tag,
        requireInteraction: !!data.requireInteraction,
        vibrate: data.vibrate,
    };

    event.waitUntil(self.registration.showNotification(data.title || 'Stock Pulse', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || '/admin/alerts';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            return self.clients.openWindow(target);
        })
    );
});
