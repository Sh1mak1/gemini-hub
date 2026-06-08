#!/usr/bin/env bash
# gemini-hub 本番デプロイ（GitHub Actions または手動 SSH から実行）
#
# 注意: GitHub Actions 側で先に git reset しないこと。
# OLD（更新前 HEAD）と NEW の差分で npm build 等を判定するため。
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/gemini-hub}"
APP_URL="${APP_URL:-https://gemini-hub.duckdns.org}"

cd "$APP_DIR"

echo "==> Deploying in $APP_DIR"

OLD="$(git rev-parse HEAD)"
git fetch origin main
git checkout main
TARGET="$(git rev-parse origin/main)"

if [[ "$OLD" == "$TARGET" ]]; then
  echo "==> Already up to date ($OLD)"
  exit 0
fi

# 本番は GitHub main が正。サーバー上のローカル変更は破棄する。
git reset --hard origin/main
NEW="$(git rev-parse HEAD)"

CHANGED="$(git diff --name-only "$OLD" "$NEW" || true)"
echo "==> Updated $OLD -> $NEW"
echo "$CHANGED"

needs() {
  echo "$CHANGED" | grep -qE "$1"
}

run() {
  echo "==> $*"
  "$@"
}

# --- 依存関係 ---

if needs '^src/composer\.(json|lock)|^docker/php/'; then
  run docker compose exec -T app composer install --no-dev --optimize-autoloader
fi

# --- Docker 構成 ---

if needs '^docker-compose\.yml|^docker/'; then
  run docker compose up -d
fi

# --- DB ---

run docker compose exec -T app php artisan migrate --force

# --- フロントエンド ---

if needs '^src/resources/|^src/package(-lock)?\.json|^src/vite\.config|^src/tailwind\.config|^src/postcss\.config' \
   || [[ ! -f src/public/build/manifest.json ]]; then
  run docker compose run --rm node sh -c "npm ci --legacy-peer-deps && npm run build"
fi

# --- キャッシュクリア（古いキャッシュが残る変更） ---

if needs '^src/config/|^src/routes/|^src/bootstrap/|^src/resources/views/|^src/app/Providers/'; then
  run docker compose exec -T app php artisan optimize:clear
fi

# --- Laravel キャッシュ再生成（毎回） ---

run docker compose exec -T app php artisan config:cache
run docker compose exec -T app php artisan route:cache

if needs '^src/resources/views/|^src/app/View/'; then
  run docker compose exec -T app php artisan view:cache
fi

# --- プロセス再起動 ---

if needs '^src/app/|^src/config/|^src/routes/|^src/bootstrap/|^src/database/|^docker/php/'; then
  run docker compose restart app
fi

if needs '^docker/nginx/'; then
  run docker compose up -d web
fi

run systemctl restart laravel-queue

# --- 確認 ---

run docker compose ps
run systemctl is-active laravel-queue
run curl -fsS "$APP_URL/up" >/dev/null

echo "==> Deploy OK ($NEW)"
