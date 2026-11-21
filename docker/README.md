# Docker 開發環境

一個 `app` 容器（PHP 8.4、Composer、Node 22）加一個 `pgsql`（PostgreSQL 17）。
原始碼以 bind mount 掛進容器，主機上改檔案會即時生效（Vite HMR）。

## 第一次啟動

```bash
cp .env.example .env     # 填入 DB_PASSWORD、NCUESA_SSO_* 等
docker compose up -d --build
docker compose logs -f app
```

第一次啟動時 entrypoint 會自動執行 `composer install`、`npm ci`、產生 `APP_KEY`、`migrate`、`storage:link`，
然後跑 `composer run dev`（server、queue、logs、vite）。之後每次啟動只會跑尚未執行的 migration。

- 網站：http://localhost:8000
- 資料庫：`localhost:5432`（帳密同 `.env`）

## 常用指令

```bash
docker compose exec app php artisan test
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker
docker compose exec app composer require ...
docker compose exec app npm install ...
docker compose exec pgsql psql -U "$DB_USERNAME" -d "$DB_DATABASE"
docker compose down            # 停止（保留資料）
docker compose down -v         # 停止並清空資料庫與 node_modules volume
```

## 設定說明

| 項目 | 說明 |
|---|---|
| `DB_HOST` | `compose.yaml` 會在容器內覆寫成 `pgsql`，`.env` 維持 `127.0.0.1` 也沒關係 |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | 同時給 Laravel 和 PostgreSQL 容器使用，只需在 `.env` 設一次 |
| 測試資料庫 | `property_testing`，在資料庫 volume 第一次建立時自動建立；`phpunit.xml` 以 `force` 指定，測試不會動到開發資料 |
| port 衝突 | 在 `.env` 設 `APP_PORT`、`VITE_PORT`、`FORWARD_DB_PORT` |
| 檔案擁有者 | Linux 主機 UID/GID 不是 1000 時，在 `.env` 設 `WWWUSER`、`WWWGROUP` 後重新 build |
| `node_modules` | 放在 named volume，避免和 macOS／Windows 主機上的原生套件互相覆蓋 |

SSO 的回呼網址仍是 `http://localhost:8000/auth/sso/callback`；若改了 `APP_PORT`，
`.env` 的 `NCUESA_SSO_REDIRECT_URI` 與 Keycloak client 設定都要一起改。

## 搬移現有的開發資料庫

在舊環境匯出，再匯入容器：

```bash
# 舊環境
pg_dump -h 127.0.0.1 -U <user> -d property --no-owner --no-privileges -Fc -f property.dump

# 新環境（容器已啟動）
docker compose cp property.dump pgsql:/tmp/property.dump
docker compose exec pgsql pg_restore -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists --no-owner /tmp/property.dump
```

`*.dump`、`*.sql` 可能含個資，不要 commit。
