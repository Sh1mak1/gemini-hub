#!/usr/bin/env bash
# gemini-hub 本番デプロイ（GitHub Actions または手動 SSH から実行）
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/gemini-hub}"
APP_URL="${APP_URL:-https://gemini-hub.duckdns.org}"

cd "$APP_DIR"

echo "==> Deploying in $APP_DIR"

OLD="$(git rev-parse HEAD)"
git fetch origin main
git checkout main
git pull origin main
NEW="$(git rev-parse HEAD)"

if [[ "$OLD" == "$NEW" ]]; then
  echo "==> Already up to date ($NEW)"
  exit 0
fi

CHANGED="$(git diff --name-only "$OLD" "$NEW" || true)"
echo "==> Updated $OLD -> $NEW"
echo "$CHANGED"

needs() {
  echo "$CHANGED" | grep -qE "$1"
}

if needs '^src/composer\.(json|lock)'; then
  echo "==> composer install"
  docker compose exec -T app composer install --no-dev --optimize-autoloader
fi

echo "==> migrate"
docker compose exec -T app php artisan migrate --force

if needs '^src/resources/|^src/package(-lock)?\.json|^src/vite\.config'; then
  echo "==> npm build"
  docker compose run --rm node sh -c "npm ci --legacy-peer-deps && npm run build"
fi

if needs '^docker/nginx/'; then
  echo "==> reload nginx"
  docker compose up -d web
fi

echo "==> cache config & routes"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache

echo "==> restart queue worker"
systemctl restart laravel-queue

echo "==> health check"
curl -fsS "$APP_URL/up" >/dev/null
echo "==> Deploy OK ($NEW)"
