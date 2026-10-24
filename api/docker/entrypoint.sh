#!/bin/sh
set -e

[ -L public/storage ] || php artisan storage:link

php artisan optimize

exec "$@"
