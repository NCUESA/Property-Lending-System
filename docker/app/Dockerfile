# 開發用 image：PHP 8.4 CLI + Composer + Node 22
# 原始碼以 bind mount 掛進來，不打包進 image；build context 只有 docker/app/。

FROM node:22-bookworm-slim AS node

FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl \
        libpq-dev libicu-dev libzip-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd intl zip bcmath pcntl exif opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 直接從官方 image 複製（同為 Debian bookworm，glibc 相容）
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# 以和主機相同的 UID/GID 執行，bind mount 產生的檔案才不會變成 root 的
RUN groupadd -g "${GID}" app && useradd -m -u "${UID}" -g "${GID}" -s /bin/bash app

# node_modules 會掛 named volume；Docker 初始化空 volume 時沿用 image 內目錄的擁有者，
# 所以要先建好並交給 app，否則 volume 會是 root 的、npm ci 無法寫入
RUN mkdir -p /var/www/html/node_modules && chown -R app:app /var/www/html

COPY php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
USER app

# 讓 laravel-vite-plugin 監聽 0.0.0.0，並把 HMR 位址寫成 localhost；artisan serve 監聽 0.0.0.0
ENV LARAVEL_SAIL=1 \
    SERVER_HOST=0.0.0.0

EXPOSE 8000 5173

ENTRYPOINT ["entrypoint"]
CMD ["composer", "run", "dev"]
