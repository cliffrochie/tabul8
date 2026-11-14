#!/usr/bin/env sh
set -eu

DB_PATH="${DB_DATABASE:-/var/lib/tabul8/database.sqlite}"
DB_DIR="$(dirname "$DB_PATH")"

mkdir -p "$DB_DIR"
if [ ! -f "$DB_PATH" ]; then
  touch "$DB_PATH"
fi

if [ -f /var/www/html/composer.json ] && [ ! -f /var/www/html/vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ -f /var/www/html/.env.example ] && [ ! -f /var/www/html/.env ]; then
  cp /var/www/html/.env.example /var/www/html/.env
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  sqlite3 "$DB_PATH" "PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL; PRAGMA busy_timeout=5000;" >/dev/null 2>&1 || true
  if [ -f /var/www/html/artisan ]; then
    if [ -z "${APP_KEY:-}" ] && [ -f /var/www/html/.env ] && grep -qE '^APP_KEY=$' /var/www/html/.env; then
      php /var/www/html/artisan key:generate --force
    fi
    php /var/www/html/artisan migrate --force
  fi
fi

exec "$@"
