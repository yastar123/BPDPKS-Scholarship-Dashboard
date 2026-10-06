#!/usr/bin/env bash
set -euo pipefail

MYSQLD="$(command -v mysqld)"
BASE_DIR="$(dirname "$(dirname "$(readlink -f "$MYSQLD")")")"
DATA_DIR="$PWD/artifacts/api-server/laravel-app/storage/app/mysql-data"
mkdir -p "$DATA_DIR"

if [[ ! -d "$DATA_DIR/mysql" ]]; then
  "$MYSQLD" --no-defaults --basedir="$BASE_DIR" --datadir="$DATA_DIR" \
    --user="$(id -un)" --initialize-insecure
fi

exec "$MYSQLD" --no-defaults --basedir="$BASE_DIR" --datadir="$DATA_DIR" \
  --user="$(id -un)" --socket="$DATA_DIR/mysql.sock" \
  --pid-file="$DATA_DIR/mysql.pid" --port=3307 --bind-address=127.0.0.1 \
  --mysqlx=0 --skip-name-resolve --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_0900_ai_ci
