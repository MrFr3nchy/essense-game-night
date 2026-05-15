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

# Auto-provision ESSENSE_API_KEY on first run if not already set.
# Calls POST /api-key/generate on the challenge API and persists the key to
# .env so subsequent restarts skip this step.
if [ -z "${ESSENSE_API_KEY}" ]; then
    echo "→ ESSENSE_API_KEY not configured — fetching from challenge API..." >&2

    # Disable exit-on-error temporarily so we can inspect each failure mode.
    set +e
    curl -sS --max-time 15 -X POST \
        "https://codechallenge.essensedesigns.info/api-key/generate" \
        > /tmp/essense_body \
        2>/tmp/essense_curl_err
    CURL_EXIT=$?
    set -e

    CURL_BODY=$(cat /tmp/essense_body 2>/dev/null || true)
    CURL_ERR=$(cat /tmp/essense_curl_err 2>/dev/null || true)
    rm -f /tmp/essense_body /tmp/essense_curl_err

    # Log every step so failures are diagnosable.
    if [ "$CURL_EXIT" != "0" ]; then
        echo "  ✗ curl failed — exit code ${CURL_EXIT}" >&2
    fi
    if [ -n "$CURL_ERR" ]; then
        echo "  curl stderr: ${CURL_ERR}" >&2
    fi
    if [ -n "$CURL_BODY" ]; then
        echo "  raw response: ${CURL_BODY}" >&2
    else
        echo "  raw response: (empty)" >&2
    fi

    FETCHED_KEY=""
    if [ -n "$CURL_BODY" ]; then
        FETCHED_KEY=$(printf '%s' "$CURL_BODY" | php -r "
            \$raw = stream_get_contents(STDIN);
            \$data = json_decode(\$raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                fwrite(STDERR, '  ✗ JSON parse error: ' . json_last_error_msg() . PHP_EOL);
                exit;
            }
            if (empty(\$data['key'])) {
                fwrite(STDERR, '  ✗ No \"key\" field in response. Fields present: ' . implode(', ', array_keys(\$data)) . PHP_EOL);
                exit;
            }
            echo \$data['key'];
        " || true)
    fi

    if [ -n "$FETCHED_KEY" ]; then
        export ESSENSE_API_KEY="$FETCHED_KEY"
        # Persist to .env (bind-mounted from host) so restarts pick it up automatically.
        if grep -q '^ESSENSE_API_KEY=' .env 2>/dev/null; then
            sed -i "s/^ESSENSE_API_KEY=.*/ESSENSE_API_KEY=${FETCHED_KEY}/" .env
        else
            echo "ESSENSE_API_KEY=${FETCHED_KEY}" >> .env
        fi
        echo "→ API key fetched and saved to api/.env — no manual setup required." >&2
    else
        echo "WARNING: Could not auto-fetch ESSENSE_API_KEY. Set it manually in api/.env." >&2
    fi
fi

exec "$@"
