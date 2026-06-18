#!/bin/sh
set -e

DB_HOST="${DB_HOST:-pgsql}"
DB_PORT="${DB_PORT:-5432}"

echo "[entrypoint] Aguardando PostgreSQL em ${DB_HOST}:${DB_PORT}..."
n=0
until php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT}) ? 0 : 1);" 2>/dev/null; do
    n=$((n+1))
    if [ "$n" -ge 60 ]; then
        echo "[entrypoint] Timeout: PostgreSQL nao respondeu apos 120s"
        exit 1
    fi
    sleep 2
done
echo "[entrypoint] PostgreSQL respondendo."

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] Executando migrations..."
    php artisan migrate --force --no-interaction
fi

echo "[entrypoint] Otimizando configuracoes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Iniciando servicos..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
