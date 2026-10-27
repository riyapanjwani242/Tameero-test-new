#!/usr/bin/env bash
# WebDevHub Dashboard chalana/band karna.
# Usage: start-dashboard.sh start|stop   (port: WDH_DASH_PORT, default 7777)
set -euo pipefail
REPO="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${WDH_DASH_PORT:-7777}"
PIDFILE="$REPO/panel/.dashboard.pid"

PHP_BIN="${WDH_PHP:-}"
if [ -z "$PHP_BIN" ]; then
  if command -v php >/dev/null 2>&1; then PHP_BIN="$(command -v php)";
  elif [ -x "$HOME/workspace/webdevhub-test/php-common/php" ]; then PHP_BIN="$HOME/workspace/webdevhub-test/php-common/php"; fi
fi

case "${1:-start}" in
  start)
    if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
      echo "Dashboard pehle se chal raha hai: http://127.0.0.1:$PORT"; exit 0
    fi
    PHP_CLI_SERVER_WORKERS=4 nohup "$PHP_BIN" -S "127.0.0.1:$PORT" \
      -t "$REPO/panel/public" "$REPO/panel/server.php" \
      > "$REPO/panel/.dashboard.log" 2>&1 &
    echo $! > "$PIDFILE"
    for i in $(seq 1 10); do
      if curl -s -o /dev/null --max-time 20 "http://127.0.0.1:$PORT/"; then break; fi
      sleep 1
    done
    echo "Dashboard tayyar hai: http://127.0.0.1:$PORT"
    ;;
  stop)
    if [ -f "$PIDFILE" ]; then
      PID="$(cat "$PIDFILE")"
      kill "$PID" 2>/dev/null || true
      for i in $(seq 1 10); do kill -0 "$PID" 2>/dev/null || break; sleep 0.5; done
      kill -9 "$PID" 2>/dev/null || true
      rm -f "$PIDFILE"
      echo "Dashboard band ho gaya."
    fi
    ;;
  *) echo "Usage: start-dashboard.sh start|stop"; exit 1;;
esac
