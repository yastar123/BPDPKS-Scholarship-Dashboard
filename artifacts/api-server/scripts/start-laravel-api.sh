#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../laravel-app" && pwd)"

cd "$APP_ROOT"

if [[ "${APP_ENV:-local}" == "production" ]]; then
  export DB_CONNECTION="${DB_CONNECTION:-mysql}"
  export DB_PORT="${DB_PORT:-3306}"
  : "${APP_KEY:?Configure APP_KEY through Replit Secrets before production startup.}"
  : "${DB_HOST:?Configure a persistent MySQL DB_HOST through Replit Secrets before production startup.}"
  : "${DB_DATABASE:?Configure DB_DATABASE through Replit Secrets before production startup.}"
  : "${DB_USERNAME:?Configure DB_USERNAME through Replit Secrets before production startup.}"
  : "${DB_PASSWORD:?Configure DB_PASSWORD through Replit Secrets before production startup.}"
  unset DB_SOCKET
  php artisan migrate --force
else
  export DB_CONNECTION=mysql
  export DB_HOST=localhost
  export DB_PORT=3307
  export DB_DATABASE=bpdpks_scholarship
  export DB_USERNAME=root
  export DB_PASSWORD=
  export DB_SOCKET="$APP_ROOT/storage/app/mysql-data/mysql.sock"

  for attempt in $(seq 1 90); do
    if mysql --connect-timeout=2 --protocol=socket --socket="$DB_SOCKET" --user="$DB_USERNAME" -e "SELECT 1" >/dev/null 2>&1; then
      break
    fi
    if [[ "$attempt" -eq 90 ]]; then
      echo "MySQL did not become ready on the local socket." >&2
      exit 1
    fi
    sleep 1
  done

  mysql --protocol=socket --socket="$DB_SOCKET" --user="$DB_USERNAME" \
    -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
  php artisan migrate --force --seed
fi

cd "$APP_ROOT/public"
exec php -S "0.0.0.0:${PORT:-8080}" \
  "$APP_ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
