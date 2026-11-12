# syntax=docker/dockerfile:1

# ---------- 1. Dépendances PHP ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-interaction --no-progress --no-scripts \
    --prefer-dist --optimize-autoloader \
    --ignore-platform-reqs

# ---------- 2. Build des assets (Vite / thème Filament) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------- 3. Image finale : nginx + PHP-FPM ----------
FROM php:8.4-fpm-alpine AS app
LABEL authors="mphrygien"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

RUN apk add --no-cache nginx supervisor \
    && install-php-extensions \
        pdo_mysql intl zip gd bcmath exif pcntl opcache redis \
    && sed -i 's/^user .*/user www-data;/' /etc/nginx/nginx.conf \
    && rm -f /etc/nginx/http.d/default.conf

# Configuration PHP (le timeout par défaut de 30 s provoquait le 500)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "memory_limit=512M"; \
        echo "max_execution_time=120"; \
        echo "max_input_time=120"; \
        echo "upload_max_filesize=64M"; \
        echo "post_max_size=64M"; \
        echo "opcache.enable=1"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.memory_consumption=192"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "realpath_cache_size=4096K"; \
        echo "realpath_cache_ttl=600"; \
    } > "$PHP_INI_DIR/conf.d/zz-app.ini"

# Pool PHP-FPM : timeout aligné sur nginx (zzz- pour passer après zz-docker.conf)
RUN { \
        echo "[www]"; \
        echo "request_terminate_timeout = 120s"; \
        echo "pm = dynamic"; \
        echo "pm.max_children = 10"; \
        echo "pm.start_servers = 3"; \
        echo "pm.min_spare_servers = 2"; \
        echo "pm.max_spare_servers = 5"; \
    } > /usr/local/etc/php-fpm.d/zzz-app.conf

COPY docker/nginx.conf /etc/nginx/http.d/app.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && (php artisan package:discover --ansi || true)

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
