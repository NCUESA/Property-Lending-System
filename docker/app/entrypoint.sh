#!/usr/bin/env bash
# 第一次啟動時補齊依賴與設定；之後每次啟動只會跑尚未執行的 migration。
set -euo pipefail
cd /var/www/html

if [ ! -f .env ]; then
    echo "[entrypoint] 建立 .env（從 .env.example）"
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] composer install"
    composer install --no-interaction --prefer-dist
fi

if [ ! -x node_modules/.bin/vite ]; then
    echo "[entrypoint] npm ci"
    npm ci
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
    echo "[entrypoint] 產生 APP_KEY"
    php artisan key:generate --ansi
fi

php artisan migrate --force --ansi

if [ ! -L public/storage ]; then
    php artisan storage:link --ansi
fi

exec "$@"
