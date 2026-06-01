# PWA & Push — handmatige test-checklist

PWA en push zijn lastig automatisch te testen; deze checklist maakt het
reproduceerbaar. Voer uit in Chrome (desktop) tenzij anders vermeld.

## Voorbereiding

- [ ] `php artisan webpush:vapid` uitgevoerd; `VAPID_*` staan in `.env`
- [ ] `npm run build` gedraaid (Node 20+)
- [ ] App draait over `https://` of via `localhost`
- [ ] Reverb draait (`php artisan reverb:start`) en Horizon (`php artisan horizon`)

## PWA / installable

- [ ] Install-prompt verschijnt in Chrome (adresbalk → installeren)
- [ ] Manifest geldig — Lighthouse PWA-audit = pass
- [ ] App opent standalone na installatie
- [ ] Offline (DevTools → Network → Offline): app shell laadt, koersen-pagina
      toont `offline.html` i.p.v. een verbindingsfout
- [ ] Koersen/alerts-pagina's worden **niet** uit cache geserveerd (network-first)

## Push permission flow

- [ ] `/admin/notification-settings` → "Schakel push in" → permission granted
- [ ] Subscription verschijnt in de DB (`push_subscriptions`) en in "Actieve devices"
- [ ] "Stuur test-notificatie" → notificatie verschijnt, óók met tab gesloten
- [ ] Klik op notificatie opent de juiste URL (`/admin`)

## Alerts → push

- [ ] Trigger een `critical` alert (bv. via een geseede uitschieter in de queue)
- [ ] Alle gesubscribede users ontvangen een push met klikbare URL
      (`/admin/alerts?highlight=…`) en `requireInteraction`
- [ ] `info`-alert geeft **geen** push (alleen in-app feed)
- [ ] Quiet hours aan → binnen het interval komt er geen push
- [ ] Categorie uitgeschakeld → dat type alert pusht niet
- [ ] Min-severity `critical` → `warning`-alert pusht niet

## Realtime feed

- [ ] Twee browservensters open op `/admin/alerts`
- [ ] Alert triggeren → in beide vensters verschijnt direct een toast + feed-update
      zonder refresh (Reverb)

## Cleanup

- [ ] Endpoint handmatig corrumperen → bij volgende push wordt subscription
      verwijderd (410/404) en blijven er geen eindeloos retryende jobs in Horizon
- [ ] `php artisan push:cleanup-stale --days=90` verwijdert inactieve subscriptions

## iOS (Safari 16.4+)

- [ ] App eerst installeren: Deel → "Zet op beginscherm"
- [ ] Open vanuit beginscherm → permission flow → push ontvangen
- [ ] De iOS-hint banner verschijnt in Safari zolang de app niet als PWA draait
