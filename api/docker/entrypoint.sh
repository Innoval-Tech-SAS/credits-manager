#!/bin/sh
set -e

[ -L public/storage ] || php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
