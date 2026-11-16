#!/bin/sh
# Container entrypoint for the Pictory backend.
#
# Starts as root only to prepare the storage volume, then runs the command as www-data:
# - Web server (frankenphp): first caches config, routes, events, views and Filament
#   components, and runs migrations (unless RUN_MIGRATIONS=false).
# - Anything else (e.g. `php artisan queue:work`): runs it as is.
#
# setpriv replaces itself with the command, so the command is PID 1 and gets `docker stop`'s
# SIGTERM directly. (runuser stays as the parent and SIGKILLs its child 2s after a SIGTERM,
# which would cut a clip render short.)
set -e

cd /app

if [ "$(id -u)" = 0 ]; then
    drop_to_app_user() {
        setpriv --reuid=www-data --regid=www-data --init-groups -- "$@"
    }

    # storage/ grows with every photo and clip, so only change what www-data doesn't own yet.
    find storage bootstrap/cache /data/caddy /config/caddy ! -user www-data \
        -exec chown www-data:www-data {} +
else
    drop_to_app_user() {
        "$@"
    }
fi

if [ "$1" = "frankenphp" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "pictory: APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
        exit 1
    fi

    drop_to_app_user php artisan optimize
    drop_to_app_user php artisan filament:optimize

    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        drop_to_app_user php artisan migrate --force
    fi
fi

if [ "$(id -u)" = 0 ]; then
    exec setpriv --reuid=www-data --regid=www-data --init-groups -- "$@"
fi

exec "$@"
