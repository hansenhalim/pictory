# syntax=docker/dockerfile:1
#
# Pictory backend (Laravel 13 + Filament 5 + Livewire 4) image.
#
# Build:
#   docker build -t pictory-be .
#
# Run the web server (Apache on port 80; migrations run on start):
#   docker run -d -p 8080:80 \
#     -e APP_KEY=base64:... \
#     -e APP_URL=https://pictory.id \
#     -v pictory-storage:/var/www/html/storage \
#     pictory-be
#
# Run the queue worker (clip and cover rendering happens here) from the same image:
#   docker run -d \
#     -e APP_KEY=base64:... \
#     -v pictory-storage:/var/www/html/storage \
#     pictory-be php artisan queue:work --tries=3
#
# Generate an APP_KEY once with: docker run --rm pictory-be php artisan key:generate --show
#
# Both containers must share the storage volume when the default SQLite database is used.

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

# ---------------------------------------------------------------------------
# Frontend assets (Vite + Tailwind). Output lands in public/build.
# Note: laravel-vite-plugin downloads the Bunny fonts at build time, so the
# build needs outbound HTTPS.
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
# Tailwind scans app/ and resources/ for class names, so copy the whole tree.
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# Base PHP runtime: extensions Laravel, Filament and the app need, plus ffmpeg
# (clip papers are rendered with ffmpeg, see RenderClipPaper / RenderMomentCover).
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-apache-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ffmpeg \
        curl \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip pdo_mysql \
    && rm -rf /var/lib/apt/lists/* \
    # Fail the build early if ffmpeg lacks what the clip renderer needs.
    && ffmpeg -hide_banner -encoders | grep -q libx264 \
    && ffmpeg -hide_banner -decoders | grep -qi 'av1'

RUN { \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 25M'; \
        echo 'memory_limit = 256M'; \
        echo 'expose_php = Off'; \
    } > "$PHP_INI_DIR/conf.d/pictory.ini"

# ---------------------------------------------------------------------------
# Composer dependencies (production only) and autoloader.
# ---------------------------------------------------------------------------
FROM base AS vendor
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

# Install dependencies first so this layer is cached until composer.* changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# Then copy the application and run the autoload dump, which also triggers the
# package discovery and filament:upgrade post-autoload scripts.
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && composer check-platform-reqs --no-dev

# ---------------------------------------------------------------------------
# Final runtime image.
# ---------------------------------------------------------------------------
FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stack \
    LOG_STACK=single,stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/app/database.sqlite \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    RUN_MIGRATIONS=true

# Serve public/ and allow the .htaccess rewrite rules in public/.
RUN a2enmod rewrite \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=vendor --chown=root:root /app ./
COPY --from=assets /app/public/build ./public/build

COPY docker-entrypoint.sh /usr/local/bin/pictory-entrypoint
RUN chmod 0755 /usr/local/bin/pictory-entrypoint \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost/up || exit 1

ENTRYPOINT ["pictory-entrypoint"]
CMD ["apache2-foreground"]
