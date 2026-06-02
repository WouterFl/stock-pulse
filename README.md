# Stock Pulse

Laravel + Filament webapp voor het monitoren van beursgenoteerde bedrijven: bedrijvenbeheer, periodiek scrapen van koersdata via meerdere providers (queue-based), historische koersopslag met grafieken, nieuws-aggregatie en een alertsysteem voor grote koersbewegingen — met realtime updates en web-push.

## Stack

- **Laravel 13** (PHP 8.4)
- **Filament 5** — admin panel op `/admin`
- **Laravel Horizon** — queue-dashboard op `/horizon` (vereist Redis)
- **Laravel Reverb** — realtime websockets (Sprint 4)
- **Redis** — queues + Horizon
- **Chart.js** — koersgrafieken (Sprint 2)
- **Vite 8 + Tailwind 4** — frontend assets

## Vereisten

- PHP 8.4, Composer 2.9+
- **Node 20+** (Vite 8). Op deze machine: gebruik nvm v22 →
  `export PATH="$HOME/.nvm/versions/node/v22.12.0/bin:$PATH"`
- Redis (`brew install redis && brew services start redis`)

## Setup

De database is **MySQL** (zowel lokaal als in productie). Lokaal draait MySQL in
een eigen container op poort `3307`:

```bash
composer install
cp .env.example .env                  # vul DB_PASSWORD / DB_ROOT_PASSWORD in
php artisan key:generate

docker compose -f docker-compose.dev.yml up -d   # lokale MySQL op 127.0.0.1:3307
php artisan migrate --seed            # maakt admin-user + 5 testbedrijven

npm install && npm run build          # met Node 20+

# Vul vast wat data:
php artisan news:fetch                # nieuws per bedrijf + markt-RSS
php artisan quotes:dispatch           # koersen (verwerk daarna de queue: php artisan queue:work)
```

> Tests draaien op een in-memory sqlite (zie `phpunit.xml`) en hebben dus géén
> draaiende MySQL nodig.

### Inloggen

Het admin-panel staat op `http://localhost:8000/admin`.

Standaard admin (via `AdminUserSeeder`, override-baar met `ADMIN_EMAIL` / `ADMIN_PASSWORD`):

- **E-mail:** `admin@stockpulse.test`
- **Wachtwoord:** `password`

Een extra user maken kan ook met `php artisan make:filament-user`.

## Draaien (lokaal)

```bash
# Webserver
php artisan serve

# Queues — via Horizon (vereist Redis)
php artisan horizon

# Realtime (Sprint 4+)
php artisan reverb:start

# Scheduler (dispatcht scrape-/news-jobs)
php artisan schedule:work

# Frontend in watch-mode (Node 20+)
npm run dev
```

Horizon-dashboard: `http://localhost:8000/horizon` (zichtbaar voor ingelogde users).

### Nieuws handmatig ophalen

Zonder draaiende scheduler kun je on-demand ophalen:

```bash
php artisan news:fetch                      # nu meteen ophalen uit alle providers
php artisan news:backfill --days=90         # historie per bedrijf via Finnhub (vereist FINNHUB_API_KEY)
php artisan news:backfill --days=180 --ticker=AAPL
```

Nieuwsbronnen (zie `config/news.php`): een **per-ticker Yahoo-feed** (vult de
bedrijfs-nieuwstabs, geen key nodig), brede markt-RSS, en optioneel Marketaux /
NewsAPI / Finnhub. Finnhub's `company-news` levert historie met datumbereik.

## Configuratie

| Bestand | Doel |
|---|---|
| `config/quotes.php` | Provider-volgorde + drivers voor koersdata (Sprint 2) |
| `config/news.php` | RSS-feeds en news-API's (Sprint 3) |
| `config/alerts.php` | Drempels en statistische detectie (Sprint 4) |

API-keys (optioneel) en VAPID-keys (web push, Sprint 5) staan in `.env` — zie `.env.example`.

## Deployment (Docker)

Productie draait als Docker-stack (zelfde opzet als de andere WPPF-projecten):
een multi-stage image (Composer → Vite-build op Node 22 → `php:8.4-fpm-alpine`
met nginx + supervisor), met daarnaast MySQL, Redis, Horizon, een scheduler en Reverb.

| Service | Rol |
|---|---|
| `app` | nginx + PHP-FPM (poort 8003 → Caddy) |
| `mysql` | MySQL 8 |
| `redis` | queues + Horizon + broadcasting |
| `horizon` | queue-workers (`quotes`/`news`/`push`) |
| `scheduler` | `schedule:work` (scrape-/news-/prune-/cleanup-jobs) |
| `reverb` | websockets (poort 8083 → Caddy `/app/*`) |

Workers wachten tot `app` healthy is (migraties klaar) voordat ze starten.

**Deployen op de VPS:**

```bash
cp .env.docker.example .env.docker     # vul APP_KEY, DB_*, REVERB_*, VAPID_*, ADMIN_PASSWORD in
cat infra/Caddyfile >> /etc/caddy/Caddyfile && systemctl reload caddy
./deploy.sh                            # build + up + migrate + seed
```

`deploy.sh` regelt de `.env`-symlink, DNS-check, image-build, migraties en seeding.
Caddy termineert TLS en proxiet `sp.wppf-development.com` → `127.0.0.1:8003`,
en `/app/*` → `127.0.0.1:8083` (Reverb).

## PWA & Push

Stock Pulse is een installeerbare PWA met web-push-notificaties voor alerts.

### VAPID-setup

```bash
php artisan webpush:vapid     # vult VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY in .env
```

Zet `VAPID_SUBJECT=mailto:jij@example.com`. De publieke key wordt aan de
frontend doorgegeven via een `<meta name="vapid-public-key">` in het Filament-panel.

### Service worker

- Gehost vanaf **root scope** (`/sw.js`) zodat het hele `/admin`-pad gedekt is.
- **Network-first** voor navigaties, `/admin/*`, `/livewire/*` en API (nooit stale koersen/alerts).
- **Cache-first** voor statische assets (`/build`, `/icons`, fonts).
- Offline fallback: `public/offline.html`.

**Troubleshooten:** service workers werken alleen over **HTTPS** (behalve op
`localhost`). Na wijzigingen in `sw.js`: DevTools → Application → Service Workers
→ *Update on reload* of *Unregister*. De cacheversie zit in `VERSION` bovenin `sw.js`.

### Lighthouse-audit

Chrome DevTools → Lighthouse → categorie *Progressive Web App* → *Analyze*.
Verwacht: manifest geldig, installable, service worker geregistreerd.

### Browser-matrix

| Platform | Push |
|---|---|
| Chrome/Edge desktop + Android | ✅ |
| Firefox desktop + Android | ✅ |
| Safari macOS | ✅ |
| Safari iOS 16.4+ | ✅ **alleen** na "Zet op beginscherm" (geïnstalleerde PWA) |

De instellingenpagina toont op iOS automatisch een hint hierover.

### Handmatige test-checklist

Zie [`docs/PWA-PUSH-CHECKLIST.md`](docs/PWA-PUSH-CHECKLIST.md).

## Tests

```bash
php artisan test
./vendor/bin/pint            # code style
```

Geautomatiseerde dekking o.a.: `PushSubscriptionControllerTest`,
`AlertNotificationTest`, `AlertPushTest`, `PushCleanupTest`,
`NotificationSettingsPageTest`, plus de quote-/news-/alert-pipelines.

## Projectstatus

Opgebouwd per sprint (zie LitePM-project **SP**):

- **Sprint 1 — Foundation & Companies** ✅ Filament/Horizon/Reverb-setup, Company CRUD, PWA-assets
- **Sprint 2 — Quotes & Charts** ✅ Provider-fallback scraping, queue-based fetch, Chart.js-detailpagina
- **Sprint 3 — News Aggregation** ✅ RSS + API-providers, dedup, artikel→bedrijf-matcher, nieuwsoverzicht
- **Sprint 4 — Alerts & Realtime** ✅ Absolute + statistische detectie, news-spike, Reverb-broadcasting, alerts-feed
- **Sprint 5 — PWA & Push Notifications** ✅ Service worker, web push, voorkeuren + quiet hours, cleanup
