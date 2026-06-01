# ── Stage 1: PHP dependencies ────────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader --ignore-platform-reqs

# ── Stage 2: Build frontend assets ───────────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app
# Flux/Filament CSS leeft in vendor — kopieer het vóór de Vite-build
COPY --from=vendor /app/vendor ./vendor
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

# ── Stage 3: Production image ─────────────────────────────────────────────────
FROM php:8.4-fpm-alpine

# Runtime + build dependencies. Redis (phpredis) en gmp (web-push) worden via
# pecl/docker-php-ext toegevoegd; build-deps daarna weer verwijderd.
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        libpng-dev \
        libjpeg-turbo-dev \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
        icu-dev \
        gmp-dev \
        linux-headers \
        zip \
        unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring bcmath gd zip xml pcntl intl opcache gmp \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

# Applicatie-broncode
COPY . .

# Hergebruik vendor uit stage 1 (al geoptimaliseerd)
COPY --from=vendor /app/vendor ./vendor

# Gebouwde frontend-assets uit stage 2
COPY --from=assets /app/public/build ./public/build

# Docker config files
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p /var/log/supervisor storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
