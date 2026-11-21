# syntax=docker/dockerfile:1

# ---- dependências PHP (sem dev) ----
FROM composer:2.8.12 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- runtime: FrankenPHP (Caddy + PHP 8.4 num binário) ----
FROM dunglas/frankenphp:1.9.1-php8.4-bookworm

RUN install-php-extensions pdo_mysql apcu redis opcache intl pcntl \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl procps \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app
COPY --from=vendor /app /app

# Usuário sem privilégios; a porta 8000 não exige capabilities.
RUN useradd --create-home --uid 10001 app \
    && setcap -r /usr/local/bin/frankenphp \
    && mkdir -p storage/framework/cache storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache /config/caddy /data/caddy

USER app
RUN php artisan package:discover --ansi

EXPOSE 8000
CMD ["frankenphp", "php-server", "--root", "public/", "--listen", ":8000"]
