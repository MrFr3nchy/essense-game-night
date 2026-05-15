#!/bin/sh
set -e

mkdir -p bootstrap/cache \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs
chmod -R ug+rwx bootstrap/cache storage 2>/dev/null || true

# In local development, reinstall with dev dependencies so tests can be run.
if [ "$APP_ENV" = "local" ]; then
    composer install --no-interaction --quiet
fi

# APP_KEY must be set in production. In development, auto-generate if missing.
if [ -z "$APP_KEY" ]; then
    if [ "$APP_ENV" = "production" ]; then
        echo "ERROR: APP_KEY is not set. Generate one with 'php artisan key:generate --show' and add it to your environment." >&2
        exit 1
    fi
    echo "WARNING: APP_KEY not set, generating a temporary key (development only)." >&2
    php artisan key:generate --force
fi

exec "$@"
