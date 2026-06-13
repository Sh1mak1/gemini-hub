#!/usr/bin/env bash
# Send a Pushover notification (deploy script, Cursor hooks, manual use).
# Credentials: PUSHOVER_APP_TOKEN + PUSHOVER_USER_KEY env vars, or src/.env.
set -euo pipefail

read_env_value() {
  local key="$1"
  local file="$2"

  if [[ ! -f "$file" ]]; then
    return 1
  fi

  local line
  line="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -1 || true)"
  if [[ -z "$line" ]]; then
    return 1
  fi

  local value="${line#*=}"
  value="${value%$'\r'}"
  value="${value#\"}"
  value="${value%\"}"
  value="${value#\'}"
  value="${value%\'}"

  if [[ -n "$value" ]]; then
    printf '%s' "$value"
    return 0
  fi

  return 1
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="${PUSHOVER_ENV_FILE:-$PROJECT_ROOT/src/.env}"

TOKEN="${PUSHOVER_APP_TOKEN:-$(read_env_value PUSHOVER_APP_TOKEN "$ENV_FILE" || true)}"
USER_KEY="${PUSHOVER_USER_KEY:-$(read_env_value PUSHOVER_USER_KEY "$ENV_FILE" || true)}"

if [[ -z "$TOKEN" || -z "$USER_KEY" ]]; then
  echo "Pushover not configured (skip)" >&2
  exit 0
fi

TITLE="${1:-Notification}"
MESSAGE="${2:-}"
PRIORITY="${PUSHOVER_PRIORITY:-0}"

if [[ -z "$MESSAGE" ]]; then
  echo "Usage: pushover-notify.sh <title> <message>" >&2
  exit 1
fi

curl -fsS \
  --form-string "token=$TOKEN" \
  --form-string "user=$USER_KEY" \
  --form-string "title=$TITLE" \
  --form-string "message=$MESSAGE" \
  --form-string "priority=$PRIORITY" \
  https://api.pushover.net/1/messages.json >/dev/null

echo "Pushover sent: $TITLE" >&2
