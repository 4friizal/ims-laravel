#!/usr/bin/env bash
# Dev bootstrap: runs before every container command. Each step is a no-op once
# it has been done, so `docker compose up` is safe to re-run.
set -euo pipefail

cd /var/www/html

if [ ! -f .env ]; then
    echo "==> .env missing, copying .env.example"
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    echo "==> Installing composer dependencies"
    composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
    echo "==> Installing bun dependencies"
    bun install --ignore-scripts
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "==> Generating application key"
    php artisan key:generate --no-interaction
fi

# Only the primary container (the one serving HTTP) runs migrations, so the
# queue/vite containers don't race it on startup.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
        touch database/database.sqlite
    else
        echo "==> Waiting for ${DB_HOST:-db}:${DB_PORT:-3306}"
        until mysqladmin ping \
            -h"${DB_HOST:-db}" \
            -P"${DB_PORT:-3306}" \
            -u"${DB_USERNAME:-root}" \
            -p"${DB_PASSWORD:-1234}" --silent >/dev/null 2>&1; do
            sleep 2
        done
    fi

    echo "==> Running migrations"
    php artisan migrate --no-interaction
fi

exec "$@"
