#!/usr/bin/env bash
# WebDevHub Launcher (Linux) — dashboard khamoshi se chalata hai
# aur browser mein khol deta hai. Koi terminal saaf nazar nahi rehta.
set -euo pipefail
REPO="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${WDH_DASH_PORT:-7777}"

bash "$REPO/panel/start-dashboard.sh" start >/dev/null 2>&1 || true

URL="http://127.0.0.1:$PORT/"
if command -v xdg-open >/dev/null 2>&1; then
  xdg-open "$URL" >/dev/null 2>&1 || true
else
  echo "Dashboard: $URL"
fi
