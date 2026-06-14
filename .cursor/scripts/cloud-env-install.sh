#!/usr/bin/env bash
# Idempotent Cloud Agent bootstrap for gemini-hub.
# Runs from repo root on each agent start (cached after first successful run).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

sudo service docker start
docker info >/dev/null

# Docker Compose reads these from repo-root .env (ephemeral on cloud VM).
cat > .env <<'EOF'
APP_PORT=8000
VITE_PORT=5173
POSTGRES_DB=laravel
POSTGRES_USER=laravel_user
POSTGRES_PASSWORD=password
EOF

if [[ ! -f src/.env ]]; then
  cp src/.env.example src/.env
fi

upsert_env() {
  local key="$1"
  local value="${2:-}"
  if [[ -z "$value" ]]; then
    return 0
  fi
  if grep -q "^${key}=" src/.env; then
    sed -i "s|^${key}=.*|${key}=${value}|" src/.env
  else
    printf '%s=%s\n' "$key" "$value" >> src/.env
  fi
}

# Secrets: register in Cursor Dashboard → Cloud Agents → Secrets.
upsert_env GEMINI_API_KEY "${GEMINI_API_KEY:-}"
upsert_env DRAFTS_API_TOKEN "${DRAFTS_API_TOKEN:-cloud-test-token}"
upsert_env SLACK_SIGNING_SECRET "${SLACK_SIGNING_SECRET:-}"
upsert_env SLACK_BOT_TOKEN "${SLACK_BOT_TOKEN:-}"

docker compose build
docker compose up -d db

echo "Waiting for PostgreSQL..."
for _ in $(seq 1 60); do
  if docker compose exec -T db pg_isready -U laravel_user -d laravel >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

docker compose up -d

docker compose exec -T app composer install --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=base64:' src/.env; then
  docker compose exec -T app php artisan key:generate --force --no-interaction
fi

docker compose exec -T app php artisan migrate --force --no-interaction

# Frontend assets: required so `php artisan test` (Inertia feature tests render
# pages and need public/build/manifest.json) and direct rendering work. For HMR
# dev work run `docker compose exec node npm run dev` instead.
docker compose run --rm node sh -c "npm ci --legacy-peer-deps && npm run build"

echo "Cloud environment ready."
