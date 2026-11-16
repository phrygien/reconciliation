#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
mkdir -p storage/app/private/depot_winparf/received storage/app/private/depot_winparf/processed storage/app/private/depot_winparf/failed storage/app/private/depot_winparf/init
mkdir -p storage/app/public

php artisan storage:link --force || true
php artisan filament:assets || true
php artisan optimize
php artisan icons:cache 2>/dev/null || true
php artisan filament:optimize 2>/dev/null || true

# chown uniquement sur ce qui n'appartient pas déjà à www-data (rapide sur gros volumes)
find storage bootstrap/cache ! -user www-data -exec chown www-data:www-data {} +

exec supervisord -c /etc/supervisord.conf
