#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Stock Pulse — app deploy
#
# Gebruik: ./deploy.sh [--skip-pull]
# Beheert de app-stack (docker-compose.yml): mysql, redis, app, horizon,
# scheduler en reverb.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

cd "$(dirname "$0")"

SKIP_PULL=false
for arg in "$@"; do
    [[ "$arg" == "--skip-pull" ]] && SKIP_PULL=true
done

echo "▶ Stock Pulse — app deploy $(date '+%Y-%m-%d %H:%M:%S')"

# ── Eerste keer instellen? ────────────────────────────────────────────────────
if [ ! -f .env.docker ]; then
    echo ""
    echo "  STAP 1: .env.docker aanmaken"
    echo "    cp .env.docker.example .env.docker && nano .env.docker"
    echo ""
    echo "  Vul minimaal in:"
    echo "    APP_KEY            →  php artisan key:generate --show"
    echo "    DB_PASSWORD, DB_ROOT_PASSWORD"
    echo "    REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET"
    echo "    VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY  →  php artisan webpush:vapid --show"
    echo "    ADMIN_PASSWORD"
    echo ""
    echo "  STAP 2: Caddy bijwerken (zie infra/Caddyfile) en herladen"
    echo "    cat infra/Caddyfile >> /etc/caddy/Caddyfile && systemctl reload caddy"
    echo ""
    echo "  STAP 3: Draai dit script opnieuw."
    exit 1
fi

# Docker Compose leest .env voor variabele-substitutie
[ -e .env ] || ln -s .env.docker .env

# ── DNS check ─────────────────────────────────────────────────────────────────
APP_DOMAIN=$(grep '^APP_DOMAIN=' .env.docker | cut -d= -f2 | tr -d '"')
if [ -n "$APP_DOMAIN" ]; then
    SERVER_IP=$(curl -sf https://api.ipify.org 2>/dev/null || echo "onbekend")
    DOMAIN_IP=$(getent hosts "$APP_DOMAIN" 2>/dev/null | awk '{print $1}' || echo "onbekend")
    if [ "$SERVER_IP" != "$DOMAIN_IP" ]; then
        echo "WAARSCHUWING: $APP_DOMAIN → $DOMAIN_IP, maar deze server is $SERVER_IP"
        echo "  Controleer het DNS A-record."
        echo ""
    fi
fi

# ── Deployen ──────────────────────────────────────────────────────────────────
if [ "$SKIP_PULL" = false ]; then
    echo "▶ Pulling latest code..."
    git pull --ff-only
fi

APP_PROJECT=$(grep '^COMPOSE_PROJECT_NAME=' .env.docker | cut -d= -f2 | tr -d '"' || echo "stock-pulse")
COMPOSE_APP="docker compose -p ${APP_PROJECT}"

echo "▶ Building Docker images..."
$COMPOSE_APP build --pull

echo "▶ Starting services..."
$COMPOSE_APP up -d --remove-orphans

echo "▶ Running migrations..."
$COMPOSE_APP exec -T app php artisan migrate --force

echo "▶ Seeding admin user + bedrijven (idempotent)..."
$COMPOSE_APP exec -T app php artisan db:seed --force || true

echo ""
echo "▶ Status:"
$COMPOSE_APP ps

PORT=$(grep '^APP_PORT=' .env.docker | cut -d= -f2 | tr -d ' ')
echo ""
echo "✓ App deploy complete."
echo "  Intern:   http://127.0.0.1:${PORT:-8003}"
echo "  Publiek:  https://${APP_DOMAIN}/admin"
echo "  Horizon:  https://${APP_DOMAIN}/horizon"
echo "  Logs:     $COMPOSE_APP logs -f"
echo "  Shell:    $COMPOSE_APP exec app sh"
