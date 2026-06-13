#!/usr/bin/env bash
# Cursor stop / subagentStop hook: Pushover with implementation summary.
set -euo pipefail

input="$(cat)"

status="$(INPUT="$input" python3 -c 'import json,os; d=json.loads(os.environ["INPUT"]); print(d.get("status",""))' 2>/dev/null || echo unknown)"
event="$(INPUT="$input" python3 -c 'import json,os; d=json.loads(os.environ["INPUT"]); print(d.get("hook_event_name","stop"))' 2>/dev/null || echo stop)"

if [[ "$status" != "completed" ]]; then
  exit 0
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

if [[ "$event" == "subagentStop" ]]; then
  title="Cursor Subagent 指示完了"
else
  title="Cursor Agent 指示完了"
fi

message="$(printf '%s' "$input" | python3 "$SCRIPT_DIR/build-agent-summary.py" 2>/dev/null || true)"

if [[ -z "$message" ]]; then
  message="Agent への指示が完了しました。
ステータス: ${status}"
fi

"$PROJECT_ROOT/scripts/pushover-notify.sh" "$title" "$message" || true

exit 0
