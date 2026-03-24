#!/usr/bin/env sh
set -e

mkdir -p /var/www/storage/framework/cache
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/app/public
mkdir -p /var/www/bootstrap/cache

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

if [ ! -L /var/www/public/storage ]; then
    rm -rf /var/www/public/storage
    php artisan storage:link >/dev/null 2>&1 || true
fi

exec "$@"
