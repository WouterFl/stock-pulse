{{-- PWA head-tags, geïnjecteerd in Filament's <head> via een render hook (SP-24). --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#10b981">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Stock Pulse">
<meta name="mobile-web-app-capable" content="yes">

<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">

{{-- Service worker registratie (SP-27). Root-scope zodat /admin gedekt is. --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function (e) {
                console.warn('SW registratie faalde:', e);
            });
        });
    }
</script>

{{-- Web push subscription-helper (SP-29). Gebruikt door de notificatie-instellingen (SP-31). --}}
<script>
    (function () {
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(base64);
            return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
        }

        function csrf() {
            const el = document.querySelector('meta[name="csrf-token"]');
            return el ? el.getAttribute('content') : '';
        }

        async function send(url, method, body) {
            const res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
                body: body ? JSON.stringify(body) : null,
            });
            if (!res.ok) throw new Error('Request faalde: ' + res.status);
            return res.json();
        }

        const Push = {
            isSupported() {
                return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            },

            async getStatus() {
                if (!this.isSupported()) return 'unsupported';
                if (Notification.permission === 'denied') return 'denied';
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) return 'subscribed';
                return Notification.permission === 'granted' ? 'granted' : 'default';
            },

            async subscribe() {
                if (!this.isSupported()) throw new Error('Push wordt niet ondersteund in deze browser.');

                const permission = await Notification.requestPermission();
                if (permission !== 'granted') throw new Error('Toestemming geweigerd.');

                const key = document.querySelector('meta[name="vapid-public-key"]').getAttribute('content');
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(key),
                });

                const json = sub.toJSON();
                await send('/push-subscriptions', 'POST', {
                    endpoint: sub.endpoint,
                    keys: json.keys,
                    contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                });
                return true;
            },

            async unsubscribe() {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (!sub) return true;
                await send('/push-subscriptions', 'DELETE', { endpoint: sub.endpoint }).catch(() => {});
                await sub.unsubscribe();
                return true;
            },

            async test() {
                return send('/push-subscriptions/test', 'POST', {});
            },
        };

        window.StockPulsePush = Push;
    })();
</script>
