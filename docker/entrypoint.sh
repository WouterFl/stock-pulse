#!/bin/sh
set -e

wait_for_db() {
    echo "[entrypoint] Waiting for database at ${DB_HOST}..."
    until php -r "
try {
    \$dsn = 'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE');
    new PDO(\$dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; do
        sleep 2
    done
    echo "[entrypoint] Database ready."
}

# Zorg dat storage-mappen bestaan na het mounten van het volume
mkdir -p storage/framework/cache \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public \
         storage/logs \
         bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

case "$1" in
    /usr/bin/supervisord)
        # Alleen de web-container voert migraties + caching uit.
        wait_for_db
        echo "[entrypoint] Running migrations..."
        php artisan migrate --force
        echo "[entrypoint] Linking storage..."
        php artisan storage:link --force 2>/dev/null || true
        echo "[entrypoint] Caching config, routes, views and events..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan event:cache
        echo "[entrypoint] Starting supervisord (php-fpm + nginx)..."
        exec "$@"
        ;;
    php)
        # Worker-/scheduler-/reverb-containers: wachten op DB, dan starten.
        wait_for_db
        exec "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
