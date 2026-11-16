# syntax=docker/dockerfile:1
#
# Pictory backend (Laravel 13 + Filament 5 + Livewire 4) image on FrankenPHP, with the
# kiosk (fe/) built into public/kiosk and served at /kiosk.
#
# Build from the repository root:
#   docker build -t registry.pictory.id/pictory:latest .
#
# The database is PostgreSQL. Pass its connection either as DB_URL
# (postgresql://user:password@host:5432/pictory) or as DB_HOST, DB_PORT, DB_DATABASE,
# DB_USERNAME and DB_PASSWORD. Sessions, cache and the queue live in the database too.
#
# Run the web server (plain HTTP on port 80; migrations run on start, so the database must
# already accept connections):
#   docker run -d -p 8080:80 \
#     -e APP_KEY=base64:... \
#     -e APP_URL=https://pictory.id \
#     -e DB_URL=postgresql://pictory:secret@db:5432/pictory \
#     -v pictory-storage:/app/storage \
#     registry.pictory.id/pictory
#
# To have FrankenPHP terminate TLS itself, add -e SERVER_NAME=pictory.id, publish 80, 443
# and 443/udp, and mount a volume at /data so the certificates survive restarts.
#
# Run the queue worker (clip and cover rendering happens here) from the same image. A clip
# render may take up to 300s, so give the worker that long to finish it on `docker stop`:
#   docker run -d --stop-timeout 330 \
#     -e APP_KEY=base64:... \
#     -e DB_URL=postgresql://pictory:secret@db:5432/pictory \
#     -v pictory-storage:/app/storage \
#     registry.pictory.id/pictory php artisan queue:work --tries=3
#
# Generate an APP_KEY once with: docker run --rm registry.pictory.id/pictory php artisan key:generate --show
#
# Both containers must share the storage volume: uploaded photos and rendered clips are
# stored on its local disk.

ARG NODE_VERSION=24

# ---------------------------------------------------------------------------
# Backend frontend assets (Vite + Tailwind). Output lands in public/build.
# Note: laravel-vite-plugin downloads the Bunny fonts at build time, so the
# build needs outbound HTTPS.
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS assets
WORKDIR /src/be
COPY be/package.json be/package-lock.json be/.npmrc ./
RUN npm ci
# Tailwind scans app/ and resources/ for class names, so copy the whole tree.
COPY be/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Kiosk (fe/, Vue + PWA). fe/vite.config.ts writes its build to ../be/public/kiosk.
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS kiosk
WORKDIR /src/fe
COPY fe/package.json fe/package-lock.json ./
RUN npm ci
COPY fe/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Base PHP runtime: FrankenPHP (Caddy + PHP 8.5 on Debian trixie), the extensions
# Laravel, Filament and the app need, plus ffmpeg (clip papers are rendered with ffmpeg,
# see RenderClipPaper / RenderMomentCover). install-php-extensions removes the build
# dependencies it pulls in, so only the runtime libraries stay.
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:latest AS base

# pcntl lets `queue:work` stop gracefully on SIGTERM and enforce each job's $timeout.
RUN install-php-extensions intl zip pdo_pgsql pcntl \
    && apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/* \
    # Fail the build early if ffmpeg lacks what the clip renderer needs.
    && ffmpeg -hide_banner -encoders | grep -q libx264 \
    && ffmpeg -hide_banner -decoders | grep -qi 'av1'

# Production php.ini defaults (display_errors off, etc.), plus the app's own settings. The
# code in the image never changes, so OPcache doesn't need to check file timestamps.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 25M'; \
        echo 'memory_limit = 256M'; \
        echo 'expose_php = Off'; \
        echo 'opcache.memory_consumption = 256'; \
        echo 'opcache.interned_strings_buffer = 16'; \
        echo 'opcache.max_accelerated_files = 20000'; \
        echo 'opcache.validate_timestamps = 0'; \
    } > "$PHP_INI_DIR/conf.d/pictory.ini"

# ---------------------------------------------------------------------------
# Composer dependencies (production only) and autoloader.
# ---------------------------------------------------------------------------
FROM base AS vendor
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /build

# Install dependencies first so this layer is cached until composer.* changes.
COPY be/composer.json be/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# Then copy the application and run the autoload dump, which also triggers the
# package discovery and filament:upgrade post-autoload scripts.
COPY be/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && composer check-platform-reqs --no-dev

# ---------------------------------------------------------------------------
# Final runtime image.
# ---------------------------------------------------------------------------
FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=pgsql \
    SERVER_NAME=:80 \
    RUN_MIGRATIONS=true

# The kiosk must be cross-origin isolated so web-gphoto2 can drive the DSLR (see the kiosk
# route in routes/web.php). public/kiosk is a real directory, so without the rewrite below
# its index.html would be served as a static file, skipping the route's auth check and its
# COOP/COEP headers. Send the kiosk shell to Laravel instead (FrankenPHP still hands Laravel
# the original REQUEST_URI), and add the same headers to the kiosk's static files (its
# service worker caches index.html, and its workers need COEP).
COPY <<'EOF' /etc/frankenphp/Caddyfile
{
	skip_install_trust

	{$CADDY_GLOBAL_OPTIONS}

	frankenphp {
		{$FRANKENPHP_CONFIG}
	}
}

{$CADDY_EXTRA_CONFIG}

{$SERVER_NAME::80} {
	root /app/public
	encode zstd br gzip
	log

	redir /kiosk /kiosk/ 308

	@kioskShell path /kiosk/ /kiosk/index.html
	rewrite @kioskShell /index.php

	header /kiosk/* {
		Cross-Origin-Opener-Policy "same-origin"
		Cross-Origin-Embedder-Policy "credentialless"
	}

	php_server
}
EOF

WORKDIR /app
COPY --from=vendor --chown=root:root /build ./
COPY --from=assets /src/be/public/build ./public/build
COPY --from=kiosk /src/be/public/kiosk ./public/kiosk

# Everything runs as www-data (see the entrypoint): it owns the writable app directories and
# Caddy's data and config directories. The frankenphp binary already has the capability to
# bind ports 80 and 443 without root. The public disk link is fixed, so it's made here
# rather than on every start.
COPY be/docker-entrypoint.sh /usr/local/bin/pictory-entrypoint
RUN chmod 0755 /usr/local/bin/pictory-entrypoint \
    && rm -f public/.htaccess \
    && ln -sfn ../storage/app/public public/storage \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy

EXPOSE 80 443 443/udp

# Only the web server answers HTTP; a container running something else (the queue worker)
# counts as healthy while it runs.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD [ "$(cat /proc/1/comm)" != frankenphp ] || curl -fsS http://localhost/up >/dev/null || exit 1

ENTRYPOINT ["pictory-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
