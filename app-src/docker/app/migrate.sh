#!/bin/sh
set -e

DB_HOST="${DB_HOST:-pgsql}"
DB_PORT="${DB_PORT:-5432}"

echo "[migrate] aguardando PostgreSQL em ${DB_HOST}:${DB_PORT}..."
n=0
until php -r "exit(@fsockopen(getenv('DB_HOST') ?: 'pgsql', (int)(getenv('DB_PORT') ?: 5432)) ? 0 : 1);" 2>/dev/null; do
    n=$((n+1))
    if [ "$n" -ge 60 ]; then
        echo "[migrate] timeout: PostgreSQL nao respondeu apos 120s"
        exit 1
    fi
    sleep 2
done

echo "[migrate] PostgreSQL respondendo. Executando migrations..."
php /var/www/html/artisan migrate --force --no-interaction -vvv
