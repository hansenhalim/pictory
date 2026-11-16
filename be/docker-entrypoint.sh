#!/bin/sh
# Container entrypoint for the Pictory backend.
#
# - Web server (apache2-foreground): prepares storage, caches config, runs migrations
#   (unless RUN_MIGRATIONS=false), then starts Apache as root so it can bind port 80
#   and drop to www-data for workers.
# - Anything else (e.g. `php artisan queue:work`): runs as www-data so files it writes
#   under storage/ stay writable by the web server.
set -e

cd /var/www/html

as_app_user() {
    runuser -u www-data -- "$@"
}

# Ensure the SQLite file exists (the default DB lives in the storage volume).
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi

chown -R www-data:www-data storage bootstrap/cache

if [ "$1" != "apache2-foreground" ]; then
    exec runuser -u www-data -- "$@"
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "pictory: APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

# Storage link for public disk URLs (safe to repeat).
php artisan storage:link --force >/dev/null 2>&1 || true

as_app_user php artisan config:cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    as_app_user php artisan migrate --force
fi

exec "$@"
